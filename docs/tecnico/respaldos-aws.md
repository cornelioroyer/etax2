# Respaldos y AWS

Estrategia de backup y retención de documentos. **Importante:** este documento
distingue con claridad entre lo que **está en el código** y lo que es
**configuración de infraestructura no presente en el repositorio**.

---

## 1. Qué hay en el código (confirmado)

### Almacenamiento S3
`config/filesystems.php` incluye el driver `s3` de Laravel, y `.env.example` define
las variables:

```
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Esto habilita guardar archivos en un bucket de Amazon S3 (por ejemplo, PDFs y XML
de facturas electrónicas, logos, adjuntos). El modelo `FelDocumento` tiene columnas
`xml_path` y `pdf_path` para apuntar a esos archivos.

> ⚠ **Pendiente de verificar:** no se observó en el repositorio el código que
> efectivamente **sube** el XML/PDF de cada factura a S3 y rellena `xml_path`/
> `pdf_path`. Hoy el PDF/XML se descargan del PAC bajo demanda (`descargaPDF`/
> `descargaXML`). Confirmar si se persiste una copia en S3 o si siempre se pide al PAC.

### Base de datos
El sistema usa **PostgreSQL** en dev/prod (confirmado por funciones PL/pgSQL,
*advisory locks* y `timestampTz`). Es compatible con **Amazon RDS for PostgreSQL**.

## 2. Qué NO está en el código (infraestructura)

Las siguientes piezas, que el equipo describe como parte del stack, **no aparecen
en el repositorio** y corresponden a configuración de infraestructura en AWS:

- ❌ **Estrategia de backups de RDS** (snapshots automáticos, frecuencia, ventana,
  *point-in-time recovery*). No hay scripts ni configuración en el repo.
- ❌ **Política de versionado/retención de S3** (lifecycle rules, versioning,
  *object lock*). No hay configuración en el repo.
- ❌ **Automatización de respaldos** (cron, jobs, comandos artisan de backup). No se
  encontró ningún comando de respaldo en `routes/console.php` ni paquete tipo
  `spatie/laravel-backup`.
- ❌ **Retención de 5 años** exigida por la DGI implementada como lógica de la
  aplicación. No hay código que aplique o verifique ese plazo.

> ⚠ **No implementado / pendiente de verificar.** Todo lo anterior debe definirse y
> documentarse a nivel de infraestructura AWS, fuera de este código. Ver propuesta
> en §3 y la parte fiscal en `fiscal/conservacion.md`.

## 3. Estrategia recomendada (propuesta, a validar)

> Esta sección es una **recomendación**, no una descripción del código. Sirve como
> punto de partida para que el equipo defina la política real.

### Base de datos (Amazon RDS for PostgreSQL)
- Activar **backups automáticos** con retención (RDS permite hasta 35 días) y
  **snapshots manuales/programados** de largo plazo para cumplir los 5 años.
- Activar **point-in-time recovery**.
- Considerar copiar snapshots a otra región para resiliencia.
- Probar **restauraciones periódicas** (un backup no probado no es un backup).

### Archivos (Amazon S3)
- Activar **versioning** del bucket.
- Definir **reglas de ciclo de vida** que conserven los objetos al menos **5 años**
  (transición a clases de almacenamiento más baratas como S3 Glacier para
  documentos antiguos).
- Evaluar **S3 Object Lock** (modo cumplimiento) para impedir el borrado de
  documentos fiscales antes de cumplir el plazo legal.
- Cifrado en reposo (SSE-S3 o SSE-KMS).

### Documentos fiscales (FEL)
- Persistir en S3 una copia del **XML firmado** y del **CAFE (PDF)** de cada
  documento autorizado, llenando `xml_path`/`pdf_path`, para no depender únicamente
  de la disponibilidad del PAC durante 5 años.

## 4. Custodia de secretos

- `APP_KEY` cifra los tokens del PAC (`fel_configuracion`). Si se pierde, esos
  tokens dejan de poder descifrarse. **Debe respaldarse y custodiarse** junto con la
  estrategia de backups (idealmente en AWS Secrets Manager o similar).
  Ver `DECISIONES.md` → D-05.
- Las credenciales AWS y del PAC no deben quedar en el repositorio.

## 5. Resumen

| Elemento | ¿En el código? | Estado |
|---|---|---|
| Driver S3 configurado | Sí | Confirmado |
| Variables AWS en `.env.example` | Sí | Confirmado |
| PostgreSQL (compatible RDS) | Sí | Confirmado |
| Subida automática de XML/PDF a S3 | No claro | ⚠ Verificar |
| Backups RDS / retención | No | ⚠ Infraestructura, no implementado en repo |
| Lifecycle / retención 5 años S3 | No | ⚠ Infraestructura, no implementado en repo |
| Automatización de respaldos | No | ⚠ No implementado en repo |
