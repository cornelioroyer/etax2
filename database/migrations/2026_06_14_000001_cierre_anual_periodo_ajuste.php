<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre anual con período de ajuste (mes 13).
 *
 * El trigger fn_validar_asiento_posteado derivaba SIEMPRE el período de la
 * fecha del asiento, lo que impedía postear en un período de ajuste
 * (mes 13) usado para el asiento de cierre del ejercicio. Se ajusta para
 * que, si el asiento ya trae asignado un período de ajuste (mes > 12) de la
 * compañía, se respete; en cualquier otro caso la derivación por fecha sigue
 * igual pero restringida a los períodos operativos (mes <= 12), de forma
 * determinista. Cambio retrocompatible.
 *
 * Solo aplica en PostgreSQL (dev/prod). En tests (SQLite) es no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Permitir el mes 13 (período de ajuste / cierre anual).
        DB::unprepared(<<<'SQL'
        ALTER TABLE public.cgl_periodos DROP CONSTRAINT IF EXISTS cgl_periodos_mes_check;
        ALTER TABLE public.cgl_periodos ADD CONSTRAINT cgl_periodos_mes_check CHECK (mes >= 1 AND mes <= 13);
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION public.fn_validar_asiento_posteado()
         RETURNS trigger
         LANGUAGE plpgsql
        AS $function$
        DECLARE
            v_debito         NUMERIC(18,2);
            v_credito        NUMERIC(18,2);
            v_periodo_id     BIGINT;
            v_periodo_estado VARCHAR(30);
        BEGIN
            -- Si el asiento ya trae un período de AJUSTE (mes>12) de la compañía,
            -- se respeta (cierre anual). En otro caso, se deriva de la fecha entre
            -- los períodos operativos (mes<=12), de forma determinista.
            v_periodo_id := NULL;
            IF NEW.periodo_id IS NOT NULL THEN
                SELECT id, estado INTO v_periodo_id, v_periodo_estado
                  FROM public.cgl_periodos
                 WHERE id = NEW.periodo_id
                   AND compania_id = NEW.compania_id
                   AND mes > 12;
            END IF;

            IF v_periodo_id IS NULL THEN
                SELECT id, estado INTO v_periodo_id, v_periodo_estado
                  FROM public.cgl_periodos
                 WHERE compania_id = NEW.compania_id
                   AND mes <= 12
                   AND NEW.fecha BETWEEN fecha_inicio AND fecha_fin
                 ORDER BY mes
                 LIMIT 1;
            END IF;

            NEW.periodo_id := v_periodo_id;

            IF NEW.estado = 'POSTEADO' AND (TG_OP = 'INSERT' OR OLD.estado IS DISTINCT FROM 'POSTEADO') THEN
                -- control de periodo
                IF v_periodo_id IS NULL THEN
                    RAISE EXCEPTION 'Asiento %: no existe periodo contable para la fecha %', NEW.numero, NEW.fecha;
                END IF;
                IF v_periodo_estado <> 'ABIERTO' THEN
                    RAISE EXCEPTION 'Asiento %: el periodo de la fecha % esta % — no se puede postear', NEW.numero, NEW.fecha, v_periodo_estado;
                END IF;

                -- control de cuadre
                SELECT COALESCE(SUM(debito), 0), COALESCE(SUM(credito), 0)
                  INTO v_debito, v_credito
                  FROM public.cgl_asientos_detalle
                 WHERE asiento_id = NEW.id;

                IF v_debito <> v_credito THEN
                    RAISE EXCEPTION 'Asiento % descuadrado: debito % <> credito %', NEW.numero, v_debito, v_credito;
                END IF;
                IF v_debito = 0 THEN
                    RAISE EXCEPTION 'Asiento % sin lineas de detalle', NEW.numero;
                END IF;
                IF NEW.total_debito <> v_debito OR NEW.total_credito <> v_credito THEN
                    RAISE EXCEPTION 'Asiento %: totales de cabecera no coinciden con el detalle', NEW.numero;
                END IF;

                NEW.fecha_posteo := COALESCE(NEW.fecha_posteo, now());
            END IF;
            RETURN NEW;
        END;
        $function$
        SQL);
    }

    public function down(): void
    {
        // El trigger anterior se restaura aplicando la versión previa del
        // esquema maestro; no se revierte automáticamente.
    }
};
