<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantillas de FACTURAS de proveedor recurrentes (alquiler, servicios fijos,
     * cuotas). NO son documentos: son moldes que el generador convierte en una
     * cxp_documentos (tipo FACTURA, estado BORRADOR) en cada vencimiento. El
     * borrador entra al ciclo de vida normal de CxP: el contador lo revisa y
     * pulsa "Contabilizar" (Dr contrapartida + Dr ITBMS / Cr CxP, submayor del
     * proveedor, asiento). Tablas nuevas + una columna aditiva en cxp_documentos.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cxp_recurrentes')) {
            Schema::create('cxp_recurrentes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('proveedor_id');
                $table->string('nombre', 200);
                $table->string('referencia', 100)->nullable();
                // SEMANAL, QUINCENAL, MENSUAL, BIMESTRAL, TRIMESTRAL, SEMESTRAL, ANUAL
                $table->string('frecuencia', 20);
                $table->date('fecha_inicio');
                $table->date('fecha_fin')->nullable();
                $table->integer('dias_credito')->default(0);
                $table->integer('ocurrencias_max')->nullable();
                $table->integer('ocurrencias_generadas')->default(0);
                $table->date('proxima_fecha');
                $table->date('ultima_generacion')->nullable();
                // ACTIVA, PAUSADA, FINALIZADA
                $table->string('estado', 20)->default('ACTIVA');
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('impuesto', 18, 2)->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->index(['compania_id', 'estado']);
                $table->index(['estado', 'proxima_fecha']);
            });
        }

        if (! Schema::hasTable('cxp_recurrentes_detalle')) {
            Schema::create('cxp_recurrentes_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recurrente_id');
                $table->integer('linea');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('descripcion', 500);
                $table->decimal('cantidad', 18, 4)->default(1);
                $table->decimal('precio_unitario', 18, 4)->default(0);
                $table->integer('tasa_itbms')->default(0);
                $table->unsignedBigInteger('cuenta_id');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['recurrente_id', 'linea']);
            });
        }

        // Trazabilidad + idempotencia: a qué plantilla recurrente pertenece el
        // documento generado (null = documento normal capturado a mano).
        if (Schema::hasTable('cxp_documentos') && ! Schema::hasColumn('cxp_documentos', 'recurrente_id')) {
            Schema::table('cxp_documentos', function (Blueprint $table) {
                $table->unsignedBigInteger('recurrente_id')->nullable()->after('asiento_id');
                $table->index(['recurrente_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cxp_documentos') && Schema::hasColumn('cxp_documentos', 'recurrente_id')) {
            Schema::table('cxp_documentos', function (Blueprint $table) {
                $table->dropIndex(['recurrente_id']);
                $table->dropColumn('recurrente_id');
            });
        }

        Schema::dropIfExists('cxp_recurrentes_detalle');
        Schema::dropIfExists('cxp_recurrentes');
    }
};
