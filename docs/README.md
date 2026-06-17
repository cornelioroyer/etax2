# eTax2 — Documentación del proyecto

> ERP contable multi-compañía para Panamá, con facturación electrónica (FEL) ante la DGI.

Esta documentación describe **lo que el código del proyecto hace hoy**. Todo lo
que aparece aquí se basó en la lectura del repositorio. Cuando algo no está
implementado en el código o requiere validación de un contador, se marca
explícitamente.

> ⚠ **Verificar con contador** y **No implementado / pendiente de verificar** son
> las dos etiquetas que usamos en estos documentos. Préstales atención: indican
> puntos que NO debes dar por ciertos sin revisarlos.

---

## 1. Visión general

eTax2 es una plataforma de gestión administrativa y contable construida sobre
Laravel. Aunque el núcleo es un **sistema contable clásico** (plan de cuentas,
asientos de partida doble, períodos, cierre), el proyecto está organizado como
una suite multi-módulo y **multi-compañía**: una misma instalación atiende a
varias empresas, cada una con su propio plan de cuentas, su numeración y su
configuración fiscal.

Sobre ese núcleo contable se apoyan módulos operativos y verticales:

- **Núcleo contable (CGL):** cuentas, diarios, períodos, asientos, saldos, cierre anual.
- **Fiscal / FEL:** facturación electrónica ante la DGI a través del PAC *The Factory HKA*.
- **Ventas, Compras, CxC, CxP.**
- **Bancos y Caja** (incluye conciliación y arqueos).
- **Inventario** y **Activos Fijos**.
- **Presupuestos (Budget).**
- **Verticales:** Taller, Propiedad Horizontal (PH) y Educación, que comparten la contabilidad.

## 2. Stack tecnológico

| Componente | Tecnología (según el código) |
|---|---|
| Lenguaje | PHP 8.2 |
| Framework | Laravel 12 |
| Base de datos | PostgreSQL (dev/prod) · SQLite (pruebas automáticas) |
| Permisos | spatie/laravel-permission |
| Autenticación | Laravel Breeze + Google (laravel/socialite) |
| PDF | barryvdh/laravel-dompdf |
| Excel (import/export) | maatwebsite/excel |
| Front-end | Blade + Vite + TailwindCSS |
| Infraestructura | AWS (configuración de almacenamiento S3 presente) |

> ⚠ El stack indicado por el equipo incluye "PostgreSQL + AWS". El código
> **confirma** PostgreSQL (hay funciones PL/pgSQL, *advisory locks* y `timestampTz`)
> y trae configuración de **AWS S3** en `config/filesystems.php` y `.env.example`.
> La parte de **RDS, backups y retención** no está implementada en el repositorio
> (es configuración de infraestructura). Ver `tecnico/respaldos-aws.md`.

## 3. Requisitos

- PHP 8.2 o superior con las extensiones habituales de Laravel, **más `soap`**
  (el cliente FEL usa `SoapClient`) y el driver de PostgreSQL (`pdo_pgsql`).
- Composer.
- Node.js + npm (para compilar el front-end con Vite).
- PostgreSQL (para dev/prod). Las pruebas automáticas corren sobre SQLite.

## 4. Cómo levantar el proyecto localmente

El `composer.json` define dos scripts útiles:

1. **Instalación inicial** (`composer setup`): instala dependencias, copia
   `.env`, genera la clave de la app, corre las migraciones y compila el front-end.

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   # Configura la conexión a PostgreSQL en .env antes de migrar (ver nota abajo)
   php artisan migrate
   npm install
   npm run build
   ```

2. **Desarrollo** (`composer dev`): levanta en paralelo el servidor, la cola de
   trabajos, los logs en vivo y Vite.

   ```bash
   composer dev
   ```

> ⚠ **Importante sobre las migraciones.** El repositorio contiene ~29 migraciones,
> pero el sistema define **~180 modelos**. Varias tablas centrales
> (`cgl_*`, `tax_impuestos`, gran parte de inventario y de los verticales) se crean
> con guardas `if (! Schema::hasTable(...))` y sus comentarios indican que en
> dev/prod **"ya existen en el esquema maestro"**. Es decir, **parte del esquema y
> los triggers de PostgreSQL viven fuera de estas migraciones**. Para levantar un
> entorno dev/prod completo necesitas ese esquema maestro además de las migraciones.
> Ver `tecnico/modelo-datos.md` y `DECISIONES.md`.

### Variables de entorno relevantes

Además de las estándar de Laravel, el `.env.example` incluye:

- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` — almacenamiento S3.
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` — inicio de sesión con Google.
- `CHATWOOT_BASE_URL`, `CHATWOOT_WEBSITE_TOKEN` — widget de soporte/chat.

La configuración del PAC (tokens de The Factory HKA) **no va en `.env`**: se guarda
por compañía en la tabla `fel_configuracion`, con los tokens cifrados. Ver
`tecnico/integracion-sfep.md`.

## 5. Estructura de esta documentación

```
/docs
  README.md            ← este archivo
  DECISIONES.md        ← decisiones técnicas y contables detectadas en el código
  /tecnico
    arquitectura.md
    modelo-datos.md
    asientos-contables.md
    integracion-sfep.md
    respaldos-aws.md
    despliegue.md
    seguridad-auditoria.md
  /funcional
    plan-de-cuentas.md
    ciclos-contables.md
    cierre-periodo.md
    reportes.md
  /fiscal
    itbms.md
    firma-electronica.md
    conservacion.md
  /usuario
    manual-contador.md
    manual-admin.md
  /comunicacion
    mensajes-whatsapp.md
```

## 6. Convenciones del proyecto (observadas en el código)

- **Multi-compañía:** casi todas las tablas tienen `compania_id`. La compañía
  activa se guarda en sesión y la fija el middleware `EstablecerCompaniaActiva`.
- **Auditoría automática:** todos los modelos del dominio se observan y registran
  en la bitácora `audit_actividad` (crear/editar/borrar + login/logout).
- **Trazabilidad:** casi todas las tablas tienen columnas `created_by` y
  `updated_by` (correo del usuario).
- **Moneda:** los importes se manejan en Balboas (B/.) con `decimal(18,2)`.
- **Zona horaria:** las fechas/horas se muestran en hora de Panamá (GMT-5)
  mediante las directivas Blade `@fecha` y `@fechaHora`.
