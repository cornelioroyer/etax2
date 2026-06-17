# Ciclos contables (ventas, compras, tesorería)

Describe los ciclos operativos del sistema y cómo se conectan con la contabilidad.
Basado en los controladores de Ventas, Compras, CxC/CxP, Bancos y Caja, y en el
service `AsientoAutomatico`.

> Principio general: los módulos operativos generan documentos y, cuando
> corresponde, **producen asientos contables automáticamente** a través de
> `AsientoAutomatico`, dentro de transacciones que permiten revertir todo si algo
> falla (ver `tecnico/asientos-contables.md`).

---

## 1. Ciclo de ventas

Módulos: Cotizaciones, Facturas, Notas de crédito, Recibos.

Flujo típico observado en el código:

1. **Cotización** (`VentaCotizacion`): documento previo sin efecto contable.
   Numeración propia con *advisory lock* (`COT-`).
2. **Factura de venta** (`VentaFactura`): puede crearse en **BORRADOR** y luego
   emitirse. Al calcular la factura:
   - Por cada línea: `base = cantidad × precio_unitario`,
     `ITBMS = base × (porcentaje/100)` tomando el porcentaje de `tax_impuestos`,
     `total_linea = base + ITBMS`.
   - Totales: subtotal, ITBMS y total, todos redondeados a 2 decimales.
   - Restricción observada: **no se permite más de un borrador de factura por
     compañía** a la vez.
3. **Contabilización**: al emitir, se genera el asiento de la venta
   (ingreso, ITBMS por pagar, cuenta por cobrar/efectivo) vía `AsientoAutomatico`.
4. **Recibo** (`VentaRecibo`): registra el cobro de la factura.
5. **Nota de crédito** (`VentaNotaCredito`): ajusta/reversa una venta.

> ⚠ **Verificar con contador:** las cuentas concretas que usa cada asiento de venta
> (ingreso, ITBMS débito fiscal, CxC) dependen de la configuración de cuentas por
> defecto de la compañía. Confirmar que el mapeo contable de la venta sea el correcto.

### Relación con la factura electrónica (FEL)
La emisión electrónica ante la DGI (CUFE) se gestiona en el módulo FEL
(`FacturaFelController`), que puede emitirse de forma manual o a partir de un
documento. La contabilidad y la emisión fiscal son procesos relacionados pero
distintos. Ver `tecnico/integracion-sfep.md`.

## 2. Ciclo de compras

Módulos: Órdenes de compra, Recepciones, CxP, Pagos, Gastos.

1. **Orden de compra** (`CompraOrden`): por línea calcula
   `base = cantidad × precio_unitario` e `ITBMS = base × (porcentaje/100)`.
   Numeración con *advisory lock* (`OC-`).
2. **Recepción** (`CompraRecepcion`): registra la entrada de la mercancía/servicio
   (`RM-`), e impacta inventario cuando aplica.
3. **Factura de proveedor / CxP** (`CxpDocumento`): la cuenta por pagar. La
   migración `cxp_factura_borrador` permite el estado **BORRADOR** además de
   PENDIENTE / PARCIAL / PAGADO / ANULADO.
4. **Pago** (`CxpPago` / aplicaciones `CxpAplicacion`): registra el pago, total o
   parcial, y su asiento.
5. **Gastos** (`GastoController`): gastos directos.

> ⚠ **Verificar con contador:** el tratamiento del **ITBMS pagado en compras como
> crédito fiscal** y las cuentas usadas (gasto/activo, ITBMS crédito fiscal, CxP).

## 3. Cuentas por cobrar y por pagar (CxC / CxP)

- **CxC** (`CxcDocumento`, `CxcAplicacion`): documentos por cobrar y la aplicación
  de cobros. Reportes de **antigüedad de saldos** (`CxcAntiguedadController`) y
  **estado de cuenta** (`CxcEstadoCuentaController`).
- **CxP** (`CxpDocumento`, `CxpAplicacion`): equivalente para cuentas por pagar, con
  antigüedad y estado de cuenta.

Estas cuentas suelen marcarse como `requiere_contacto` en el plan de cuentas, de
modo que cada movimiento quede asociado a un cliente o proveedor (control auxiliar).
El reporte de **cuadre de auxiliares** valida que los auxiliares concuerden con la
contabilidad (ver `reportes.md`).

## 4. Tesorería (Bancos y Caja)

### Bancos
- `BcoBanco`, `BcoCuenta`, `BcoMovimiento`, `BcoDeposito`, `BcoCheque`,
  `BcoTransferencia`.
- **Conciliación bancaria** (`BcoConciliacion` + detalle): concilia los movimientos
  del banco con los de la contabilidad.
- **Sincronización automática**: cuando un asiento que toca una cuenta contable
  enlazada a un banco se postea o anula, `AsientoObserver` → `BancoSync` refleja o
  retira el movimiento bancario, manteniendo el módulo de Bancos como **espejo
  conciliable** de la contabilidad (ver `DECISIONES.md` → D-10).

### Caja
- `Caja`, `CajaMovimiento`, `CajaVale`, `CajaReembolso`, `CajaArqueo` (+ detalle).
- Soporta **caja menuda** (vales, reembolsos) y **arqueos** (conteo y cuadre del
  efectivo contra el saldo).

> ⚠ **Verificar con contador:** la contabilización de depósitos, transferencias,
> cheques, vales y reembolsos, y las cuentas usadas en cada caso.

## 5. Otros módulos que generan contabilidad

- **Activos Fijos (AFI):** altas, **depreciación**, revaluación y bajas, con su
  efecto contable.
- **Inventario (INV):** existencias, kardex, movimientos; método de costeo por
  compañía (`metodo_costeo`, default PROMEDIO) y opción de permitir stock negativo.
- **Verticales (Taller, PH, Educación):** generan cobros/facturas que entran a los
  ciclos de ventas/CxC y, por tanto, a la contabilidad.

## 6. Cómo se garantiza la integridad del ciclo

Todo asiento generado por un módulo pasa por `AsientoAutomatico`, que valida el
cuadre y el período, y por el **trigger de PostgreSQL** que revalida en la base de
datos. Como cada operación corre en una transacción, si el asiento no cuadra o el
período está cerrado, **el documento del módulo también se revierte**. Ver
`tecnico/asientos-contables.md`.

## 7. Inconsistencias detectadas

- **Cálculo de ITBMS con dos fuentes de verdad**: Ventas/Compras usan
  `tax_impuestos.porcentaje`; FEL usa factores fijos en código. Ver `fiscal/itbms.md`
  y `DECISIONES.md` → D-06. ⚠ Verificar con contador.
- ⚠ **Pendiente de verificar:** el detalle exacto de los asientos generados por cada
  módulo (qué cuentas, débito/crédito) no se documenta exhaustivamente aquí porque
  depende de las cuentas por defecto configuradas por compañía. Se recomienda hacer,
  con un contador, una **matriz de contabilización** por tipo de documento.
