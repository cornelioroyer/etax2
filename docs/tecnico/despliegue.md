# Despliegue y configuración

Cómo se despliega y configura eTax2. Lo que está confirmado por el código se marca
como tal; lo que es práctica recomendada de infraestructura se marca como propuesta.

---

## 1. Requisitos del servidor

- **PHP 8.2+** con extensiones de Laravel, **`soap`** (cliente FEL) y
  **`pdo_pgsql`** (PostgreSQL).
- **Composer**.
- **Node.js + npm** (para compilar el front-end con Vite).
- **PostgreSQL** (dev/prod). Las pruebas usan SQLite.
- Servidor web (Nginx/Apache) apuntando a `public/`.

## 2. Componentes que deben ejecutarse

Según `composer.json` (script `dev`) y la configuración por defecto, el sistema usa:

- **Servidor web/PHP-FPM** sirviendo la app.
- **Cola de trabajos** (`QUEUE_CONNECTION=database`): requiere un worker
  (`php artisan queue:work` / `queue:listen`).
- **Sesiones y caché en base de datos** (`SESSION_DRIVER=database`,
  `CACHE_STORE=database`).
- **Vite** para compilar assets (`npm run build` en despliegue).

> ⚠ Como las colas usan la base de datos, en producción debe haber un **worker de
> cola supervisado** (p. ej. con supervisor/systemd). No se encontró configuración
> de supervisor en el repositorio. ⚠ Pendiente de definir en infraestructura.

## 3. Pasos de despliegue (base)

```bash
# 1. Obtener el código
git pull

# 2. Dependencias de PHP (sin dev en producción)
composer install --no-dev --optimize-autoloader

# 3. Variables de entorno
#    Configurar .env (ver §4). NO commitear .env.
php artisan key:generate   # solo la primera vez

# 4. Base de datos
php artisan migrate --force
#    ⚠ Ver nota sobre el "esquema maestro" más abajo.

# 5. Front-end
npm ci
npm run build

# 6. Cachés de Laravel (recomendado en producción)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Worker de cola (supervisado)
php artisan queue:work --tries=1
```

> ⚠ **Esquema maestro.** `php artisan migrate` **no** crea por sí solo todo el
> esquema: muchas tablas y los triggers contables viven en un *esquema maestro*
> externo (ver `DECISIONES.md` → D-01 y `tecnico/modelo-datos.md`). Un despliegue
> nuevo requiere aplicar ese esquema maestro **además** de las migraciones.
> Confirmar el procedimiento con quien mantiene la base de datos.

## 4. Variables de entorno (.env)

Basado en `.env.example`. Para producción, ajustar al menos:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio>

DB_CONNECTION=pgsql
DB_HOST=<endpoint RDS>
DB_PORT=5432
DB_DATABASE=<db>
DB_USERNAME=<user>
DB_PASSWORD=<secreto>

# Almacenamiento S3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...

# Inicio de sesión con Google
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback

# Correo (ajustar a un proveedor real; por defecto es 'log')
MAIL_MAILER=...

# Soporte/chat (opcional)
CHATWOOT_BASE_URL=...
CHATWOOT_WEBSITE_TOKEN=...
```

> El `.env.example` trae `DB_CONNECTION=sqlite` y `MAIL_MAILER=log` por
> conveniencia de desarrollo. **En producción deben cambiarse** a PostgreSQL y a un
> mailer real.

### Configuración del PAC (FEL)
Los tokens de The Factory HKA **no** van en `.env`: se cargan por compañía desde la
interfaz y se guardan cifrados en `fel_configuracion`. El cifrado depende de
`APP_KEY`, que **debe custodiarse** (ver `DECISIONES.md` → D-05).

## 5. Salud del servicio

`bootstrap/app.php` expone un endpoint de salud en **`/up`** (health check de
Laravel), útil para balanceadores y monitoreo.

## 6. Arquitectura de despliegue en AWS (propuesta, a validar)

> Sección de recomendación; no descrita en el código.

- Aplicación en EC2 / contenedores (ECS) detrás de un balanceador.
- **Amazon RDS for PostgreSQL** para la base de datos.
- **Amazon S3** para archivos (documentos fiscales, logos, adjuntos).
- Worker de cola en una instancia/servicio supervisado.
- Secretos en AWS Secrets Manager / SSM Parameter Store.
- Backups y retención según `tecnico/respaldos-aws.md`.

## 7. Verificaciones post-despliegue

- `/up` responde correctamente.
- Login (incluido Google) funciona.
- Crear una compañía aplica el plan de cuentas plantilla (ver
  `funcional/plan-de-cuentas.md`).
- En FEL, `foliosRestantes()` responde (prueba de conexión con el PAC) en el
  ambiente correcto (PRUEBAS antes de PRODUCCION).
- Postear un asiento de prueba cuadra y respeta el período (valida que el trigger
  esté activo).

> ⚠ **No implementado / pendiente de verificar:** pipeline de CI/CD, scripts de
> despliegue automatizado y configuración de supervisor no están en el repositorio.
