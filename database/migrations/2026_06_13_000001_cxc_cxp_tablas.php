<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod las tablas contact_*, core_cuentas_default, cxc_* y
     * cxp_* ya existen (esquema maestro); en tests (SQLite) hay que
     * crearlas. Cada bloque va con guarda hasTable.
     */
    public function up(): void
    {
        if (! Schema::hasTable('contact_tipos')) {
            Schema::create('contact_tipos', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 100);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('contact_contactos')) {
            Schema::create('contact_contactos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 50)->nullable();
                $table->string('nombre', 200);
                $table->string('razon_social', 250)->nullable();
                $table->string('tipo_persona', 20)->default('JURIDICA');
                $table->string('identificacion', 50)->nullable();
                $table->string('dv', 5)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->text('direccion')->nullable();
                $table->string('pais', 100)->nullable()->default('Panamá');
                $table->string('provincia', 100)->nullable();
                $table->string('distrito', 100)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'codigo']);
            });
        }

        if (! Schema::hasTable('contact_contactos_tipos')) {
            Schema::create('contact_contactos_tipos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contacto_id');
                $table->unsignedBigInteger('tipo_id');
                $table->timestamps();
                $table->unique(['contacto_id', 'tipo_id']);
            });
        }

        if (! Schema::hasTable('core_cuentas_default')) {
            Schema::create('core_cuentas_default', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('clave', 50);
                $table->unsignedBigInteger('cuenta_id');
                $table->string('descripcion', 200)->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'clave']);
            });
        }

        foreach (['cxc' => 'cliente_id', 'cxp' => 'proveedor_id'] as $prefijo => $contraparte) {
            if (! Schema::hasTable("{$prefijo}_documentos")) {
                Schema::create("{$prefijo}_documentos", function (Blueprint $table) use ($contraparte) {
                    $table->id();
                    $table->unsignedBigInteger('compania_id');
                    $table->unsignedBigInteger($contraparte);
                    $table->string('tipo_documento', 40);
                    $table->string('numero', 50);
                    $table->date('fecha');
                    $table->date('fecha_vencimiento')->nullable();
                    $table->unsignedBigInteger('moneda_id')->nullable();
                    $table->decimal('subtotal', 18, 2)->default(0);
                    $table->decimal('descuento', 18, 2)->default(0);
                    $table->decimal('impuesto', 18, 2)->default(0);
                    $table->decimal('total', 18, 2)->default(0);
                    $table->decimal('saldo', 18, 2)->default(0);
                    $table->string('estado', 30)->default('PENDIENTE');
                    $table->unsignedBigInteger('asiento_id')->nullable();
                    if ($contraparte === 'cliente_id') {
                        $table->unsignedBigInteger('fel_documento_id')->nullable();
                    } else {
                        $table->unsignedBigInteger('adjunto_id')->nullable();
                    }
                    $table->timestamps();
                    $table->string('created_by', 200)->nullable();
                    $table->string('updated_by', 200)->nullable();
                });
            }

            if (! Schema::hasTable("{$prefijo}_documentos_detalle")) {
                Schema::create("{$prefijo}_documentos_detalle", function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('documento_id');
                    $table->integer('linea');
                    $table->unsignedBigInteger('item_id')->nullable();
                    $table->text('descripcion');
                    $table->decimal('cantidad', 18, 4)->default(1);
                    $table->decimal('precio_unitario', 18, 4)->default(0);
                    $table->decimal('descuento', 18, 2)->default(0);
                    $table->unsignedBigInteger('impuesto_id')->nullable();
                    $table->decimal('impuesto_monto', 18, 2)->default(0);
                    $table->decimal('total_linea', 18, 2)->default(0);
                    $table->unsignedBigInteger('cuenta_id')->nullable();
                    $table->timestamps();
                    $table->string('created_by', 200)->nullable();
                    $table->string('updated_by', 200)->nullable();
                    $table->unique(['documento_id', 'linea']);
                });
            }

            if (! Schema::hasTable("{$prefijo}_aplicaciones")) {
                Schema::create("{$prefijo}_aplicaciones", function (Blueprint $table) use ($contraparte) {
                    $table->id();
                    $table->unsignedBigInteger('compania_id');
                    $table->unsignedBigInteger($contraparte);
                    $table->unsignedBigInteger('documento_origen_id');
                    $table->unsignedBigInteger('documento_destino_id');
                    $table->date('fecha');
                    $table->decimal('monto_aplicado', 18, 2);
                    $table->timestamps();
                    $table->string('created_by', 200)->nullable();
                    $table->string('updated_by', 200)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // Solo aplica a tests (SQLite); en dev/prod las tablas son del
        // esquema maestro y no se tocan.
    }
};
