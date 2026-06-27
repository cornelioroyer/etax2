<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A4 (auditoría de integridad contable) — defensa en profundidad: impedir, a
 * nivel de BD, que un asiento POSTEADO pase a ANULADO cuando su período está
 * cerrado.
 *
 * Por qué importa: la transición POSTEADO→ANULADO dispara `fn_actualizar_saldos`
 * (AFTER UPDATE), que revierte los saldos. Si el período está cerrado, esa
 * reversión mutaría un período cerrado. La app ya lo valida en
 * `AsientoAutomatico::anular()` (fuente única de toda anulación de módulo) y en
 * `AsientoController` (A1), pero un UPDATE por SQL directo o por una ruta que no
 * pase por esa guarda lo lograría igual. Este trigger es el backstop final.
 *
 * Se extiende la función existente `fn_proteger_asiento_posteado()` (BEFORE
 * DELETE OR UPDATE en cgl_asientos) sin alterar su comportamiento previo: solo
 * se agrega la verificación de período abierto en la transición a ANULADO.
 *
 * Semántica espejo de la guarda de app: solo bloquea si EXISTE el período y NO
 * está 'ABIERTO' (si periodo_id es NULL o no se halla, no bloquea). Usa
 * OLD.periodo_id (el período en que vive el asiento posteado).
 *
 * Idempotente (CREATE OR REPLACE). Solo PostgreSQL; no-op en SQLite (tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // tests SQLite: no-op (los triggers no existen ahí)
        }

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION public.fn_proteger_asiento_posteado()
         RETURNS trigger
         LANGUAGE plpgsql
        AS $function$
        DECLARE
            v_periodo_estado VARCHAR(30);
        BEGIN
            IF TG_OP = 'DELETE' THEN
                IF OLD.estado = 'POSTEADO' THEN
                    RAISE EXCEPTION 'Asiento %: un asiento POSTEADO no se puede eliminar; debe anularse o revertirse', OLD.numero;
                END IF;
                RETURN OLD;
            END IF;
            IF OLD.estado = 'POSTEADO' THEN
                IF NEW.estado NOT IN ('POSTEADO','ANULADO') THEN
                    RAISE EXCEPTION 'Asiento %: un asiento POSTEADO solo puede pasar a ANULADO', OLD.numero;
                END IF;
                -- A4: no anular un asiento cuyo período esté cerrado (mutaría saldos
                -- de un período cerrado vía fn_actualizar_saldos). Espejo de la guarda
                -- de app: solo bloquea si el período existe y NO está ABIERTO.
                IF NEW.estado = 'ANULADO' THEN
                    SELECT estado INTO v_periodo_estado
                      FROM public.cgl_periodos
                     WHERE id = OLD.periodo_id;
                    IF FOUND AND v_periodo_estado IS DISTINCT FROM 'ABIERTO' THEN
                        RAISE EXCEPTION 'Asiento %: el periodo contable esta % — no se puede anular un asiento en un periodo cerrado', OLD.numero, v_periodo_estado;
                    END IF;
                END IF;
                IF NEW.fecha         IS DISTINCT FROM OLD.fecha
                OR NEW.numero        IS DISTINCT FROM OLD.numero
                OR NEW.compania_id   IS DISTINCT FROM OLD.compania_id
                OR NEW.diario_id     IS DISTINCT FROM OLD.diario_id
                OR NEW.total_debito  IS DISTINCT FROM OLD.total_debito
                OR NEW.total_credito IS DISTINCT FROM OLD.total_credito THEN
                    RAISE EXCEPTION 'Asiento %: los datos de un asiento POSTEADO son inmutables', OLD.numero;
                END IF;
            END IF;
            RETURN NEW;
        END;
        $function$;
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Restaura la definición previa (sin la verificación de período en ANULADO).
        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION public.fn_proteger_asiento_posteado()
         RETURNS trigger
         LANGUAGE plpgsql
        AS $function$
        BEGIN
            IF TG_OP = 'DELETE' THEN
                IF OLD.estado = 'POSTEADO' THEN
                    RAISE EXCEPTION 'Asiento %: un asiento POSTEADO no se puede eliminar; debe anularse o revertirse', OLD.numero;
                END IF;
                RETURN OLD;
            END IF;
            IF OLD.estado = 'POSTEADO' THEN
                IF NEW.estado NOT IN ('POSTEADO','ANULADO') THEN
                    RAISE EXCEPTION 'Asiento %: un asiento POSTEADO solo puede pasar a ANULADO', OLD.numero;
                END IF;
                IF NEW.fecha         IS DISTINCT FROM OLD.fecha
                OR NEW.numero        IS DISTINCT FROM OLD.numero
                OR NEW.compania_id   IS DISTINCT FROM OLD.compania_id
                OR NEW.diario_id     IS DISTINCT FROM OLD.diario_id
                OR NEW.total_debito  IS DISTINCT FROM OLD.total_debito
                OR NEW.total_credito IS DISTINCT FROM OLD.total_credito THEN
                    RAISE EXCEPTION 'Asiento %: los datos de un asiento POSTEADO son inmutables', OLD.numero;
                END IF;
            END IF;
            RETURN NEW;
        END;
        $function$;
        SQL);
    }
};
