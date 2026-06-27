<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A3 (auditoría de integridad contable): versiona la protección de `cgl_saldos`
 * que hasta ahora solo vivía en el esquema maestro de PostgreSQL (fuera del
 * repo). Garantiza, de forma IDEMPOTENTE, que cualquier entorno PostgreSQL
 * tenga:
 *   - UNIQUE `uq_cgl_saldos` (compania_id, periodo_id, cuenta_id, contacto_id,
 *     centro_costo_id) con NULLS NOT DISTINCT (PG15+), para que NO se dupliquen
 *     saldos cuando contacto_id/centro_costo_id son NULL (sin NULLS NOT DISTINCT
 *     Postgres trataría cada NULL como distinto y permitiría duplicados que
 *     inflarían los estados financieros).
 *   - Índices de apoyo por columna de FK.
 *   - Las 5 FKs de cgl_saldos.
 *
 * dev y prod YA tienen estos objetos (aplicados directo en el esquema maestro);
 * por eso todo va con guardas "si no existe" y la migración es no-op donde ya
 * están. En SQLite (tests) es no-op completo.
 *
 * Defensa: si existieran filas duplicadas (UNIQUE ausente en algún entorno), la
 * migración ABORTA con un mensaje accionable en vez de fallar de forma críptica,
 * para que se depuren los duplicados antes de imponer el UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // tests SQLite: no-op
        }

        // 1) Defensa anti-duplicados: si el UNIQUE aún no existe y hay grupos
        //    repetidos, abortar con un mensaje accionable.
        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE
            v_dups BIGINT;
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM pg_constraint
                WHERE conname = 'uq_cgl_saldos' AND conrelid = 'public.cgl_saldos'::regclass
            ) THEN
                SELECT COUNT(*) INTO v_dups FROM (
                    SELECT 1
                      FROM public.cgl_saldos
                     GROUP BY compania_id, periodo_id, cuenta_id, contacto_id, centro_costo_id
                    HAVING COUNT(*) > 1
                ) d;
                IF v_dups > 0 THEN
                    RAISE EXCEPTION 'cgl_saldos tiene % grupo(s) de filas duplicadas; depuralas antes de aplicar uq_cgl_saldos.', v_dups;
                END IF;
            END IF;
        END $$;
        SQL);

        // 2) UNIQUE idempotente (NULLS NOT DISTINCT, PG15+).
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM pg_constraint
                WHERE conname = 'uq_cgl_saldos' AND conrelid = 'public.cgl_saldos'::regclass
            ) THEN
                ALTER TABLE public.cgl_saldos
                    ADD CONSTRAINT uq_cgl_saldos
                    UNIQUE NULLS NOT DISTINCT
                    (compania_id, periodo_id, cuenta_id, contacto_id, centro_costo_id);
            END IF;
        END $$;
        SQL);

        // 3) Índices de apoyo por FK (idempotentes).
        DB::unprepared(<<<'SQL'
        CREATE INDEX IF NOT EXISTS idx_cgl_saldos_periodo_id      ON public.cgl_saldos (periodo_id);
        CREATE INDEX IF NOT EXISTS idx_cgl_saldos_cuenta_id       ON public.cgl_saldos (cuenta_id);
        CREATE INDEX IF NOT EXISTS idx_cgl_saldos_contacto_id     ON public.cgl_saldos (contacto_id);
        CREATE INDEX IF NOT EXISTS idx_cgl_saldos_centro_costo_id ON public.cgl_saldos (centro_costo_id);
        SQL);

        // 4) FKs idempotentes (mismos nombres/acciones que el esquema maestro).
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='cgl_saldos_compania_id_fkey' AND conrelid='public.cgl_saldos'::regclass) THEN
                ALTER TABLE public.cgl_saldos ADD CONSTRAINT cgl_saldos_compania_id_fkey FOREIGN KEY (compania_id) REFERENCES public.core_companias(id) ON DELETE CASCADE;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='cgl_saldos_periodo_id_fkey' AND conrelid='public.cgl_saldos'::regclass) THEN
                ALTER TABLE public.cgl_saldos ADD CONSTRAINT cgl_saldos_periodo_id_fkey FOREIGN KEY (periodo_id) REFERENCES public.cgl_periodos(id) ON DELETE CASCADE;
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='cgl_saldos_cuenta_id_fkey' AND conrelid='public.cgl_saldos'::regclass) THEN
                ALTER TABLE public.cgl_saldos ADD CONSTRAINT cgl_saldos_cuenta_id_fkey FOREIGN KEY (cuenta_id) REFERENCES public.cgl_cuentas(id);
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='cgl_saldos_contacto_id_fkey' AND conrelid='public.cgl_saldos'::regclass) THEN
                ALTER TABLE public.cgl_saldos ADD CONSTRAINT cgl_saldos_contacto_id_fkey FOREIGN KEY (contacto_id) REFERENCES public.contact_contactos(id);
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='cgl_saldos_centro_costo_id_fkey' AND conrelid='public.cgl_saldos'::regclass) THEN
                ALTER TABLE public.cgl_saldos ADD CONSTRAINT cgl_saldos_centro_costo_id_fkey FOREIGN KEY (centro_costo_id) REFERENCES public.core_centros_costos(id);
            END IF;
        END $$;
        SQL);
    }

    public function down(): void
    {
        // Intencionalmente NO se revierte: son garantías de integridad contable.
        // Quitar el UNIQUE/FKs en un rollback dejaría cgl_saldos expuesto a
        // duplicados. Si alguna vez hiciera falta, hágase manualmente y con criterio.
    }
};
