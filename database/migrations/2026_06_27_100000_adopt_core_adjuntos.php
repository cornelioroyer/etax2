<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adopta la infraestructura central de adjuntos `core_adjuntos`.
 *
 * La tabla YA EXISTE en dev y prod (se creó fuera de las migraciones: drift),
 * por eso esta migración es IDEMPOTENTE:
 *   - en instalaciones nuevas / tests (sqlite) crea la tabla con un esquema
 *     funcionalmente equivalente al de producción;
 *   - en dev/prod, donde la tabla ya existe, NO la recrea y solo agrega el
 *     índice de búsqueda por documento de origen que faltaba en el esquema.
 *
 * down() NO elimina la tabla (contiene/contendrá datos y existía antes de esta
 * migración); solo revierte el índice que esta migración agrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_adjuntos')) {
            Schema::create('core_adjuntos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('compania_id')->nullable()
                    ->constrained('core_companias')->cascadeOnDelete();

                // Puntero de origen denormalizado (polimórfico plano): identifica
                // a qué registro de qué tabla/módulo pertenece el adjunto.
                $table->string('modulo', 50)->nullable();
                $table->string('tabla_origen', 100)->nullable();
                $table->unsignedBigInteger('registro_id')->nullable();

                $table->string('nombre_archivo', 255);
                $table->string('mime_type', 100)->nullable();
                $table->string('extension', 20)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();

                $table->string('storage_disk', 30)->default('s3');
                $table->text('storage_path');
                $table->text('thumbnail_path')->nullable();
                $table->text('url')->nullable();
                $table->string('hash_archivo', 128)->nullable();

                $table->foreignId('usuario_id')->nullable()->constrained('users');

                $table->timestampsTz();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->index('compania_id', 'idx_core_adjuntos_compania_id');
                $table->index('usuario_id', 'idx_core_adjuntos_usuario_id');
            });
        }

        // Índice de origen — faltante en el esquema actual de dev/prod. Acelera
        // "dame los adjuntos de este documento". IF NOT EXISTS funciona tanto en
        // PostgreSQL como en SQLite (tests), así que sirve en ambos caminos.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_core_adjuntos_origen '.
            'ON core_adjuntos (compania_id, tabla_origen, registro_id)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_core_adjuntos_origen');
    }
};
