# Reportes

Reportes contables y fiscales disponibles. Basado en los controladores de
`app/Http/Controllers/Admin/Reporte*` y sus rutas en `routes/web.php`.

> Todos los reportes operan sobre la **compañía activa** y suelen permitir filtrar
> por período. Varios soportan **exportación** (el trait `ExportaReporte` y los
> paquetes de PDF/Excel del proyecto).

---

## 1. Reportes implementados (confirmados por rutas)

| Reporte | Ruta (nombre) | Controlador |
|---|---|---|
| **Balance General / Situación** | `reportes.balance` | `ReporteBalanceController` |
| **Balance de Comprobación** (+ detalle) | `reportes.comprobacion` (`.detalle`) | `ReporteComprobacionController` |
| **Estado de Resultado** | `reportes.resultado` | `ReporteResultadosController` |
| **Comparativo mensual** | `reportes.comparativo` | `ReporteComparativoController` |
| **Flujo de caja** | `reportes.flujo-caja` | `ReporteFlujoCajaController` |
| **Liquidación de ITBMS** (apoyo Formulario 430) | `reportes.liquidacion-itbms` | `ReporteLiquidacionItbmsController` |
| **Cuadre de auxiliares** | `reportes.cuadre-auxiliares` | `ReporteCuadreAuxiliaresController` |

### Balance General (Estado de Situación)
Muestra activos, pasivos y patrimonio a una fecha. Se apoya en la clasificación de
las cuentas por tipo/sección.

### Balance de Comprobación
Lista las cuentas con sus saldos deudores y acreedores para verificar que el total
de débitos iguale el total de créditos. Tiene una vista de **detalle**.

### Estado de Resultado
Ingresos, costos y gastos del período y la utilidad/pérdida resultante. Por diseño,
**no incluye el asiento de cierre** (que va en el período de ajuste, mes 13).

### Comparativo mensual
Compara cuentas/resultados mes a mes dentro del año.

### Flujo de caja
Entradas y salidas de efectivo.

### Liquidación de ITBMS (Formulario 430)
Calcula, mes a mes (1–12): base de ventas, **ITBMS cobrado** (débito fiscal), base
de compras, **ITBMS crédito fiscal** y el **neto** a pagar/favor. Considera las
notas de crédito de CxC y CxP para ajustar los montos. Es el reporte de apoyo para
la declaración del **Formulario 430** de la DGI. Ver `fiscal/itbms.md`.

> ⚠ **Verificar con contador:** que el cálculo del ITBMS neto, el tratamiento de
> notas de crédito y la presentación coincidan con lo que exige el Formulario 430
> vigente.

### Cuadre de auxiliares
Verifica que los saldos auxiliares (p. ej. CxC por cliente, CxP por proveedor)
concuerden con los saldos de las cuentas de control en la contabilidad. Es una
herramienta de control interno previa al cierre.

## 2. Libro diario y registro de asientos

El **registro de asientos** se gestiona en `AsientoController` (índice, ver detalle,
crear, importar, postear, anular). El índice de asientos funciona como **libro
diario** (listado cronológico de asientos con su detalle).

> ⚠ **No implementado / pendiente de verificar — Libro Mayor y "Libro Diario"
> formal.** No se encontró una ruta/reporte dedicado llamado *Libro Mayor* (movimientos
> de una cuenta en el tiempo con saldo corrido) ni un *Libro Diario* formateado como
> documento legal independiente del listado de asientos. Si la empresa los necesita
> como reportes formales (p. ej. para presentar a la DGI o auditoría), **están
> pendientes de confirmar/implementar**. La información existe en `cgl_asientos` /
> `cgl_asientos_detalle` / `cgl_saldos`, pero no como reporte dedicado con ese nombre.

## 3. Reportes auxiliares por módulo

Además de los contables, hay reportes específicos de módulo:
- **CxC**: antigüedad de saldos (`CxcAntiguedadController`), estado de cuenta.
- **CxP**: antigüedad de saldos (`CxpAntiguedadController`), estado de cuenta.
- **Presupuesto vs. real** (service `PresupuestoReal`, módulo Budget).

## 4. Exportación

Los reportes pueden exportarse mediante el trait `ExportaReporte` y los paquetes del
proyecto (`barryvdh/laravel-dompdf` para PDF y `maatwebsite/excel` para Excel).
El alcance exacto de formatos por reporte conviene verificarlo en cada vista.

## 5. Inconsistencias / pendientes

- ⚠ **Libro Mayor / Libro Diario formal**: no implementados como reporte dedicado
  (ver §2).
- ⚠ **Verificar con contador:** la equivalencia entre estos reportes y los registros
  contables legales exigidos en Panamá (libros principales y auxiliares).
