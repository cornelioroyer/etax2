# Manual del contador — eTax2

Guía paso a paso para el contador que usa el sistema. Está escrita en lenguaje
sencillo, sin tecnicismos. Si algo no coincide con lo que ves en pantalla, avísale
al equipo: el sistema se actualiza con frecuencia.

> A lo largo del manual verás notas **⚠ Importante**: son cosas que conviene
> revisar con cuidado porque tienen efecto contable o fiscal.

---

## 1. Conceptos básicos antes de empezar

- **Compañía activa.** El sistema maneja varias empresas. Arriba siempre verás cuál
  está seleccionada. **Todo lo que registres entra en esa empresa.** Si trabajas con
  varias, revisa siempre que sea la correcta antes de empezar.
- **Balboas (B/.).** Todos los montos están en balboas, con dos decimales.
- **Estados de los documentos.** Casi todo pasa por estados como *Borrador*,
  *Posteado/Emitido*, *Anulado*. Un *borrador* todavía no afecta la contabilidad; al
  *postear/emitir* sí queda registrado.

## 2. Iniciar sesión y elegir la empresa

1. Entra con tu correo y contraseña (o con el botón de Google si tu cuenta lo usa).
2. Si tienes acceso a más de una empresa, selecciónala en el selector de compañía.
3. Listo: ya estás trabajando dentro de esa empresa.

## 3. El plan de cuentas

El plan de cuentas es la lista de todas las cuentas contables de la empresa,
organizada como un árbol (cuentas que agrupan y cuentas de detalle).

- Cuando se crea una empresa nueva, el sistema le copia un **plan de cuentas
  base** (plantilla "Formulario 2 / ISR Panamá").
- Solo las **cuentas de detalle** (las "hojas" del árbol) reciben movimientos.
- Algunas cuentas **exigen indicar el cliente o proveedor** (por ejemplo, las
  cuentas por cobrar o por pagar). Si una cuenta lo exige, el sistema te lo pedirá.

> ⚠ Importante: si vas a usar el plan base, revísalo con calma para confirmar que
> las cuentas y su clasificación se ajustan a tu empresa.

## 4. Registrar asientos contables

Puedes registrar asientos manuales o dejar que los módulos (ventas, compras, etc.)
los generen automáticamente.

### Para un asiento manual:
1. Ve a **Asientos → Nuevo**.
2. Indica la fecha, una descripción y, si quieres, una referencia.
3. Agrega las líneas: por cada una, elige la cuenta y escribe el monto al **débito**
   o al **crédito**.
4. Asegúrate de que **el total de débitos sea igual al total de créditos**. Si no
   cuadra, el sistema **no te dejará postear** y te avisará.
5. Guarda como borrador o **postea** directamente.

> ⚠ Importante: solo puedes postear en un **período abierto**. Si el mes ya está
> cerrado, el sistema lo rechazará. (Ver sección 8, cierre de período.)

### Importar asientos
En **Asientos → Importar** puedes cargar asientos desde una plantilla de Excel
(descarga primero la plantilla desde el mismo lugar para usar el formato correcto).

### Anular un asiento
Si te equivocaste en un asiento ya posteado, **anúlalo** (no se borra: queda
registrado como anulado, junto con quién y cuándo).

## 5. Ventas

1. **Cotización** (opcional): documento previo, sin efecto contable.
2. **Factura de venta**: agrega los productos/servicios; el sistema calcula el
   **ITBMS** automáticamente según la tasa de cada línea (0%, 7%, 10% o 15%).
   - Puedes guardarla como **borrador** y emitirla después.
   - ⚠ Solo puede haber **un borrador de factura a la vez** por empresa.
3. **Recibo**: registra el cobro de la factura.
4. **Nota de crédito**: para ajustar o devolver una venta.

## 6. Facturación electrónica (DGI)

El sistema envía las facturas electrónicas a la DGI a través de un proveedor
autorizado (PAC). Al emitir una factura electrónica:

1. Ve a **Facturas electrónicas → Nueva**.
2. Elige el tipo (factura, nota de crédito o nota de débito), el cliente, la forma de
   pago y agrega los ítems con su tasa de ITBMS.
3. Al enviarla, pueden pasar dos cosas:
   - **Autorizada**: la DGI la acepta y el sistema guarda el **CUFE** (el código
     único de la factura) y el código QR.
   - **Rechazada**: el sistema te muestra el motivo. Corrige y vuelve a intentar.
4. Desde la lista puedes **descargar el PDF** (CAFE) y, si hace falta, **anular** una
   factura autorizada (indicando el motivo).

> ⚠ Importante: para emitir facturas reales, la empresa debe tener configuradas
> **sus propias credenciales** del proveedor y estar en ambiente de *Producción*. Si
> ves "PRUEBAS", esas facturas **no son válidas** ante la DGI. Confírmalo con el
> administrador.

## 7. Compras, cuentas por cobrar y por pagar

- **Compras**: orden de compra → recepción → factura del proveedor (cuenta por
  pagar) → pago. El ITBMS de las compras se registra como crédito fiscal.
- **Cuentas por cobrar (CxC)**: lo que te deben los clientes. Hay reportes de
  **antigüedad** (cuánto tiempo lleva pendiente cada saldo) y **estado de cuenta**.
- **Cuentas por pagar (CxP)**: lo que la empresa debe a proveedores, con los mismos
  reportes.

## 8. Bancos y caja

- **Bancos**: registra movimientos, depósitos, cheques y transferencias, y haz la
  **conciliación bancaria** (comparar el banco con la contabilidad). El sistema
  mantiene los movimientos del banco en línea con la contabilidad automáticamente.
- **Caja**: maneja caja menuda con **vales** y **reembolsos**, y los **arqueos**
  (conteo del efectivo para verificar que cuadra con el saldo).

## 9. Cierre de período

### Cierre mensual
Cuando termines de revisar el mes, ciérralo para que nadie pueda modificar asientos
de ese período por error.

> ⚠ Importante: antes de cerrar, conviene revisar las **conciliaciones bancarias**,
> el **cuadre de auxiliares** (CxC/CxP) y la **liquidación de ITBMS**. Una vez
> cerrado, no se pueden registrar asientos en ese mes (salvo que se reabra, lo cual
> queda auditado).

### Cierre anual
Al terminar el año, el sistema genera el **asiento de cierre**: traslada el resultado
del ejercicio (utilidad o pérdida) a las **utilidades retenidas**. Este asiento se
registra en un "período de ajuste" especial para que no aparezca en los reportes
mensuales.

> ⚠ Importante: para cerrar el año, la empresa debe tener configurada la cuenta de
> **utilidades retenidas**. Si falta, el sistema te avisará. Revisa el cierre con
> calma; si necesitas, puede reversarse.

## 10. Reportes

Tienes disponibles, entre otros:

- **Balance General** (situación a una fecha).
- **Estado de Resultado** (ingresos, costos, gastos y la utilidad del período).
- **Balance de Comprobación** (para verificar que todo cuadra).
- **Comparativo mensual**.
- **Flujo de caja**.
- **Liquidación de ITBMS** (apoyo para el **Formulario 430**): te muestra, mes a mes,
  el ITBMS cobrado, el crédito fiscal y el neto.
- **Cuadre de auxiliares** (que CxC/CxP cuadren con la contabilidad).

Muchos reportes se pueden **exportar a PDF o Excel**.

> ⚠ Importante: el reporte de **Liquidación de ITBMS** es un apoyo; revisa que los
> números coincidan con lo que vas a declarar en el Formulario 430.

## 11. Cosas que el sistema NO hace (a hoy) y conviene saber

- No hay un reporte llamado **Libro Mayor** ni un **Libro Diario** formateado como
  documento legal aparte (la información de los asientos sí está disponible en la
  pantalla de asientos). Si los necesitas como reportes formales, coméntalo al equipo.
- La conservación de documentos por 5 años depende también de los respaldos del
  sistema (eso lo maneja el área técnica). Si necesitas un respaldo de tus XML/PDF,
  consúltalo con el administrador.

## 12. ¿Algo se ve raro?

Como el sistema audita todo, cualquier cambio queda registrado (quién y cuándo). Si
ves un dato incorrecto, no lo borres: corrígelo con el documento adecuado (nota de
crédito, anulación, asiento de ajuste) y, si tienes dudas, consulta al equipo.
