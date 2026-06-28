<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Actualiza el CHECK de compras_ordenes.estado para incluir TODOS los estados
 * que usa el modelo CompraOrden: faltaban RECIBIDA_PARCIAL (recepción parcial,
 * ya en uso) y PARCIALMENTE_FACTURADA (facturación parcial 1:N). Sin esto, el
 * flujo de recepciones/facturas parciales viola el constraint.
 */
return new class extends Migration
{
    private array $estados = [
        'BORRADOR', 'APROBADA', 'RECIBIDA_PARCIAL', 'RECIBIDA',
        'PARCIALMENTE_FACTURADA', 'FACTURADA', 'CERRADA', 'ANULADA',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        $lista = "'".implode("','", $this->estados)."'";
        DB::statement('ALTER TABLE compras_ordenes DROP CONSTRAINT IF EXISTS compras_ordenes_estado_check');
        DB::statement("ALTER TABLE compras_ordenes ADD CONSTRAINT compras_ordenes_estado_check CHECK (estado::text = ANY (ARRAY[{$lista}]::text[]))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        // Revierte al set previo (sin los estados parciales).
        DB::statement('ALTER TABLE compras_ordenes DROP CONSTRAINT IF EXISTS compras_ordenes_estado_check');
        DB::statement("ALTER TABLE compras_ordenes ADD CONSTRAINT compras_ordenes_estado_check CHECK (estado::text = ANY (ARRAY['BORRADOR','APROBADA','RECIBIDA','FACTURADA','CERRADA','ANULADA']::text[]))");
    }
};
