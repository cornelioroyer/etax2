# Integración con la DGI (facturación electrónica / FEL)

Documenta el código que conecta eTax2 con la facturación electrónica de la DGI:
flujo de envío, firma, obtención del CUFE y manejo de errores. Basado en
`app/Services/FelService.php`, `app/Services/FelDocumentoBuilder.php`,
`app/Http/Controllers/Admin/FacturaFelController.php` y `app/Models/FelConfiguracion.php`.

---

## 1. Cómo se conecta eTax2 con la DGI

eTax2 **no se conecta directamente** al sistema de la DGI. Lo hace a través de un
**PAC (Proveedor Autorizado Calificado)**: **The Factory HKA Panamá**. El PAC es
quien firma electrónicamente, transmite a la DGI y devuelve el **CUFE** (Código
Único de Factura Electrónica) y el QR.

> Terminología: el equipo se refiere a "SFEP/PAC". En el código, el componente
> concreto es el web service de **The Factory HKA** (`FelService`). El término
> genérico FEL (Factura Electrónica) se usa en tablas y modelos.

La conexión es vía **SOAP** (`SoapClient` de PHP) contra el WSDL de HKA:

- Pruebas: `https://demoemision.thefactoryhka.com.pa/ws/obj/v1.0/Service.svc?singleWsdl`
- Producción: `https://emision.thefactoryhka.com.pa/ws/obj/v1.0/Service.svc?singleWsdl`

El ambiente (`PRUEBAS` / `PRODUCCION`) se elige por compañía en `fel_configuracion`.

## 2. Configuración por compañía (`fel_configuracion`)

Cada compañía guarda:

- `ambiente` — PRUEBAS o PRODUCCION.
- `token_empresa` y `token_password` — credenciales del PAC, **cifradas** en la
  base de datos (cast `encrypted`; dependen de `APP_KEY`).
- `punto_facturacion` (default `001`) y `codigo_sucursal` (default `0000`).
- `correlativo` — consecutivo del número de documento fiscal.

## 3. Operaciones disponibles (`FelService`)

`FelService` expone los métodos del web service del PAC, normalizando la respuesta
SOAP a un arreglo:

| Método | Para qué sirve |
|---|---|
| `enviar($documento)` | Envía un documento electrónico (factura, nota). |
| `foliosRestantes()` | Folios disponibles; sirve también como prueba de conexión. |
| `estadoDocumento($datos)` | Consulta el estado de un documento ya enviado. |
| `anulacionDocumento($datos, $motivo)` | Anula un documento autorizado. |
| `descargaPDF($datos)` | Descarga el CAFE en PDF (base64). |
| `descargaXML($datos)` | Descarga el XML firmado del documento. |

Todas las llamadas adjuntan automáticamente `tokenEmpresa` y `tokenPassword` de la
compañía, y tienen tiempos de espera configurados (conexión 30 s, stream 60 s).

## 4. Construcción del documento (`FelDocumentoBuilder`)

Arma el arreglo `DocumentoElectronico` que el PAC espera (estructura del WSDL obj
v1.0, ejemplo "Factura de operación interna"). Detalles relevantes:

- **Tipos de documento soportados:** `01` Factura de operación interna,
  `06` Nota de crédito genérica, `07` Nota de débito genérica.
  (Las notas genéricas 06/07 **no** requieren referenciar el CUFE del documento
  original, a diferencia de las notas 04/05, que no están implementadas.)
- **Tasas ITBMS** (catálogo DGI, código → factor): `00`=0%, `01`=7%, `02`=10%,
  `03`=15%.
- **Cliente:** si no hay RUC, se envía como **consumidor final** (`tipoClienteFE`
  `02`); con RUC, como contribuyente (`01`) con dígito verificador, provincia,
  distrito, corregimiento, etc.
- **Ítems:** por cada línea calcula `precioItem = cantidad × precio`,
  `ITBMS = precioItem × factor`, y `valorTotal = precioItem + ITBMS`. Incluye
  código CPBS (obligatorio para la DGI; por defecto `81`/`8111` = servicios
  informáticos).
- **Totales:** subtotal neto, total ITBMS, total factura, forma de pago, etc.
- Convención del PAC respetada en el código: **los campos no requeridos se omiten**
  (no se envían vacíos) mediante `array_filter`.

> ⚠ **Verificar con contador:** el código CPBS por defecto (`81`/`8111`, servicios
> informáticos) debe ajustarse al giro real de cada producto/servicio facturado.
> Un CPBS incorrecto es un dato fiscal incorrecto.

## 5. Flujo de emisión (`FacturaFelController@store`)

1. Verifica el permiso `fel.gestionar` y que exista configuración con
   `token_empresa`. Si falta, redirige a configurar los tokens del PAC.
2. Valida la entrada (tipo de documento, cliente, forma de pago, ítems y tasas
   ITBMS permitidas `00/01/02/03`).
3. **Reserva el número fiscal** dentro de una transacción:
   `$config->siguienteNumeroFiscal()` (con bloqueo de fila; ver §7).
4. Construye el documento con `FelDocumentoBuilder->facturaInterna(...)`.
5. Guarda localmente `FelDocumento` (estado `PENDIENTE`) + `FelDocumentoDetalle`.
6. **Envía al PAC**: `(new FelService($config))->enviar($documento)`.
7. **Interpreta la respuesta:**
   - Si `codigo == '200'` o `resultado == 'Procesado'` → estado `AUTORIZADO`,
     guarda `cufe`, `qr`, la respuesta completa de la DGI y la fecha de envío.
     Muestra el CUFE al usuario.
   - En otro caso → estado `RECHAZADO`, guarda la respuesta y muestra el mensaje de
     error del PAC.
8. **Registra el evento** (ENVIO) en `fel_eventos` con la respuesta en JSON.

### Otras acciones del controlador
- **`pdf()`** — descarga el CAFE (PDF) desde el PAC y lo devuelve en línea.
- **`anular()`** — solo documentos `AUTORIZADO`; llama a `anulacionDocumento` con un
  motivo, registra el evento ANULACION y, si el PAC responde OK, deja el documento
  `ANULADO`.

## 6. Firma electrónica y CUFE

- La **firma electrónica la realiza el PAC (The Factory HKA)**, no este código.
  eTax2 envía el documento y el PAC lo firma y lo transmite a la DGI.
- El **CUFE** lo **devuelve el PAC** en la respuesta del `enviar` y se guarda en
  `fel_documentos.cufe`. También se guarda el `qr`.
- El **XML firmado** se obtiene del PAC con `descargaXML` (no se genera localmente).

Por eso **no hay manejo de certificados digitales propios en el repositorio**: la
custodia del certificado de firma es responsabilidad del PAC. Ver
`fiscal/firma-electronica.md`.

## 7. Numeración fiscal (modo demo vs. tokens propios)

`FelConfiguracion::siguienteNumeroFiscal()` incrementa el `correlativo` con bloqueo
de fila (`lockForUpdate`), dentro de una transacción:

- **Modo demo (credenciales compartidas de HKA):** todas las compañías comparten un
  **único consecutivo** anclado en la compañía del sistema (ID 1), porque emiten
  bajo la misma cuenta PAC; si se repitiera el número, la DGI rechazaría con
  "Documento duplicado".
- **Con tokens propios:** cada compañía lleva su propio espacio de folios.

> ⚠ **Verificar con operaciones/contador:** en producción cada compañía debe usar
> **sus propios tokens HKA**. Operar en producción con las credenciales demo
> mezclaría la numeración de varias empresas.

## 8. Manejo de errores

- **Errores de transporte/SOAP:** `FelService::llamar()` captura cualquier
  excepción y devuelve `['error' => true, 'codigo' => 'SOAP', 'mensaje' => ...]`,
  de modo que el controlador siempre recibe un arreglo y nunca una excepción sin
  controlar.
- **Rechazo de la DGI:** el documento queda `RECHAZADO` con el mensaje del PAC, y se
  conserva la respuesta completa para diagnóstico.
- **Trazabilidad:** cada interacción (envío, anulación) se guarda en `fel_eventos`
  con la respuesta íntegra en JSON.

## 9. Inconsistencias detectadas

- **Doble fuente de verdad del ITBMS** (ver `fiscal/itbms.md` y `DECISIONES.md` →
  D-06): el FEL usa factores fijos en código, mientras Ventas/Compras leen el
  porcentaje de `tax_impuestos`.
- **CPBS por defecto fijo** (`81`/`8111`): correcto para servicios informáticos,
  pero debe parametrizarse por producto para otros giros.
- **Lectura del CUFE/estado tolerante a varias formas de respuesta** (`$resp['codigo']`,
  `$resp['EnviarResult']['codigo']`, `resultado == 'Procesado'`): es defensivo, pero
  conviene confirmar contra la documentación vigente del PAC que cubre todos los
  casos de éxito/fallo. ⚠ Pendiente de verificar.
