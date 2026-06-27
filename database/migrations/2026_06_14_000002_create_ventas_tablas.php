<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod las tablas ventas_* ya existen (esquema maestro de la
     * suite planilla); en tests (SQLite) hay que crearlas. Cada bloque va
     * con guarda hasTable, así que en dev/prod es no-op.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ventas_cotizaciones')) {
            Schema::create('ventas_cotizaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('cliente_id');
                $table->string('numero', 50);
                $table->date('fecha');
                $table->date('fecha_validez')->nullable();
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('descuento', 18, 2)->default(0);
                $table->decimal('itbms', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->string('estado', 30)->default('BORRADOR');
                $table->json('extra')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('ventas_cotizaciones_detalle')) {
            Schema::create('ventas_cotizaciones_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cotizacion_id');
                $table->integer('linea');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->text('descripcion');
                $table->decimal('cantidad', 18, 4)->default(1);
                $table->decimal('precio_unitario', 18, 4)->default(0);
                $table->decimal('descuento', 18, 2)->default(0);
                $table->unsignedBigInteger('impuesto_id')->nullable();
                $table->decimal('impuesto_monto', 18, 2)->default(0);
                $table->decimal('total_linea', 18, 2)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('ventas_facturas')) {
            Schema::create('ventas_facturas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('cliente_id');
                $table->string('numero', 50);
                $table->date('fecha');
                $table->date('fecha_vencimiento')->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('descuento', 18, 2)->default(0);
                $table->decimal('itbms', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->decimal('saldo', 18, 2)->default(0);
                $table->string('estado', 30)->default('BORRADOR');
                $table->string('tipo_documento', 30)->default('FACTURA');
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('cotizacion_id')->nullable();
                $table->unsignedBigInteger('cxc_documento_id')->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedBigInteger('fel_documento_id')->nullable();
                $table->json('extra')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('ventas_facturas_detalle')) {
            Schema::create('ventas_facturas_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('factura_id');
                $table->integer('linea');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->text('descripcion');
                $table->decimal('cantidad', 18, 4)->default(1);
                $table->decimal('precio_unitario', 18, 4)->default(0);
                $table->decimal('descuento', 18, 2)->default(0);
                $table->unsignedBigInteger('impuesto_id')->nullable();
                $table->decimal('impuesto_monto', 18, 2)->default(0);
                $table->decimal('total_linea', 18, 2)->default(0);
                $table->unsignedBigInteger('cuenta_ingreso_id')->nullable();
                $table->unsignedBigInteger('centro_costo_id')->nullable();
                $table->unsignedBigInteger('proyecto_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('ventas_recibos')) {
            Schema::create('ventas_recibos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('cliente_id');
                $table->string('numero', 50);
                $table->date('fecha');
                $table->string('metodo_pago', 50)->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->decimal('total', 18, 2)->default(0);
                $table->string('estado', 30)->default('APLICADO');
                $table->unsignedBigInteger('cxc_documento_id')->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('ventas_recibos_detalle')) {
            Schema::create('ventas_recibos_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recibo_id');
                $table->unsignedBigInteger('factura_id');
                $table->unsignedBigInteger('cxc_documento_id')->nullable();
                $table->decimal('monto', 18, 2)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_recibos_detalle');
        Schema::dropIfExists('ventas_recibos');
        Schema::dropIfExists('ventas_facturas_detalle');
        Schema::dropIfExists('ventas_facturas');
        Schema::dropIfExists('ventas_cotizaciones_detalle');
        Schema::dropIfExists('ventas_cotizaciones');
    }
};
