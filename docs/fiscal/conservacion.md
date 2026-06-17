# Conservación de documentos (retención 5 años)

La normativa de la DGI exige conservar los documentos fiscales por **5 años**. Este
documento explica qué hace el sistema hoy frente a esa obligación y qué queda
pendiente.

> ⚠ **Aviso clave.** La retención de 5 años es una **obligación legal**. El código
> del repositorio **no implementa** una política automática de retención/archivado.
> Lo que existe es la información almacenada y la base para construir esa política.

---

## 1. Qué conserva el sistema hoy (en base de datos)

eTax2 guarda de forma permanente en la base de datos:

- **Documentos electrónicos** (`fel_documentos`): tipo, número, fecha, cliente,
  montos, **estado**, **CUFE**, **QR**, y rutas a archivos (`xml_path`, `pdf_path`).
- **Detalle** de cada documento (`fel_documentos_detalle`).
- **Eventos** con el PAC (`fel_eventos`): cada envío/anulación con la **respuesta
  íntegra de la DGI en JSON**.
- **Contabilidad**: asientos, detalle y saldos (`cgl_*`).
- **Bitácora de auditoría** (`audit_actividad`, `audit_reaperturas`): quién hizo qué
  y cuándo.

Mientras la base de datos se conserve (y se respalde), esta información permanece.

## 2. Archivos XML/PDF de las facturas

- El **XML firmado** y el **CAFE (PDF)** se pueden **descargar del PAC** bajo demanda
  (`descargaXML`, `descargaPDF`).
- Las columnas `xml_path` y `pdf_path` están previstas para guardar la ubicación de
  esos archivos (presumiblemente en S3).

> ⚠ **Pendiente de verificar / posible brecha:** no se observó en el repositorio el
> código que **persiste automáticamente** una copia del XML/PDF en
> almacenamiento propio (S3) y rellena `xml_path`/`pdf_path`. Si hoy los documentos
> solo se obtienen del PAC bajo demanda, la conservación a 5 años dependería de la
> disponibilidad del PAC, lo cual es un riesgo. **Recomendación:** guardar una copia
> propia de cada XML firmado y CAFE al autorizarse el documento.

## 3. Qué NO está implementado (y se necesita)

- ❌ **Política de retención de 5 años** como lógica de la aplicación o de
  infraestructura (no hay reglas de ciclo de vida ni controles de no-borrado en el
  código).
- ❌ **Backups con retención de largo plazo** (corresponde a RDS/S3; ver
  `tecnico/respaldos-aws.md`).
- ❌ **Protección contra borrado** de documentos fiscales antes de cumplir el plazo.

## 4. Recomendaciones para cumplir la retención de 5 años

> Propuesta a validar con el contador y con quien administre la infraestructura.

1. **Persistir copia propia** del XML firmado y del CAFE (PDF) en **S3** al
   autorizarse cada documento, llenando `xml_path`/`pdf_path`.
2. **S3 con versioning + reglas de ciclo de vida** que conserven los objetos al
   menos 5 años; evaluar **Object Lock** (modo cumplimiento) para impedir borrados.
3. **Backups de la base de datos** (RDS) con retención que cubra los 5 años
   (snapshots de largo plazo), y **pruebas de restauración** periódicas.
4. **Política de retención de la bitácora** `audit_actividad` acorde (no purgarla
   antes del plazo).
5. **No permitir el borrado físico** de documentos fiscales; usar siempre anulación
   lógica (estado `ANULADO`), como ya hace el sistema con las facturas.
6. **Custodiar `APP_KEY`** (sin ella no se descifran los tokens del PAC).

## 5. A favor: el sistema ya evita el borrado de documentos fiscales

Como práctica positiva, las facturas electrónicas **no se eliminan**: se **anulan**
(estado `ANULADO`) ante la DGI mediante el PAC, conservando el registro y su
historial de eventos. Esto es coherente con la obligación de conservación.

> ⚠ **Verificar con contador:** el plazo exacto (inicio del cómputo de los 5 años),
> los tipos de documentos cubiertos por la obligación y cualquier requisito de
> **formato** o **accesibilidad** de los documentos conservados que exija la DGI.
