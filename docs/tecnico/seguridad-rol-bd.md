# Remediación: rol de mínimo privilegio para eTax2 en PostgreSQL

> **Estado:** propuesto (no ejecutado). Evaluación 2026-06-28.
> **Severidad:** CRÍTICA.

## Problema

La app eTax2 se conecta con el rol PostgreSQL **`planilla`, que es SUPERUSUARIO**. El
servidor `50.16.106.252` es **una sola instancia PostgreSQL 17.10 con 15 bases**
(`etax2`, `etax2_dev`, `planilla`, `planilla2`, `chong`, `conytram`, `cooldragon`,
`express`, `mendoza`, `pfc`, `recluta`, `stap`, `winsoft`, `winsoft2`, `postgres`).
Por ser superusuario, las credenciales de eTax2 dan control total sobre **las 15
bases**, incluidas las de otros clientes.

Además el rol `planilla` está **compartido en vivo** por varias apps
(`pg_stat_activity`: bases `planilla` ~30 conns nómina, `etax2`/`etax2_dev` eTax2,
`chong` ~14 conns). Por eso **NO se toca el rol `planilla`**: se le da a eTax2 su
propio rol.

Único punto del código eTax2 que necesitaba superusuario: la **restauración de
respaldos** (`session_replication_role=replica`). Se resuelve con un `GRANT SET ON
PARAMETER` (PG15+), sin cambiar el código.

## Plan (mínimo privilegio, sin tocar `planilla`)

Ejecutar **como `postgres`** (superusuario). Probar en `etax2_dev` ANTES que en `etax2`.

```sql
-- 1) Rol dedicado (cluster-global, una sola vez). Contraseña fuerte -> .env (no git).
CREATE ROLE etax2_app LOGIN PASSWORD :'pwd' NOSUPERUSER NOCREATEDB NOCREATEROLE;

-- 2) Restaurar sin superusuario: delegar SOLO este parámetro (PG15+), sesión-local.
GRANT SET ON PARAMETER session_replication_role TO etax2_app;
```

Por **cada** base de eTax2 (`etax2_dev`, luego `etax2`), conectarse a ella y:

```sql
\c etax2_dev
GRANT CONNECT ON DATABASE etax2_dev TO etax2_app;

-- Reasignar a etax2_app todo lo que posee `planilla` en ESTA base
REASSIGN OWNED BY planilla TO etax2_app;

-- Reasignar lo que posee `postgres` en el esquema public (tablas/secuencias/funciones)
DO $$
DECLARE r record;
BEGIN
  FOR r IN SELECT format('ALTER TABLE public.%I OWNER TO etax2_app;', tablename) c
           FROM pg_tables WHERE schemaname='public' AND tableowner='postgres'
  LOOP EXECUTE r.c; END LOOP;
  FOR r IN SELECT format('ALTER SEQUENCE public.%I OWNER TO etax2_app;', sequencename) c
           FROM pg_sequences WHERE schemaname='public' AND sequenceowner='postgres'
  LOOP EXECUTE r.c; END LOOP;
  FOR r IN SELECT format('ALTER FUNCTION public.%I(%s) OWNER TO etax2_app;',
                         p.proname, pg_get_function_identity_arguments(p.oid)) c
           FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace
           WHERE n.nspname='public' AND pg_get_userbyid(p.proowner)='postgres'
  LOOP EXECUTE r.c; END LOOP;
END$$;

-- Red de seguridad por si quedara algún objeto ajeno + objetos futuros
GRANT USAGE ON SCHEMA public TO etax2_app;
GRANT ALL ON ALL TABLES    IN SCHEMA public TO etax2_app;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO etax2_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES    TO etax2_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO etax2_app;
```

**Opcional (endurecimiento, lo hace el DBA con cuidado):** quitar el `CONNECT` por
defecto de `PUBLIC` en las bases ajenas para que `etax2_app` no pueda ni conectarse
(`REVOKE CONNECT ON DATABASE <otra> FROM PUBLIC;`). Aun sin esto, `etax2_app` no
tiene grants sobre objetos de otras bases, así que no puede leerlas.

## Cambio en la app

`.env` (dev primero): `DB_USERNAME=etax2_app`, `DB_PASSWORD=***`. Luego
`php artisan config:clear` (o `config:cache` en prod) y `queue:restart`.
`planilla` sigue siendo superusuario y funcionando para nómina/chong durante la
transición; el corte es solo cambiar el `.env`.

## Verificación

1. `SELECT current_user, current_setting('is_superuser');` → `etax2_app | off`.
2. Smoke de la app (login, listados, postear un asiento) en dev.
3. e2e de restauración en dev (genera respaldo de una compañía y restaura en una
   nueva): debe pasar igual que con superusuario, gracias al `GRANT SET ON PARAMETER`.

## Reversión

Apuntar el `.env` de nuevo a `planilla`. El rol `etax2_app` y la reasignación de
propiedad no estorban (planilla, superusuario, sigue teniendo acceso total). Para
revertir propiedad: `REASSIGN OWNED BY etax2_app TO planilla` en cada base.

## Recomendación adicional

Aplicar el mismo patrón (rol no-superusuario por app) a **nómina (`planilla`)** y
**`chong`**, y evaluar separar bases de clientes en instancias/credenciales
distintas. Hoy una fuga de credenciales de cualquiera de esas apps compromete las 15
bases.
```
