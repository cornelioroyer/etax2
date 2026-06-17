# Arquitectura del proyecto

Este documento describe cómo está organizado el código de eTax2 (Laravel 12) y
cómo fluye una petición desde el navegador hasta la base de datos.

---

## 1. Organización general (Laravel)

El proyecto sigue la estructura estándar de Laravel, con la lógica de negocio
concentrada en controladores de administración, modelos Eloquent y un pequeño
conjunto de *services*.

```
app/
  Http/
    Controllers/
      Admin/        ← la gran mayoría de la lógica (más de 100 controladores)
      Auth/         ← autenticación (Breeze + Google)
      Concerns/     ← traits reutilizables por los controladores
    Middleware/     ← EnsureUserIsAdmin, EstablecerCompaniaActiva
    Requests/       ← Form Requests (validación)
  Models/           ← ~180 modelos Eloquent
  Services/         ← lógica de dominio reutilizable (ver §4)
  Observers/        ← AsientoObserver, AuditObserver
  Providers/        ← AppServiceProvider (permisos, auditoría, directivas Blade)
  Support/          ← Fechas (helpers de zona horaria Panamá)
  Exports/ Imports/ ← Excel (maatwebsite/excel)
  Mail/             ← correos
database/
  migrations/       ← esquema para pruebas + cambios incrementales (ver modelo-datos.md)
routes/
  web.php           ← casi todas las rutas (≈786 líneas)
  auth.php          ← rutas de autenticación
  console.php       ← comandos de consola
resources/views/    ← plantillas Blade
config/             ← configuración (filesystems con S3, permission, etc.)
```

## 2. Capas y responsabilidades

eTax2 **no** usa una arquitectura de capas estricta (repositorios, DTOs, etc.). El
patrón observado es el clásico de Laravel, "controlador robusto + service para lo
transversal":

1. **Rutas** (`routes/web.php`) — definen URL, middleware y a qué controlador van.
2. **Middleware** — autenticación, verificación, permisos y compañía activa.
3. **Form Requests / validación en el controlador** — validan la entrada.
4. **Controladores** (`Admin/*`) — orquestan: validan, calculan totales, abren
   transacciones, crean documentos y disparan los asientos contables.
5. **Models (Eloquent)** — acceso a datos, relaciones, y algo de lógica de dominio
   (numeración consecutiva, *scopes*, helpers de estado).
6. **Services** — lógica que se comparte entre módulos (asientos automáticos,
   cierre anual, FEL, sincronización de bancos, etc.).
7. **Observers** — efectos secundarios automáticos (auditoría, espejo de bancos).
8. **Base de datos (PostgreSQL)** — además de almacenar, **valida** vía triggers
   PL/pgSQL el cuadre y el control de período de los asientos.

## 3. Multi-compañía (concepto central)

eTax2 es multi-empresa. Casi todas las tablas tienen `compania_id`, y la "compañía
activa" se guarda en la sesión del usuario.

- El middleware **`EstablecerCompaniaActiva`** (registrado en el grupo `web` en
  `bootstrap/app.php`) fija la compañía activa de la sesión y se la comunica a
  spatie/permission como "team" (los permisos son por compañía).
- Los controladores obtienen la compañía activa con el trait
  **`ConCompaniaActiva`** (`companiaActivaId($request)`), que además verifica que
  el usuario tenga acceso a esa compañía (o sea super-admin).
- La **compañía 1** es la del sistema (WIN SOFT CORP) y tiene reglas especiales de
  permisos (ver `seguridad-auditoria.md`).

## 4. Services (lógica de dominio)

En `app/Services/`:

| Service | Responsabilidad |
|---|---|
| `AsientoAutomatico` | Crea y postea asientos de partida doble desde los módulos (CxC, CxP, bancos…). Valida cuadre y período. |
| `CierreAnual` | Calcula y postea el asiento de cierre del ejercicio (período de ajuste, mes 13). |
| `FelService` | Cliente SOAP del PAC The Factory HKA (enviar, anular, consultar, descargar PDF/XML). |
| `FelDocumentoBuilder` | Construye el documento electrónico (ítems, ITBMS, cliente, totales) que espera el PAC. |
| `FelConfiguracionDefault` | Detecta si la configuración FEL es la demo y aplica valores por defecto. |
| `PlantillaCuentas` | Copia un plan de cuentas plantilla (p. ej. `PA_ISR`) a una compañía nueva. |
| `BancoSync` | Mantiene el módulo de Bancos como espejo de las cuentas contables bancarias. |
| `PresupuestoReal` | Compara presupuesto vs. real. |

## 5. Routing

Casi todo cuelga del grupo `admin` con prefijo `/admin` y nombre `admin.*`. El
acceso se controla con middleware de permisos de spatie (`permission:...`):

```php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(...);

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:contabilidad.ver')->group(function () {
        Route::post('asientos/{asiento}/postear', [AsientoController::class, 'postear']);
        // ...
    });
});
```

- `auth` + `verified` — usuario autenticado y con correo verificado.
- `admin` (`EnsureUserIsAdmin`) — para rutas reservadas a super-admin (p. ej. auditoría global).
- `permission:<clave>` — permiso específico por compañía (spatie).

## 6. Flujo de una petición (ejemplo: emitir una factura electrónica)

Tomando como ejemplo `FacturaFelController@store` (crear y enviar una factura FEL):

1. **Ruta** → `POST /admin/fel` con middleware `auth` y permiso `fel.gestionar`.
2. **Middleware** `EstablecerCompaniaActiva` fija la compañía activa en sesión.
3. **Autorización** dentro del controlador: `abort_unless($request->user()->can('fel.gestionar'), 403)`.
4. **Validación** de los datos del formulario (tipo de documento, cliente, ítems,
   tasas ITBMS permitidas `00/01/02/03`, forma de pago…).
5. **Reserva del consecutivo fiscal** dentro de una transacción
   (`$config->siguienteNumeroFiscal()`), con bloqueo de fila.
6. **Construcción** del documento electrónico con `FelDocumentoBuilder`.
7. **Persistencia local**: se crea `FelDocumento` + `FelDocumentoDetalle` en estado
   `PENDIENTE`.
8. **Envío al PAC**: `FelService->enviar($documento)` (SOAP a The Factory HKA).
9. **Resultado**: si la DGI autoriza, se guardan `cufe`, `qr` y estado `AUTORIZADO`;
   si no, estado `RECHAZADO` con el mensaje de error. Cada intento se registra en
   `fel_eventos`.
10. **Auditoría**: en paralelo, `AuditObserver` registra la creación del documento
    en `audit_actividad`.

Para un documento contable (ej. factura de venta), el flujo equivalente termina en
`AsientoAutomatico->postear(...)`, que crea el asiento dentro de una transacción y,
al pasar a `POSTEADO`, dispara el **trigger PL/pgSQL** que revalida cuadre y período.

## 7. Front-end

Vistas en Blade bajo `resources/views`, compiladas con Vite + TailwindCSS. Dos
directivas Blade propias formatean fechas en hora de Panamá:

- `@fecha($valor)` — fecha pura.
- `@fechaHora($valor)` — fecha y hora (GMT-5).

## 8. Notas y pendientes

- ⚠ **No implementado / pendiente de verificar:** no se observó una capa de API
  REST documentada; la aplicación es principalmente server-rendered (Blade).
- ⚠ La lógica de negocio está mayormente en los controladores `Admin/*`; algunos
  son extensos. No es un defecto, pero conviene tenerlo en cuenta para mantenimiento.
