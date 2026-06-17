# Manual del administrador — eTax2

Guía para quien administra el sistema: empresas, usuarios, permisos, configuración
fiscal y puesta a punto. Escrita en lenguaje sencillo. Algunas tareas técnicas
(servidores, respaldos) requieren al equipo de tecnología; aquí se indica cuándo.

---

## 1. Roles principales

- **Super-administrador**: tiene control total sobre toda la plataforma y todas las
  empresas. (Internamente es el usuario marcado como administrador del sistema.)
- **Administrador de empresa**: gestiona una o varias empresas según los permisos
  que se le asignen.
- **Usuarios**: contadores y demás personal, con permisos por empresa.

> La empresa "del sistema" (la primera, de la casa de software) está protegida: los
> usuarios que no son super-administradores solo pueden **verla**, no modificarla.

## 2. Crear y configurar una empresa

1. Ve a **Compañías → Nueva** y completa los datos (nombre, RUC, DV, dirección,
   representante legal, etc.).
2. Al crearse, el sistema le copia automáticamente un **plan de cuentas base**
   (plantilla ISR Panamá). Si no se aplicó, puedes aplicarla desde el plan de cuentas.
3. Revisa/ajusta el **plan de cuentas** de la empresa.
4. Configura las **cuentas por defecto** (ver sección 4): son las que usan los
   módulos para contabilizar automáticamente.
5. Configura la **facturación electrónica** (ver sección 5).

## 3. Usuarios y permisos

1. Ve a **Usuarios de compañía**.
2. Crea o invita usuarios y asígnalos a la empresa correspondiente.
3. Asigna **permisos** por empresa (por ejemplo: ver contabilidad, postear asientos,
   gestionar facturación electrónica, etc.). Un mismo usuario puede tener permisos
   distintos en empresas distintas.

> ⚠ Importante: da a cada persona **solo los permisos que necesita**. Acciones
> sensibles como **postear/anular asientos**, **cerrar/reabrir períodos** o
> **gestionar la facturación electrónica** deberían estar limitadas a personal de
> confianza.

## 4. Cuentas por defecto (clave para la contabilidad automática)

Los módulos (ventas, compras, bancos, cierre, etc.) generan asientos usando "cuentas
por defecto". Por ejemplo, el **cierre anual** necesita la cuenta de **utilidades
retenidas**.

1. Ve a **Cuentas por defecto**.
2. Asigna una cuenta real a cada clave que usen los módulos activos.

> ⚠ Importante: si falta una cuenta por defecto, el módulo que la necesita fallará al
> contabilizar. Antes de poner una empresa en marcha, revisa que estén todas las que
> correspondan a los módulos que vas a usar. (Esto conviene hacerlo junto con el
> contador.)

## 5. Configurar la facturación electrónica (DGI)

El sistema emite facturas electrónicas a través de un proveedor autorizado (PAC).
Cada empresa necesita su propia configuración.

1. Ve a **Facturación electrónica → Configuración**.
2. Ingresa las **credenciales del proveedor** (token de empresa y contraseña). El
   sistema las guarda **cifradas**.
3. Define el **ambiente**:
   - **PRUEBAS**: para probar; estas facturas **no son válidas** ante la DGI.
   - **PRODUCCIÓN**: para emitir facturas reales.
4. Configura el **punto de facturación** y el **código de sucursal** si aplica.
5. Puedes usar la opción de **folios disponibles** como prueba de conexión con el
   proveedor.

> ⚠ Muy importante:
> - Cada empresa debe usar **sus propias credenciales** en producción. No uses las
>   credenciales de prueba para emitir facturas reales.
> - No cambies la clave técnica del sistema (la usa el equipo de tecnología) sin
>   coordinar: de ella depende poder leer las credenciales guardadas.

## 6. Períodos contables

- El administrador (o el contador con permiso) gestiona la **apertura y cierre** de
  períodos.
- La **reapertura** de un período queda **auditada** de forma especial (se registra
  quién y cuándo). Úsala con criterio, sobre todo si el período ya se declaró a la
  DGI.

## 7. Auditoría: quién hizo qué y cuándo

El sistema registra **automáticamente** toda la actividad: creación, edición y
borrado de registros, además de inicios de sesión (incluidos los intentos fallidos).

1. Ve a **Auditoría**.
2. Busca por usuario, tipo de acción o entidad afectada.
3. En el detalle verás los **valores antes y después** de cada cambio, la fecha, el
   usuario y desde dónde se hizo.

Úsalo para investigar discrepancias o para control interno.

## 8. Seguridad: buenas prácticas

- Mantén actualizada la lista de usuarios; desactiva los que ya no deban tener acceso.
- Revisa periódicamente los permisos.
- Vigila los **intentos de acceso fallidos** en la auditoría.

> ⚠ Pendientes conocidos (consultar con el equipo de tecnología): a la fecha el
> sistema **registra** los intentos fallidos pero no se observó un **bloqueo
> automático** de cuenta tras varios intentos, ni **doble factor (2FA)**. Si la
> empresa lo requiere, plantéalo al equipo.

## 9. Respaldos y conservación (coordinar con tecnología)

La obligación de la DGI de conservar documentos **5 años** depende de los respaldos
de la base de datos y del almacenamiento de archivos. Esto lo gestiona el área de
tecnología, no se configura desde la aplicación.

> ⚠ Importante: confirma con el equipo de tecnología que existan:
> - Respaldos automáticos de la base de datos con retención suficiente.
> - Conservación de los archivos (XML firmados y PDF) por al menos 5 años.
> - Pruebas de restauración de respaldos.
>
> (Detalle técnico en la documentación técnica: *respaldos-aws* y *conservacion*.)

## 10. Soporte

Si algo no funciona como se espera, reúne la información (empresa, usuario, qué
intentabas hacer, mensaje de error y hora) y repórtalo al equipo. La pantalla de
auditoría suele ayudar a reconstruir lo ocurrido.
