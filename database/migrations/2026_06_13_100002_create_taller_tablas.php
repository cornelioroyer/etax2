<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taller_talleres')) {
            Schema::create('taller_talleres', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 200);
                $table->string('tipo_taller', 50)->default('general');
                $table->string('direccion', 500)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('email', 200)->nullable();
                $table->unsignedBigInteger('responsable_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_sucursales')) {
            Schema::create('taller_sucursales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->string('codigo', 30);
                $table->string('nombre', 200);
                $table->string('direccion', 500)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('email', 200)->nullable();
                $table->unsignedBigInteger('almacen_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_areas')) {
            Schema::create('taller_areas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('sucursal_id')->nullable();
                $table->string('codigo', 30);
                $table->string('nombre', 200);
                $table->string('tipo_area', 50)->nullable();
                $table->integer('capacidad')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_tipos_equipo')) {
            Schema::create('taller_tipos_equipo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->string('codigo', 30);
                $table->string('nombre', 200);
                $table->string('categoria', 50)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_marcas')) {
            Schema::create('taller_marcas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->string('nombre', 200);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_modelos')) {
            Schema::create('taller_modelos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('marca_id');
                $table->unsignedBigInteger('tipo_equipo_id')->nullable();
                $table->string('nombre', 200);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_especialidades')) {
            Schema::create('taller_especialidades', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_sintomas')) {
            Schema::create('taller_sintomas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('tipo_equipo_id')->nullable();
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_servicios_estandar')) {
            Schema::create('taller_servicios_estandar', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('tipo_equipo_id')->nullable();
                $table->unsignedBigInteger('especialidad_id')->nullable();
                $table->string('codigo', 50)->nullable();
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->integer('tiempo_estimado_min')->default(0);
                $table->decimal('precio_base', 18, 2)->default(0);
                $table->decimal('costo_base', 18, 2)->default(0);
                $table->unsignedBigInteger('cuenta_ingreso_id')->nullable();
                $table->unsignedBigInteger('impuesto_id')->nullable();
                $table->boolean('requiere_aprobacion')->default(false);
                $table->integer('garantia_dias')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_checklist')) {
            Schema::create('taller_checklist', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('tipo_equipo_id')->nullable();
                $table->string('nombre', 200);
                $table->string('tipo', 50)->default('recepcion');
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_checklist_detalle')) {
            Schema::create('taller_checklist_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('checklist_id');
                $table->string('pregunta', 500);
                $table->string('tipo_respuesta', 50)->default('si_no');
                $table->boolean('requerido')->default(false);
                $table->integer('orden')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('taller_tecnicos')) {
            Schema::create('taller_tecnicos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('contacto_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('codigo', 30)->nullable();
                $table->string('nombre_publico', 200);
                $table->string('tipo_tecnico', 50)->default('interno');
                $table->decimal('costo_hora', 18, 2)->nullable();
                $table->decimal('precio_hora', 18, 2)->nullable();
                $table->decimal('capacidad_horas_dia', 8, 2)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_tecnico_especialidades')) {
            Schema::create('taller_tecnico_especialidades', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tecnico_id');
                $table->unsignedBigInteger('especialidad_id');
                $table->string('nivel', 50)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('taller_configuracion')) {
            Schema::create('taller_configuracion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id')->unique();
                $table->boolean('generar_cxc_al_facturar')->default(true);
                $table->boolean('emitir_factura_electronica')->default(false);
                $table->boolean('permitir_entrega_sin_pago')->default(false);
                $table->boolean('permitir_facturar_sin_calidad')->default(false);
                $table->unsignedBigInteger('fel_configuracion_id')->nullable();
                $table->unsignedBigInteger('cuenta_cxc_id')->nullable();
                $table->unsignedBigInteger('cuenta_ingreso_servicio_id')->nullable();
                $table->unsignedBigInteger('cuenta_ingreso_repuestos_id')->nullable();
                $table->unsignedBigInteger('cuenta_costo_repuestos_id')->nullable();
                $table->unsignedBigInteger('cuenta_inventario_id')->nullable();
                $table->unsignedBigInteger('cuenta_garantia_id')->nullable();
                $table->unsignedBigInteger('cuenta_banco_default_id')->nullable();
                $table->unsignedBigInteger('bco_cuenta_default_id')->nullable();
                $table->integer('dias_garantia_default')->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_equipos')) {
            Schema::create('taller_equipos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('tipo_equipo_id')->nullable();
                $table->unsignedBigInteger('marca_id')->nullable();
                $table->unsignedBigInteger('modelo_id')->nullable();
                $table->string('codigo', 50)->nullable();
                $table->string('nombre', 200);
                $table->string('numero_serie', 100)->nullable();
                $table->string('placa', 50)->nullable();
                $table->string('vin', 100)->nullable();
                $table->integer('anio')->nullable();
                $table->string('color', 100)->nullable();
                $table->text('descripcion')->nullable();
                $table->jsonb('especificaciones')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('taller_ordenes')) {
            Schema::create('taller_ordenes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('taller_id');
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('sucursal_id')->nullable();
                $table->unsignedBigInteger('area_actual_id')->nullable();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('contacto_entrega_id')->nullable();
                $table->unsignedBigInteger('equipo_id');
                $table->unsignedBigInteger('presupuesto_id')->nullable();
                $table->unsignedBigInteger('cita_id')->nullable();
                $table->string('numero', 50);
                $table->date('fecha_recepcion');
                $table->date('fecha_prometida')->nullable();
                $table->datetime('fecha_inicio')->nullable();
                $table->datetime('fecha_fin')->nullable();
                $table->datetime('fecha_entrega')->nullable();
                $table->string('prioridad', 30)->default('normal');
                $table->string('tipo_servicio', 100)->nullable();
                $table->string('origen', 100)->nullable();
                $table->text('sintomas_reportados')->nullable();
                $table->text('observacion_recepcion')->nullable();
                $table->decimal('medidor_valor', 18, 2)->nullable();
                $table->string('medidor_unidad', 50)->nullable();
                $table->string('estado', 50)->default('recibida');
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('descuento', 18, 2)->default(0);
                $table->decimal('impuesto', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->decimal('saldo', 18, 2)->default(0);
                $table->integer('garantia_dias')->default(0);
                $table->unsignedBigInteger('cxc_documento_id')->nullable();
                $table->unsignedBigInteger('fel_documento_id')->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_ordenes');
        Schema::dropIfExists('taller_equipos');
        Schema::dropIfExists('taller_configuracion');
        Schema::dropIfExists('taller_tecnico_especialidades');
        Schema::dropIfExists('taller_tecnicos');
        Schema::dropIfExists('taller_checklist_detalle');
        Schema::dropIfExists('taller_checklist');
        Schema::dropIfExists('taller_servicios_estandar');
        Schema::dropIfExists('taller_sintomas');
        Schema::dropIfExists('taller_especialidades');
        Schema::dropIfExists('taller_modelos');
        Schema::dropIfExists('taller_marcas');
        Schema::dropIfExists('taller_tipos_equipo');
        Schema::dropIfExists('taller_areas');
        Schema::dropIfExists('taller_sucursales');
        Schema::dropIfExists('taller_talleres');
    }
};
