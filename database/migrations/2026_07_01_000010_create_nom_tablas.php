<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de NÓMINA (nom_*) — sistema de planilla nativo de etax2 para
     * clientes nuevos. Hereda los CONCEPTOS del sistema planilla legacy
     * (catálogo con signo: 03=Salario, 102=CSS, 103=SE, 104=ISR, 108=Vacaciones,
     * 109=XIII...) pero con las reglas parametrizadas en tablas, NUNCA
     * hardcodeadas por compañía. El sistema planilla legacy sigue operando
     * aparte para sus compañías existentes; este módulo no lo toca.
     *
     * v1: maestros + planilla regular (horas manuales, sin reloj) con
     * CSS/SE/ISR y asiento contable vía AsientoAutomatico.
     */
    public function up(): void
    {
        if (! Schema::hasTable('nom_departamentos')) {
            Schema::create('nom_departamentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 20);
                $table->string('nombre', 200);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['compania_id', 'codigo']);
            });
        }

        if (! Schema::hasTable('nom_cargos')) {
            Schema::create('nom_cargos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 20);
                $table->string('nombre', 200);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['compania_id', 'codigo']);
            });
        }

        // Catálogo de conceptos por compañía. Misma numeración que el sistema
        // planilla legacy para que contadores y reportes hablen el mismo idioma.
        // El monto en nom_movimientos SIEMPRE es positivo; el efecto lo da el tipo.
        if (! Schema::hasTable('nom_conceptos')) {
            Schema::create('nom_conceptos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 10);
                $table->string('descripcion', 200);
                // INGRESO (suma al bruto), DEDUCCION (resta del neto),
                // PATRONAL (costo del empleador, no toca el neto)
                $table->string('tipo', 20);
                // Cálculo: MANUAL (novedad capturada), SALARIO (lo genera el motor),
                // PORCENTAJE (porcentaje sobre base gravable: CSS/SE), ISR (tabla)
                $table->string('calculo', 20)->default('MANUAL');
                $table->decimal('porcentaje', 8, 4)->nullable();
                // ¿Este ingreso integra la base gravable de CSS/SE e ISR?
                $table->boolean('gravable_css')->default(true);
                $table->boolean('gravable_isr')->default(true);
                // ¿Acumula para XIII / vacaciones? (fases siguientes lo consumen)
                $table->boolean('acumula_xiii')->default(true);
                $table->boolean('acumula_vacaciones')->default(true);
                // Contabilización: gasto (ingresos/patronal) o pasivo (deducciones).
                // Null = usa la cuenta default del módulo (GASTO_SALARIOS, etc.).
                $table->unsignedBigInteger('cuenta_gasto_id')->nullable();
                $table->unsignedBigInteger('cuenta_pasivo_id')->nullable();
                $table->boolean('imprime_en_recibo')->default(true);
                $table->integer('orden_impresion')->default(0);
                // Conceptos del motor (03, 102, 103, 104...) no editables/borrables
                $table->boolean('de_sistema')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['compania_id', 'codigo']);
            });
        }

        if (! Schema::hasTable('nom_empleados')) {
            Schema::create('nom_empleados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 20);
                $table->string('nombre', 150);
                $table->string('apellido', 150);
                $table->string('cedula', 30)->nullable();
                $table->string('seguro_social', 30)->nullable();
                $table->date('fecha_nacimiento')->nullable();
                $table->string('sexo', 1)->nullable();          // M / F
                $table->string('estado_civil', 20)->nullable();
                $table->string('email', 200)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('direccion', 500)->nullable();
                $table->date('fecha_inicio');
                $table->date('fecha_terminacion')->nullable();
                // FIJO (salario mensual) / POR_HORA (tasa x horas capturadas)
                $table->string('tipo_salario', 20)->default('FIJO');
                $table->decimal('salario_mensual', 18, 2)->default(0);
                $table->decimal('tasa_hora', 18, 4)->default(0);
                $table->decimal('horas_semanales', 8, 2)->default(48);
                // SEMANAL / QUINCENAL / MENSUAL — a qué calendario de pago pertenece
                $table->string('tipo_planilla', 20)->default('QUINCENAL');
                $table->string('forma_pago', 20)->default('TRANSFERENCIA'); // EFECTIVO/CHEQUE/TRANSFERENCIA
                $table->string('banco', 100)->nullable();
                $table->string('cuenta_bancaria', 50)->nullable();
                $table->string('tipo_cuenta', 20)->nullable();  // AHORRO / CORRIENTE
                $table->unsignedBigInteger('departamento_id')->nullable();
                $table->unsignedBigInteger('cargo_id')->nullable();
                $table->integer('dependientes')->default(0);
                // ACTIVO / VACACIONES / LICENCIA / INACTIVO / TERMINADO
                $table->string('status', 20)->default('ACTIVO');
                $table->text('observacion')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['compania_id', 'codigo']);
                $table->index(['compania_id', 'status']);
                $table->index(['compania_id', 'cedula']);
            });
        }

        // Calendario de períodos de pago por compañía y tipo de planilla.
        if (! Schema::hasTable('nom_periodos')) {
            Schema::create('nom_periodos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('tipo_planilla', 20);            // SEMANAL/QUINCENAL/MENSUAL
                $table->integer('anio');
                $table->integer('numero');                       // correlativo dentro del año
                $table->date('desde');
                $table->date('hasta');
                $table->date('fecha_pago');
                $table->string('estado', 20)->default('ABIERTO'); // ABIERTO / CERRADO
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['compania_id', 'tipo_planilla', 'anio', 'numero']);
                $table->index(['compania_id', 'estado']);
            });
        }

        // La corrida de planilla: documento con ciclo de estados. Nada se borra:
        // BORRADOR -> PROCESADA -> CONTABILIZADA -> ANULADA (reverso del asiento).
        if (! Schema::hasTable('nom_planillas')) {
            Schema::create('nom_planillas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('periodo_id');
                $table->string('numero', 30);                    // NP-000001
                // REGULAR hoy; XIII / VACACIONES / LIQUIDACION en fases siguientes
                $table->string('tipo', 20)->default('REGULAR');
                $table->string('descripcion', 300)->nullable();
                $table->string('estado', 20)->default('BORRADOR');
                $table->date('fecha');                           // fecha contable del asiento
                $table->decimal('total_ingresos', 18, 2)->default(0);
                $table->decimal('total_deducciones', 18, 2)->default(0);
                $table->decimal('total_neto', 18, 2)->default(0);
                $table->decimal('total_patronal', 18, 2)->default(0);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['compania_id', 'numero']);
                $table->index(['compania_id', 'estado']);
                $table->index(['periodo_id']);
            });
        }

        // Las líneas de la corrida: empleado x concepto x monto (el modelo
        // pla_dinero del legacy, probado con 8M de filas). Monto SIEMPRE positivo.
        if (! Schema::hasTable('nom_movimientos')) {
            Schema::create('nom_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('planilla_id');
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedBigInteger('concepto_id');
                $table->decimal('cantidad', 12, 4)->nullable();  // horas, si aplica
                $table->decimal('base', 18, 2)->nullable();      // base de cálculo (auditoría)
                $table->decimal('monto', 18, 2);
                $table->string('descripcion', 300)->nullable();
                $table->timestamps();

                $table->index(['planilla_id', 'empleado_id']);
                $table->index(['compania_id', 'concepto_id']);
                $table->index(['empleado_id']);
            });
        }

        // Novedades: ingresos/deducciones por empleado que alimentan la corrida.
        // FIJA = aplica en cada período mientras esté vigente (cuota de préstamo,
        // bono fijo). VARIABLE = aplica solo al período indicado (horas extra,
        // ausencia, comisión del mes). Aquí entran las "horas manuales" de v1.
        if (! Schema::hasTable('nom_novedades')) {
            Schema::create('nom_novedades', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedBigInteger('concepto_id');
                $table->string('tipo_registro', 20);             // FIJA / VARIABLE
                $table->unsignedBigInteger('periodo_id')->nullable(); // requerido si VARIABLE
                $table->decimal('cantidad', 12, 4)->nullable();  // horas, si aplica
                $table->decimal('monto', 18, 2)->default(0);
                $table->date('vigente_desde')->nullable();       // para FIJA
                $table->date('vigente_hasta')->nullable();
                $table->string('descripcion', 300)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->index(['compania_id', 'empleado_id', 'activo']);
                $table->index(['periodo_id']);
            });
        }

        // Parámetros legales NACIONALES con vigencia (CSS, SE, riesgo profesional
        // default). Globales — no llevan compañía. Fuente única del motor.
        if (! Schema::hasTable('nom_parametros_legales')) {
            Schema::create('nom_parametros_legales', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 50);                     // CSS_EMPLEADO, CSS_PATRONO, SE_EMPLEADO, SE_PATRONO...
                $table->decimal('valor', 12, 6);
                $table->date('vigente_desde');
                $table->string('descripcion', 300)->nullable();
                $table->timestamps();

                $table->unique(['clave', 'vigente_desde']);
            });
        }

        // Tramos del ISR asalariados de Panamá, con vigencia.
        if (! Schema::hasTable('nom_isr_tramos')) {
            Schema::create('nom_isr_tramos', function (Blueprint $table) {
                $table->id();
                $table->date('vigente_desde');
                $table->decimal('desde', 18, 2);                 // renta anual gravable
                $table->decimal('hasta', 18, 2)->nullable();     // null = sin tope
                $table->decimal('tasa', 8, 4);                   // % del tramo
                $table->decimal('cuota_fija', 18, 2)->default(0); // impuesto acumulado de tramos previos
                $table->timestamps();

                $table->index(['vigente_desde']);
            });
        }

        // Configuración de nómina por compañía (lo único parametrizable por
        // compañía va AQUÍ, jamás en código).
        if (! Schema::hasTable('nom_configuracion')) {
            Schema::create('nom_configuracion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id')->unique();
                // Prima de riesgo profesional del empleador (varía por actividad)
                $table->decimal('riesgo_profesional', 8, 4)->default(0.98);
                $table->decimal('horas_semanales_default', 8, 2)->default(48);
                $table->string('tipo_planilla_default', 20)->default('QUINCENAL');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nom_configuracion');
        Schema::dropIfExists('nom_isr_tramos');
        Schema::dropIfExists('nom_parametros_legales');
        Schema::dropIfExists('nom_novedades');
        Schema::dropIfExists('nom_movimientos');
        Schema::dropIfExists('nom_planillas');
        Schema::dropIfExists('nom_periodos');
        Schema::dropIfExists('nom_empleados');
        Schema::dropIfExists('nom_conceptos');
        Schema::dropIfExists('nom_cargos');
        Schema::dropIfExists('nom_departamentos');
    }
};
