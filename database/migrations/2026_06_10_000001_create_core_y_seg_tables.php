<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas core_ y seg_ del esquema contable (diseño v3.5).
 *
 * En etax2_dev estas tablas ya existen (creadas por etax2_schema_final.sql),
 * por eso cada bloque va con guarda hasTable: la migración solo las crea
 * donde falten (BD de tests SQLite, futuro deploy a producción).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_planes')) {
            Schema::create('core_planes', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 100);
                $table->decimal('precio_mensual', 18, 2)->default(0);
                $table->integer('max_documentos_ia_mes')->nullable();
                $table->integer('max_storage_mb')->nullable();
                $table->integer('max_usuarios')->nullable();
                $table->jsonb('modulos_incluidos')->default('[]');
                $table->boolean('activo')->default(true);
                $table->timestampsTz();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('core_zonas')) {
            Schema::create('core_zonas', function (Blueprint $table) {
                $table->id();
                $table->string('description', 200);
                $table->timestampsTz();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('core_companias')) {
            Schema::create('core_companias', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 200);
                $table->string('razon_social', 250)->nullable();
                $table->string('ruc', 50)->nullable();
                $table->string('dv', 5)->nullable();
                $table->text('direccion')->nullable();
                $table->string('direccion2', 200)->nullable();
                $table->string('direccion3', 200)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('telefono2', 50)->nullable();
                $table->string('fax', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->text('logo_url')->nullable();
                $table->string('sello_url', 255)->nullable();
                $table->string('firma_cartas', 200)->nullable();
                $table->string('cargo', 100)->nullable();
                $table->text('mensaje')->nullable();
                $table->integer('correlativo_ss')->default(0);
                $table->date('fecha_de_apertura')->nullable();
                $table->date('fecha_de_expiracion')->nullable();
                $table->string('no_patronal', 100)->nullable();
                $table->string('act_economica', 100)->nullable();
                $table->string('cedula', 50)->nullable();
                $table->string('licencia', 50)->nullable();
                $table->string('repre_legal', 200)->nullable();
                $table->string('cedula_repre_legal', 100)->nullable();
                $table->foreignId('zonas_id')->nullable()->constrained('core_zonas');
                $table->foreignId('plan_id')->nullable()->constrained('core_planes');
                $table->string('tipo_de_entidad', 100)->nullable();
                $table->text('constitucion')->nullable();
                $table->string('token', 50)->nullable();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->string('nit', 50)->nullable();
                $table->string('municipio', 200)->nullable();
                $table->string('clave_municipio', 200)->nullable();
                $table->string('metodo_costeo', 20)->default('PROMEDIO');
                $table->boolean('permitir_stock_negativo')->default(false);
                $table->jsonb('extra')->default('{}');
                $table->boolean('activa')->default(true);
                $table->timestampsTz();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['ruc', 'dv'], 'uq_core_companias_ruc');
            });
        }

        // Las tablas seg_* (roles/permisos) las crea la migracion de spatie
        // (create_permission_tables) usando los nombres del config
        // permission.table_names — no se crean aqui para evitar duplicados.

        // Campos heredados del diseño seg_usuarios en la tabla users
        if (! Schema::hasColumn('users', 'telefono')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('telefono', 50)->nullable();
                $table->timestampTz('ultimo_login')->nullable();
                $table->boolean('activo')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_companias');
        Schema::dropIfExists('core_zonas');
        Schema::dropIfExists('core_planes');
    }
};
