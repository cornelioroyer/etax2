# Firma electrónica

Cómo se maneja la firma electrónica de los documentos fiscales en eTax2. Basado en
`FelService`, `FelDocumentoBuilder`, `FacturaFelController` y `FelConfiguracion`.

---

## 1. Quién firma los documentos

**La firma electrónica la realiza el PAC (The Factory HKA), no eTax2.**

El flujo es:

1. eTax2 arma el documento electrónico y lo envía al web service del PAC.
2. El **PAC firma electrónicamente** el documento, lo transmite a la DGI y obtiene la
   autorización.
3. El PAC devuelve a eTax2 el **CUFE** (Código Único de Factura Electrónica) y el
   **QR**, que se guardan en `fel_documentos`.
4. El **XML firmado** y el **CAFE (PDF)** se obtienen del PAC bajo demanda
   (`descargaXML`, `descargaPDF`).

Por este diseño, **eTax2 no gestiona certificados digitales propios**: la custodia,
validación y renovación del certificado de firma son responsabilidad del **PAC**.

> ⚠ **Verificar con el PAC / contador:** la vigencia del certificado y el esquema de
> firma los administra The Factory HKA. Conviene confirmar con el proveedor las
> condiciones del servicio (vigencia, renovación, respaldo del certificado).

## 2. Credenciales del PAC (lo que sí gestiona eTax2)

Lo que eTax2 sí administra son las **credenciales de acceso al PAC**, por compañía,
en la tabla `fel_configuracion`:

- `token_empresa` y `token_password` — **cifrados** en la base de datos (cast
  `encrypted` de Laravel; dependen de `APP_KEY`).
- `ambiente` — `PRUEBAS` o `PRODUCCION`.
- `punto_facturacion`, `codigo_sucursal`, `correlativo`.

> ⚠ **Custodia de `APP_KEY`:** como los tokens se cifran con `APP_KEY`, si esta clave
> se pierde o cambia, los tokens guardados dejan de poder descifrarse. Debe
> respaldarse y custodiarse (ver `tecnico/respaldos-aws.md` y `DECISIONES.md` → D-05).

## 3. Validación del documento

- **Antes de enviar:** eTax2 valida los datos del documento (tipo, cliente, ítems,
  tasas ITBMS permitidas, forma de pago) y omite los campos no requeridos según las
  reglas del PAC.
- **Después de enviar:** interpreta la respuesta del PAC. Si la DGI autoriza
  (`codigo 200` o `resultado "Procesado"`), el documento queda `AUTORIZADO` con su
  CUFE; si no, queda `RECHAZADO` con el mensaje de error. Cada intento se registra en
  `fel_eventos`.
- **Consulta posterior:** `estadoDocumento` permite consultar el estado de un
  documento ya enviado.
- **Anulación:** `anulacionDocumento` anula ante la DGI un documento autorizado (con
  motivo), dejándolo `ANULADO`.

## 4. Renovación

La **renovación del certificado de firma** corresponde al PAC. Desde eTax2, lo que
puede requerir renovación/actualización son las **credenciales (tokens)** del PAC si
el proveedor las rota; en ese caso se actualizan en `fel_configuracion`.

> ⚠ **No implementado / pendiente de verificar:** no se observó en el código una
> alerta o control de **vencimiento** (de tokens del PAC o de folios disponibles).
> El método `foliosRestantes()` permite consultar folios, pero no se vio un aviso
> automático cuando se están agotando. Recomendable añadir un monitoreo.

## 5. Modo demo vs. producción

> ⚠ **Importante.** En modo **demo**, todas las compañías comparten las credenciales
> y un único consecutivo fiscal (ver `tecnico/integracion-sfep.md` y
> `DECISIONES.md` → D-04). En **producción**, cada compañía debe tener **sus propios
> tokens** del PAC y operar en el ambiente `PRODUCCION`. **Verificar con
> operaciones/contador** antes de emitir documentos reales.

## 6. Resumen de responsabilidades

| Responsabilidad | Quién |
|---|---|
| Firma electrónica del documento | **PAC (The Factory HKA)** |
| Custodia/renovación del certificado de firma | **PAC** |
| Transmisión a la DGI y CUFE | **PAC** |
| Credenciales de acceso al PAC (tokens) | **eTax2** (cifradas por compañía) |
| Armado y validación previa del documento | **eTax2** |
| Registro y estado del documento | **eTax2** (`fel_documentos`, `fel_eventos`) |
