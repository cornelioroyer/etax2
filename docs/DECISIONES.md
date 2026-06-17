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
el esquema maestro (ver D-01). ⚠ **Verificar** que el trigger esté efectivamente
creado en producción.

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

## D-06 · ITBMS con dos fuentes de verdad (inconsistencia)

**Qué se observó.**
- Ventas y Compras calculan el ITBMS leyendo el **porcentaje de la tabla
  `tax_impuestos`** (`base * porcentaje / 100`).
- El módulo **FEL** usa un arreglo de **factores fijos en código**
  (`FelDocumentoBuilder::TASAS_ITBMS = ['00'=>0.00, '01'=>0.07, '02'=>0.10, '03'=>0.15]`).

**Decisión inferida / riesgo.** Hoy ambos dan el mismo resultado, pero son dos
fuentes de verdad distintas. Si la DGI cambiara una tasa, habría que actualizarla
en **dos lugares**. Detallado en `fiscal/itbms.md` → "Inconsistencias detectadas".

⚠ **Verificar con contador.**

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

⚠ **Pendiente de verificar:** confirmar cuáles tablas están realmente en uso y
retirar las obsoletas para evitar confusión.

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
