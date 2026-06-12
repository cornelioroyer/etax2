<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En dev/prod las tablas fel_* ya existen (esquema maestro);
        // en tests (SQLite) hay que crearlas.
        if (! Schema::hasTable('fel_configuracion')) {
            Schema::create('fel_configuracion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id')->unique();
                $table->string('ambiente', 20)->default('PRUEBAS');
                $table->string('proveedor', 100)->nullable();
                $table->text('token')->nullable();
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('fel_documentos')) {
            Schema::create('fel_documentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('tipo_documento', 50);
                $table->string('documento_origen', 100);
                $table->unsignedBigInteger('documento_id');
                $table->string('numero', 50);
                $table->date('fecha');
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('itbms', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->string('estado_fel', 30)->default('PENDIENTE');
                $table->text('cufe')->nullable();
                $table->text('qr')->nullable();
                $table->text('xml_path')->nullable();
                $table->text('pdf_path')->nullable();
                $table->jsonb('respuesta_dgi')->nullable();
                $table->timestampTz('fecha_envio')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'tipo_documento', 'numero']);
            });
        }

        if (! Schema::hasTable('fel_documentos_detalle')) {
            Schema::create('fel_documentos_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fel_documento_id');
                $table->integer('linea');
                $table->text('descripcion');
                $table->decimal('cantidad', 18, 4)->default(1);
                $table->decimal('precio_unitario', 18, 4)->default(0);
                $table->decimal('impuesto_monto', 18, 2)->default(0);
                $table->decimal('total_linea', 18, 2)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['fel_documento_id', 'linea']);
            });
        }

        if (! Schema::hasTable('fel_eventos')) {
            Schema::create('fel_eventos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fel_documento_id');
                $table->string('evento', 50);
                $table->text('descripcion')->nullable();
                $table->jsonb('respuesta')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        // Campos para The Factory HKA (token dual + numeración)
        Schema::table('fel_configuracion', function (Blueprint $table) {
            if (! Schema::hasColumn('fel_configuracion', 'token_empresa')) {
                $table->text('token_empresa')->nullable();
            }
            if (! Schema::hasColumn('fel_configuracion', 'token_password')) {
                $table->text('token_password')->nullable();
            }
            if (! Schema::hasColumn('fel_configuracion', 'punto_facturacion')) {
                $table->string('punto_facturacion', 10)->default('001');
            }
            if (! Schema::hasColumn('fel_configuracion', 'codigo_sucursal')) {
                $table->string('codigo_sucursal', 10)->default('0000');
            }
            if (! Schema::hasColumn('fel_configuracion', 'correlativo')) {
                $table->unsignedBigInteger('correlativo')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fel_configuracion', function (Blueprint $table) {
            foreach (['token_empresa', 'token_password', 'punto_facturacion', 'codigo_sucursal', 'correlativo'] as $col) {
                if (Schema::hasColumn('fel_configuracion', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
