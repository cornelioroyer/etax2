# Cierre de período (mensual y anual)

Describe el proceso de cierre en eTax2. Basado en `app/Models/PeriodoContable.php`,
`app/Services/CierreAnual.php`, `app/Http/Controllers/Admin/PeriodoContableController.php`,
`CierreAnualController`, `CglCierreController` y la función PL/pgSQL
`fn_validar_asiento_posteado()`.

---

## 1. Períodos contables

Cada compañía maneja **períodos mensuales** (`cgl_periodos`), identificados por
`anio` + `mes`, con `fecha_inicio`, `fecha_fin` y un `estado` (ABIERTO, …). El
`mes` admite valores de **1 a 13**, donde **13 es el período de ajuste/cierre anual**
(ver §3).

### Efecto del estado del período
- Un asiento solo puede **postearse** si su período está **ABIERTO**. Esto se valida
  en dos capas: en PHP (`AsientoAutomatico`) y en la base de datos (el trigger lanza
  *"el periodo de la fecha … está … — no se puede postear"*).
- Al **cerrar** un período mensual, ya no se pueden registrar ni modificar asientos
  con fecha dentro de ese período.

## 2. Cierre mensual

Gestionado desde `PeriodoContableController` (y `CglCierreController` para el
registro de cierres). El cierre mensual consiste en **cerrar el período** (cambiar su
estado) una vez revisada la contabilidad del mes, de modo que quede "congelado".

> ⚠ **Verificar con contador:** que el cierre mensual contemple todas las
> validaciones que la empresa requiera antes de congelar el mes (conciliaciones
> bancarias completas, auxiliares cuadrados, ITBMS revisado, etc.). El sistema
> ofrece reportes de apoyo (cuadre de auxiliares, liquidación de ITBMS), pero la
> **decisión de cerrar es del contador**.

### Reapertura
La reapertura de un período es una acción sensible y queda registrada en la tabla de
auditoría específica **`audit_reaperturas`** (ver `tecnico/seguridad-auditoria.md`).

> ⚠ **Verificar con contador:** la política de reapertura (quién puede, en qué
> casos) y su impacto en declaraciones ya presentadas a la DGI.

## 3. Cierre anual del ejercicio

Gestionado por el service `CierreAnual` (y `CierreAnualController`). Su objetivo:
**saldar las cuentas de resultado** (INGRESO, COSTO, GASTO) contra la cuenta de
patrimonio configurada como **`UTILIDADES_RETENIDAS`**, dejando la utilidad o
pérdida del ejercicio en el patrimonio.

### Cómo funciona

1. **Previsualización** (`previsualizar`): lee los saldos del año (meses 1–12) de
   las cuentas de resultado desde `cgl_saldos`, calcula:
   - **Ingresos** (saldo acreedor de cuentas INGRESO),
   - **Costos** y **Gastos** (saldo deudor),
   - **Utilidad del ejercicio** = ingresos − costos − gastos.
   Arma las líneas del asiento que **reversan** cada cuenta de resultado.

2. **Asiento de cierre** (`cerrar`):
   - Verifica que no exista ya un cierre posteado para el año.
   - Verifica que haya movimientos en cuentas de resultado.
   - Verifica que esté configurada la cuenta `UTILIDADES_RETENIDAS` (si falta, exige
     configurarla antes de cerrar).
   - Crea el asiento con fecha **31 de diciembre** del año, descripción
     *"Asiento de cierre del ejercicio AAAA"* y referencia `CIERRE-AAAA`.
   - Lo postea en el **período de ajuste (mes 13)** del año, de modo que los reportes
     operativos por mes (1–12) **no lo incluyan**.
   - Todo dentro de una transacción.

3. **Reversa** (`reversar`): anula el asiento de cierre del ejercicio (estado
   `ANULADO`); los saldos se revierten.

### Por qué el mes 13
Para no "ensuciar" el estado de resultado mensual: el cierre se aísla en un período
de ajuste. La restricción de la tabla `cgl_periodos` se amplió específicamente para
permitir `mes = 13`, y el trigger de PostgreSQL **respeta** el período de ajuste si
el asiento ya lo trae asignado (ver `DECISIONES.md` → D-07).

> ⚠ **Verificar con contador:**
> - Que saldar todo el resultado contra `UTILIDADES_RETENIDAS` corresponda a la
>   política contable de la empresa (algunas empresas usan una cuenta intermedia de
>   "Pérdidas y Ganancias" o "Resultado del Ejercicio" antes de pasar a utilidades
>   retenidas).
> - El tratamiento de la **utilidad/pérdida** y su relación con la **declaración de
>   renta (ISR)** ante la DGI.

## 4. Relación con la conservación de documentos

El cierre congela la información contable de un período/ejercicio, lo que conecta con
la obligación de **conservar los documentos 5 años** (ver `fiscal/conservacion.md`).
La auditoría (`audit_actividad`, `audit_reaperturas`) deja constancia de quién cerró
o reabrió y cuándo.

## 5. Inconsistencias / pendientes

- ⚠ **Pendiente de verificar:** dónde y cómo se actualiza `cgl_saldos` (de lo que
  depende la exactitud del cierre). El cálculo del cierre confía en esa tabla; su
  mantenimiento forma parte del esquema maestro (ver `tecnico/modelo-datos.md`).
- ⚠ **Pendiente de verificar:** el comportamiento del cierre mensual respecto a
  documentos en estado BORRADOR (que no afectan saldos) al momento de cerrar el mes.
