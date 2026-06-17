# Seguridad y auditoría

Control de acceso, registro de auditoría (quién hizo qué y cuándo) y logs. Basado en
`app/Providers/AppServiceProvider.php`, `app/Observers/AuditObserver.php`,
`app/Models/AuditActividad.php`, los middleware en `app/Http/Middleware/` y la
configuración de spatie/permission.

---

## 1. Autenticación

- Basada en **Laravel Breeze** (login, registro, recuperación de contraseña,
  verificación de correo, confirmación de contraseña). Rutas en `routes/auth.php`.
- **Inicio de sesión con Google** vía `laravel/socialite`
  (`GoogleLoginController`).
- Las rutas protegidas usan los middleware `auth` y `verified` (correo verificado).
- Usuarios: la migración `2026_06_08_210000` agrega a `users` los campos
  **`is_admin`** (super-admin de la plataforma) e **`is_active`**. El primer usuario
  creado queda como `is_admin`.

## 2. Autorización (permisos por compañía)

Se usa **spatie/laravel-permission** en modo multi-compañía ("teams"): los roles y
permisos se evalúan **en el contexto de la compañía activa**.

El control de orden se centraliza en un único `Gate::before` en `AppServiceProvider`
(reemplaza el auto-registro de spatie para controlar la precedencia):

1. **Super-admin** (`is_admin`) → pasa todas las verificaciones.
2. **Compañía 1 (sistema, WIN SOFT CORP)** → los usuarios que no son super-admin
   solo pueden **ver** (cualquier permiso que no termine en `.ver` se deniega),
   salvo `companias.crear`.
3. **Resolución estándar** de permisos por rol/compañía (réplica del comportamiento
   de spatie).

Los permisos siguen la convención `<recurso>.<acción>` (p. ej. `contabilidad.ver`,
`asientos.postear`, `fel.gestionar`, `usuarios_compania.gestionar`). Las rutas los
exigen con `middleware('permission:<clave>')`.

### Middleware propios
- **`EnsureUserIsAdmin`** (alias `admin`): restringe rutas a super-admin (p. ej.
  auditoría global).
- **`EstablecerCompaniaActiva`**: fija la compañía activa de la sesión y la
  comunica a spatie como "team". Está añadido al grupo `web` en `bootstrap/app.php`.

### Acceso a datos por compañía
Los controladores validan, además del permiso, que el usuario tenga acceso a la
compañía activa: con el trait `ConCompaniaActiva` o con verificaciones del tipo
`abort_unless($user->is_admin || $user->companiasAccesibles()->contains('id', $id), 403)`.
Esto evita que un usuario vea datos de una compañía a la que no pertenece.

## 3. Registro de auditoría (bitácora)

### Cómo funciona
En el arranque (`AppServiceProvider::registrarAuditoria()`), el sistema recorre
**todos** los modelos de `app/Models` y les engancha `AuditObserver`
(excepto el propio `AuditActividad`, para no auditarse a sí mismo). Así, **cualquier
creación, edición o borrado** de cualquier entidad del dominio queda registrada
automáticamente, sin tener que añadir código en cada módulo.

Además se escuchan eventos de autenticación:
- **`login`** — inicio de sesión exitoso.
- **`logout`** — cierre de sesión.
- **`login_fallido`** — intento de acceso fallido (guarda el correo intentado).

### Qué se guarda (`audit_actividad`)
Por cada evento:

| Campo | Contenido |
|---|---|
| compania_id | compañía afectada (o de la sesión activa) |
| usuario_id / usuario_nombre | quién realizó la acción |
| evento | created / updated / deleted / login / logout / login_fallido |
| entidad / entidad_tabla / entidad_id | qué entidad se afectó (p. ej. `Asiento`, `cgl_asientos`, id) |
| descripcion | etiqueta legible de la fila (nombre, código, número…) |
| valores_anteriores / valores_nuevos | **diff antes/después** (JSON) |
| url / metodo | ruta y verbo HTTP |
| ip / user_agent | origen de la petición |
| created_at | cuándo |

Detalles de comportamiento (de `AuditObserver`):
- En **updated**, solo se registra si hubo cambios sustantivos; si lo único que
  cambió fueron `created_at`/`updated_at`, **no** se registra.
- Los valores se "depuran" (`AuditActividad::depurar`) antes de guardarse, para no
  almacenar campos sensibles/ruidosos.

### Reaperturas de período
Existe una tabla específica **`audit_reaperturas`** (migración
`2026_06_14_000003`) para auditar la reapertura de períodos/ejercicios contables,
una acción especialmente sensible.

## 4. Trazabilidad a nivel de fila

Independientemente de la bitácora, casi todas las tablas guardan `created_by` y
`updated_by` (correo del usuario), de modo que cada registro lleva quién lo creó y
quién lo modificó por última vez.

## 5. Protección de datos sensibles

- **Tokens del PAC** (`token_empresa`, `token_password`) se guardan **cifrados**
  (cast `encrypted`) en `fel_configuracion`.
- **Contraseñas** de usuario con hashing bcrypt (`BCRYPT_ROUNDS=12`).
- ⚠ La fortaleza del cifrado depende de la custodia de `APP_KEY` (ver
  `DECISIONES.md` → D-05).

## 6. Logs de aplicación

Logging estándar de Laravel (`LOG_CHANNEL=stack`). No sustituye a la bitácora de
auditoría: los logs sirven para diagnóstico técnico; `audit_actividad` es el
registro funcional de "quién hizo qué".

## 7. Inconsistencias / pendientes

- ⚠ **No implementado / pendiente de verificar:** no se observó una política de
  **expiración de sesión reforzada**, **bloqueo por intentos fallidos** ni
  **doble factor (2FA)**. Los intentos fallidos se *registran* (`login_fallido`),
  pero no se observó un mecanismo que **bloquee** la cuenta tras N intentos.
- ⚠ **Retención de la bitácora:** `audit_actividad` puede crecer mucho (audita todos
  los modelos). Conviene definir una política de archivado/retención acorde con la
  conservación de 5 años (ver `fiscal/conservacion.md`).
