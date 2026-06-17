# ITBMS (Impuesto sobre la Transferencia de Bienes Muebles y Servicios)

Cómo maneja eTax2 las tasas de ITBMS, las exenciones y el reporte de liquidación
(Formulario 430). Basado en `tax_impuestos`, `FelDocumentoBuilder`,
`VentaFacturaController`, `CompraOrdenController` y `ReporteLiquidacionItbmsController`.

> ⚠ Este documento describe **lo que hace el código**. Todo lo relativo a tasas,
> exenciones y obligaciones formales debe **validarse con un contador** conforme a
> la normativa vigente de la DGI.

---

## 1. Tasas que maneja el sistema

El sistema contempla las cuatro tasas del catálogo de la DGI:

| Código DGI | Tasa | Uso típico (según comentarios del código) |
|---|---|---|
| `00` | 0% (Exento) | Bienes/servicios exentos |
| `01` | 7% | Tasa general |
| `02` | 10% | Bebidas alcohólicas, hospedaje |
| `03` | 15% | Tabaco |

Estas tasas aparecen en **dos lugares** del código:

1. **Tabla `tax_impuestos`** (se siembran 4 registros globales: `ITBMS_0`=0,
   `ITBMS_7`=7, `ITBMS_10`=10, `ITBMS_15`=15). La usan **Ventas** y **Compras**.
2. **Constante `FelDocumentoBuilder::TASAS_ITBMS`** (factores fijos en código:
   `00`=0.00, `01`=0.07, `02`=0.10, `03`=0.15). La usa el **módulo FEL**.

> ⚠ **Verificar con contador:** que la asignación de tasas por tipo de producto/
> servicio sea correcta. La tasa por defecto del FEL cuando no se especifica es
> **7%** (`?? 0.07`), y el código CPBS por defecto corresponde a servicios
> informáticos; ambos deben ajustarse al giro real.

## 2. Cómo se calcula

### En Ventas y Compras (desde `tax_impuestos`)
Por cada línea:

```
base   = cantidad × precio_unitario
ITBMS  = base × (porcentaje / 100)     // porcentaje viene de tax_impuestos (7, 10, 15)
total  = base + ITBMS
```

Todo redondeado a 2 decimales. Los totales del documento suman las líneas.

### En FEL (desde la constante en código)
Por cada ítem:

```
precioItem = cantidad × precio
ITBMS      = precioItem × factor       // factor = 0.07, 0.10, 0.15 (de la constante)
valorTotal = precioItem + ITBMS
```

> El resultado numérico es el mismo (7% = 0.07), pero la **fuente del dato es
> distinta**. Ver "Inconsistencias detectadas" (§5).

## 3. Exenciones (tasa 0%)

La tasa `00` (código DGI) / `ITBMS_0` (tabla) representa operaciones **exentas**: el
ITBMS calculado es 0. En el FEL, el factor es `0.00`.

> ⚠ **Verificar con contador:** qué bienes/servicios deben marcarse como exentos y
> que el sistema los esté tratando correctamente (exento ≠ gravado a 0 a efectos de
> reportes; confirmar el tratamiento en la liquidación).

## 4. Liquidación de ITBMS — Formulario 430

El reporte `ReporteLiquidacionItbmsController` calcula, mes a mes (1–12):

- **Base de ventas** e **ITBMS cobrado** (débito fiscal), restando el ITBMS de las
  **notas de crédito de CxC**.
- **Base de compras** e **ITBMS crédito fiscal**, restando el ITBMS de las **notas
  de crédito de CxP**.
- **Neto del mes** = ITBMS cobrado − ITBMS crédito fiscal.
- Totales anuales de cada columna.

Este reporte es el **apoyo para declarar el Formulario 430** de la DGI.

> ⚠ **Verificar con contador:**
> - Que el período de declaración (mensual) y el cálculo del neto a pagar / saldo a
>   favor coincidan con el Formulario 430 vigente.
> - El tratamiento de **saldos a favor** arrastrados de meses anteriores (no se
>   observó en el cálculo del controlador un arrastre de saldo a favor; revisar).
> - El tratamiento de retenciones de ITBMS si aplican (existe el modelo
>   `TaxRetencion`, pero su uso en la liquidación debe confirmarse).

## 5. Inconsistencias detectadas

1. **Dos fuentes de verdad para las tasas de ITBMS.** Ventas/Compras leen el
   porcentaje de `tax_impuestos`; FEL usa una constante en código
   (`FelDocumentoBuilder::TASAS_ITBMS`). Hoy ambos coinciden (7/10/15%), pero:
   - Si la DGI cambiara una tasa, habría que actualizarla en **dos lugares**.
   - Un cambio en uno solo produciría documentos fiscales inconsistentes (la
     contabilidad diría una cosa y el documento electrónico otra).
   - **Recomendación:** unificar en una sola fuente (idealmente `tax_impuestos`) y
     que el FEL lea de ahí. ⚠ Verificar con contador antes de cambiar.

2. **Tasa y CPBS por defecto fijos en el FEL.** Si no se especifica, FEL asume 7% y
   CPBS de servicios informáticos. Correcto para ese giro; riesgoso para otros.

3. **Arrastre de saldo a favor en la liquidación**: ⚠ pendiente de verificar si el
   reporte debe arrastrar saldos a favor entre meses.
