<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa de DENEGACIÓN de permisos por usuario y compañía.
 *
 * Spatie laravel-permission es aditivo: los permisos efectivos son
 *   (permisos del rol) ∪ (permisos directos).
 * Esta tabla agrega el override negativo que falta, para poder QUITAR a un
 * usuario en concreto un permiso que su rol sí otorga, sin tocar el rol ni
 * afectar a los demás usuarios. La resolución final pasa a ser:
 *   efectivos = (rol ∪ directos) − denegados
 *
 * Misma estructura/llaves que seg_usuarios_permisos (model_has_permissions),
 * con team_foreign_key = compania_id para mantener el aislamiento multiempresa.
 */
return new class extends Migration
{
    private function tabla(): string
    {
        // Mismo nombre de tabla base que usa Spatie, con sufijo _denegados.
        $base = config('permission.table_names.model_has_permissions', 'seg_usuarios_permisos');

        return $base.'_denegados';
    }

    public function up(): void
    {
        $tabla = $this->tabla();
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $morphKey = $columnNames['model_morph_key'] ?? 'model_id';

        if (Schema::hasTable($tabla)) {
            return;
        }

        Schema::create($tabla, static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $morphKey, $teams) {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'denegados_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'denegados_team_foreign_key_index');

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotPermission, $morphKey, 'model_type'],
                    'denegados_permission_model_type_primary'
                );
            } else {
                $table->primary(
                    [$pivotPermission, $morphKey, 'model_type'],
                    'denegados_permission_model_type_primary'
                );
            }
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla());
    }
};
