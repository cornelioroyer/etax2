# Registro de decisiones (técnicas y contables)

Este documento recoge decisiones de diseño que se **dedujeron leyendo el código**.
No es un registro escrito por el equipo original; es una reconstrucción. Donde una
decisión tenga implicaciones contables o fiscales, se marca para validación.

---

## D-01 · Esquema maestro externo a las migraciones

**Qué se observó.** El repositorio tiene ~29 migraciones pero ~180 modelos. Tablas
centrales (`cgl_cuentas`, `cgl_asientos`, `tax_impuestos`, buena parte de
inventario y de los módulos verticales) se crean con `if (! Schema::hasTable(...))`
y comentarios del tipo *"en dev/prod ya existen en el esquema maestro; en tests
(SQLite) hay que crearlas"*.

**Decisión inferida.** El esquema "real" de producción se mantiene por fuera de las
migraciones de Laravel (un *esquema maestro* SQL con triggers y funciones). Las
migraciones sirven sobre todo para que las **pruebas automáticas** (SQLite) tengan
las tablas, y para aplicar cambios incrementales.

**Implicación / riesgo.** Un `php artisan migrate` sobre una base vacía **no
reproduce el sistema completo**. La fuente de verdad del esquema no está
versionada en este repositorio.
⚠ **Pendiente de verificar:** ubicar y versionar el esquema maestro.

---

## D-02 · Control contable reforzado en la base de datos (PL/pgSQL)

**Qué se observó.** La función `fn_validar_asiento_posteado()` (en la migración
`2026_06_14_000001_cierre_anual_periodo_ajuste.php`) valida, del lado de PostgreSQL,
que un asiento POSTEADO: tenga período abierto, esté cuadrado (débito = crédito),
tenga detalle y que los totales de cabecera coincidan con el detalle.

**Decisión inferida.** El cuadre de partida doble y el control de período se
garantizan **en dos capas**: en PHP (`AsientoAutomatico`) y en la base de datos
(trigger). La base de datos es la última línea de defensa.

**Nota.** La **función** del trigger está en una migración, pero la sentencia
`CREATE TRIGGER` que la engancha a la tabla no aparece en el repositorio: vive en
el esquema maestro (ver D-01).

> ✅ **Verificado 2026-06-23 (dev y prod).** La función `fn_validar_asiento_posteado`
> existe y el trigger **`trg_cgl_asientos_posteo` sobre `cgl_asientos` está ACTIVO**
> (`pg_trigger.tgenabled='O'`) tanto en `etax2_dev` como en `etax2`. El control
> contable en base de datos está operativo. Sin acción pendiente.

---

## D-03 · Numeración serializada con *advisory locks*

**Qué se observó.** Los consecutivos (asientos `AS-NNNNNN`, órdenes de compra,
recepciones, cotizaciones, facturas, recibos, notas, activos) usan
`pg_advisory_xact_lock(...)` antes de calcular el siguiente número.

**Decisión inferida.** Como PostgreSQL no permite `FOR UPDATE` junto con funciones
de agregación (`max`), se usan *advisory locks* de transacción para evitar números
duplicados ante usuarios concurrentes.

**Implicación.** La numeración es correcta solo si estas operaciones se ejecutan
**dentro de una transacción** (el código así lo hace).

---

## D-04 · Numeración fiscal compartida en modo demo del PAC

**Qué se observó.** En `FelConfiguracion::siguienteNumeroFiscal()`, cuando la
configuración es la *demo* de The Factory HKA, **todas las compañías comparten un
único consecutivo** anclado en la compañía del sistema (ID 1). Con tokens propios,
cada compañía lleva su propio folio.

**Decisión inferida.** Las credenciales demo de HKA son compartidas entre
compañías e incluso entre dev y prod; si dos compañías usaran el mismo número, la
DGI rechazaría con "Documento duplicado". Por eso el contador demo es único y
monotónico (a prueba de borrados de compañías).

⚠ **Verificar con contador / operaciones:** en producción cada compañía debe usar
**sus propios tokens HKA**, no los demo. Ver `fiscal/firma-electronica.md`.

---

## D-05 · Tokens del PAC cifrados en base de datos

**Qué se observó.** En el modelo `FelConfiguracion`, `token_empresa` y
`token_password` usan el cast `encrypted` de Laravel.

**Decisión inferida.** Las credenciales del PAC se guardan cifradas en la columna,
no en texto plano ni en `.env`. El cifrado depende de `APP_KEY`.

**Implicación.** Si se pierde o cambia `APP_KEY`, los tokens guardados dejan de
poder descifrarse. ⚠ Custodiar `APP_KEY`.

---

## D-06 · ITBMS con TRES fuentes de verdad (inconsistencia)

> **Actualizado 2026-06-23 (revisión de código).** El registro original decía "dos
> fuentes". La revisión confirmó que en realidad hay **tres** representaciones
> independientes de las tasas ITBMS.

**Qué se observó.**
1. **Tabla `tax_impuestos`** (porcentaje por fila): la usan **Ventas** (factura,
   cotización), **Compras** (orden), **Items** y el importador FEL, vía
   `TaxImpuesto::itbmsGlobales()` y `base * porcentaje / 100`. Códigos de catálogo:
   `ITBMS_0 / ITBMS_7 / ITBMS_10 / ITBMS_15`.
2. **Arreglo fijo en código `[0, 7, 10, 15]`** (enteros %): lo usan **CxC y CxP**
   en `CxcFacturaController`, `CxpFacturaController`, `CxcNotaController`,
   `CxpNotaController` (`public const TASAS_ITBMS`), validado con `Rule::in`. Estos
   controladores **NO** leen `tax_impuestos`.
3. **Arreglo fijo en código `FelDocumentoBuilder::TASAS_ITBMS`** (factores por
   código DGI): `['00'=>0.00, '01'=>0.07, '02'=>0.10, '03'=>0.15]`, usado por FEL
   para armar el XML de la DGI (`FacturaFelController`).

**Riesgo.** Hoy los valores coinciden (0/7/10/15 %), por lo que no hay descuadre
numérico inmediato. Pero ante un cambio de tasa de la DGI habría que tocar **tres
lugares**, y CxC/CxP quedarían fuera de cualquier catálogo administrado en
`tax_impuestos`. Detallado en `fiscal/itbms.md` → "Inconsistencias detectadas".

**Decisión propuesta (en curso).** Centralizar la definición canónica en el modelo
`TaxImpuesto` (constantes `PORCENTAJES_ITBMS` y `DGI_CODIGO_POR_PORCENTAJE` +
helper `factorItbmsPorCodigoDgi()`), y que CxC/CxP y FEL **deriven** de ahí. La
tabla `tax_impuestos` debe mantenerse consistente con esa definición. Pendiente de
prueba en dev (`php artisan test`).

⚠ **Verificar con contador** que las cuatro tasas (0/7/10/15) y su asignación por
tipo de producto sean las correctas para Panamá.

---

## D-07 · Cierre anual con período de ajuste (mes 13)

**Qué se observó.** `CierreAnual` saldas las cuentas de resultado
(INGRESO/COSTO/GASTO) contra la cuenta por defecto `UTILIDADES_RETENIDAS`, y postea
el asiento en un **período de ajuste (mes 13)** para que los reportes operativos
(meses 1–12) no lo incluyan. La restricción de la tabla se amplió para permitir
`mes` hasta 13.

**Decisión inferida.** Separar el asiento de cierre del flujo operativo mensual,
manteniendo comparables los estados de resultado por mes.

⚠ **Verificar con contador:** que el tratamiento de utilidad/pérdida del ejercicio
contra utilidades retenidas se ajuste a la política contable de la empresa.

---

## D-08 · Tablas duplicadas / legado (inconsistencia de esquema)

**Qué se observó.** Existen pares de tablas que parecen cumplir el mismo rol:
- `companias` (migración `2026_06_09`) vs `core_companias` (migración `2026_06_10`).
  El modelo `Compania` usa **`core_companias`**.
- `zonas` vs `core_zonas`.
- `banco_cuentas` (migración `2026_06_12_000002`) vs `bco_cuentas` (migración
  `2026_06_20`, usada por el módulo de Bancos actual).

**Decisión inferida.** Hubo una primera versión de tablas que luego fue sustituida
por las `core_*` / `bco_*`. Las primeras quedaron como legado.

> **Actualizado 2026-06-23 (revisión de código).** Confirmado por modelo:
> `Compania` → `core_companias`, `Zona` → `core_zonas` (las tablas `companias` y
> `zonas` quedan como legado dormido, solo las crea su migración).
>
> **Hallazgo nuevo — el par de bancos NO es legado dormido, está activo:** existen
> **dos módulos bancarios cableados en rutas a la vez** bajo el mismo permiso
> `bancos.ver`:
> - `bancos.*` (`routes/web.php` ~286-292) → **viejo** `BancoCuentaController`,
>   tabla `banco_cuentas`, con `tests/Feature/BancoTest.php` dependiente.
> - `bco/*` (`routes/web.php` ~424+) → **nuevo** módulo (`BcoCuentaController`,
>   `BcoMovimientoController`, conciliación, transferencias), tabla `bco_cuentas`.
>
> Riesgo: un usuario podría crear cuentas en `banco_cuentas` (módulo viejo) que el
> módulo nuevo `bco_*` no ve. Antes de retirar el viejo hay que verificar en
> **dev/prod** si `banco_cuentas` tiene filas/movimientos reales.

> ✅ **Verificado y corregido 2026-06-23.** Conteo en BD:
> | tabla | etax2_dev | etax2 (prod) |
> |---|---|---|
> | `banco_cuentas` (legado) | **0** | **0** |
> | `bco_cuentas` (activa) | 5 | 4 |
> | `companias` (legado) | 1 | 1 |
> | `core_companias` (activa) | 3 | 13 |
> | `zonas` (legado) | 1 | 1 |
> | `core_zonas` (activa) | 1 | 1 |
>
> Como `banco_cuentas` está **vacía en dev y prod** y el módulo viejo estaba
> huérfano (el menú ya apuntaba a `bco.*`, y `BancoSync` usa `BcoCuenta`), se
> **retiró el módulo legado** en código: rutas `bancos.*`, `BancoCuentaController`,
> modelo `BancoCuenta`, vista `admin/bancos/index.blade.php` y `BancoTest`.
>
> **Resuelto 2026-06-23 (autorizado).** Se ejecutó `DROP TABLE banco_cuentas` en
> `etax2_dev` y `etax2` (estaba vacía: 0 filas, 0 FK dependientes). Su DDL queda
> versionado en la migración `2026_06_12_000002_banco_cuentas_tabla.php` (recreable).
> El despliegue de código (retiro del módulo + unificación ITBMS de D-06) se aplicó
> a **dev y prod** con la suite de pruebas en verde.
>
> Quedan como legado dormido (no eliminadas) `companias` y `zonas` con 1 fila
> huérfana cada una; pueden retirarse en una limpieza posterior si se confirma que
> ningún proceso las consulta.

---

## D-09 · Gate de permisos personalizado (orden controlado)

**Qué se observó.** En `AppServiceProvider`, un único `Gate::before` reemplaza el
auto-registro de spatie/permission para controlar el orden de evaluación:
1) el super-admin (`is_admin`) pasa todo;
2) en la **compañía 1 (sistema, WIN SOFT CORP)** los usuarios que no son
   super-admin solo pueden **ver** (lectura), salvo crear compañías;
3) resolución normal de permisos por rol/compañía.

**Decisión inferida.** Proteger la compañía del sistema y dar al super-admin
control total, manteniendo el modelo de permisos por compañía para el resto.

---

## D-10 · Sincronización contabilidad → bancos por observer

**Qué se observó.** `AsientoObserver` escucha cuando un asiento se postea o anula y
llama a `BancoSync` para reflejar/retirar movimientos bancarios de las cuentas
contables enlazadas a un banco. Crear el movimiento bancario **no** genera un nuevo
asiento (se evita la recursión).

**Decisión inferida.** La contabilidad es la fuente; el módulo de Bancos se
mantiene como un espejo conciliable de las cuentas contables bancarias.

---

## D-11 · Auditoría universal por observer

**Qué se observó.** `AppServiceProvider::registrarAuditoria()` recorre **todos** los
modelos de `app/Models` y les engancha `AuditObserver`, además de escuchar los
eventos de login/logout/login fallido. Cada cambio guarda valores antes/después en
`audit_actividad`. Ver `tecnico/seguridad-auditoria.md`.
