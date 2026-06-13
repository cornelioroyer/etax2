<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod las tablas compras_* ya existen (esquema maestro de la
     * suite planilla); en tests (SQLite) hay que crearlas. Cada bloque va
     * con guarda hasTable (no-op en dev/prod).
     *
     * Las órdenes/recepciones usan líneas de texto libre (como ventas/cxc),
     * así que se agregan a compras_recepciones_detalle dos columnas que el
     * esquema maestro no trae: orden_detalle_id y descripcion (con guarda
     * hasColumn para la tabla preexistente).
     */
    public function up(): void
    {
        if (! Schema::hasTable('compras_ordenes')) {
            Schema::create('compras_ordenes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('proveedor_id');
                $table->string('numero', 50);
                $table->date('fecha');
                $table->string('estado', 30)->default('BORRADOR');
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('itbms', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->unsignedBigInteger('adjunto_id')->nullable();
                $table->unsignedBigInteger('cxp_documento_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        } else {
            // Tabla maestra preexistente: enlace a la factura CxP generada.
            if (! Schema::hasColumn('compras_ordenes', 'cxp_documento_id')) {
                Schema::table('compras_ordenes', function (Blueprint $table) {
                    $table->unsignedBigInteger('cxp_documento_id')->nullable()->after('adjunto_id');
                });
            }
        }

        if (! Schema::hasTable('compras_ordenes_detalle')) {
            Schema::create('compras_ordenes_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->integer('linea');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->text('descripcion');
                $table->decimal('cantidad', 18, 4)->default(1);
                $table->decimal('precio_unitario', 18, 4)->default(0);
                $table->unsignedBigInteger('impuesto_id')->nullable();
                $table->decimal('total_linea', 18, 2)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('compras_recepciones')) {
            Schema::create('compras_recepciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('orden_id')->nullable();
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->string('numero', 50);
                $table->date('fecha');
                $table->string('estado', 30)->default('RECIBIDO');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('compras_recepciones_detalle')) {
            Schema::create('compras_recepciones_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recepcion_id');
                $table->unsignedBigInteger('orden_detalle_id')->nullable();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->text('descripcion')->nullable();
                $table->decimal('cantidad', 18, 4)->default(0);
                $table->decimal('costo', 18, 4)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        } else {
            // Tabla maestra preexistente: agregar columnas de línea de texto libre.
            Schema::table('compras_recepciones_detalle', function (Blueprint $table) {
                if (! Schema::hasColumn('compras_recepciones_detalle', 'orden_detalle_id')) {
                    $table->unsignedBigInteger('orden_detalle_id')->nullable()->after('recepcion_id');
                }
                if (! Schema::hasColumn('compras_recepciones_detalle', 'descripcion')) {
                    $table->text('descripcion')->nullable()->after('item_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_recepciones_detalle');
        Schema::dropIfExists('compras_recepciones');
        Schema::dropIfExists('compras_ordenes_detalle');
        Schema::dropIfExists('compras_ordenes');
    }
};
