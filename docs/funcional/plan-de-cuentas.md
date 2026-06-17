# Plan de cuentas

Estructura del plan de cuentas en eTax2. Basado en `app/Models/CuentaContable.php`,
`app/Services/PlantillaCuentas.php` y las tablas `cgl_cuentas` / `cgl_tipos_cuenta`.

---

## 1. Qué es

Cada compañía tiene **su propio plan de cuentas** (tabla `cgl_cuentas`, filtrada por
`compania_id`). Es un árbol jerárquico de cuentas contables: las cuentas "padre"
agrupan y las cuentas "hoja" son las que reciben movimientos.

## 2. Estructura de una cuenta

Cada cuenta tiene:

- **Código** y **nombre** (el código es único dentro de la compañía).
- **Jerarquía**: `cuenta_padre_id` y `nivel`. Una cuenta puede tener cuentas hijas
  (relaciones `padre()` / `hijos()` en el modelo).
- **Tipo de cuenta** (`tipo_cuenta_id` → `cgl_tipos_cuenta`), que define la
  **naturaleza** (DÉBITO/CRÉDITO) y la **sección** (p. ej. activo, pasivo,
  patrimonio, ingreso, costo, gasto).
- **Naturaleza** propia (`naturaleza`: DEBITO o CREDITO).
- **`permite_movimiento`**: solo las cuentas hoja deberían permitir asientos; las
  cuentas de agrupación, no.
- **Banderas de exigencia en el detalle**:
  - `requiere_contacto` — obliga a indicar cliente/proveedor (típico en cuentas por
    cobrar/pagar).
  - `requiere_centro_costo` — obliga a centro de costo.
  - `requiere_proyecto` — obliga a proyecto.
- **`conciliable`** — cuentas que se concilian (p. ej. bancos).
- **`activa`** — si la cuenta está habilitada.
- **`renglon_isr`** — mapea la cuenta a un **renglón del formulario de ISR de la
  DGI**, para apoyar la preparación de la declaración de renta.

## 3. Tipos de cuenta (`cgl_tipos_cuenta`)

Catálogo que clasifica las cuentas por `codigo` (p. ej. ACTIVO, PASIVO,
PATRIMONIO, INGRESO, COSTO, GASTO), con su `naturaleza` y `seccion`. El cierre anual
se apoya en los tipos `INGRESO`, `COSTO` y `GASTO` para identificar las cuentas de
resultado (ver `cierre-periodo.md`).

> ⚠ **Verificar con contador:** el catálogo exacto de tipos y su mapeo a las
> secciones del balance y del estado de resultado debe revisarse contra el plan
> contable que use la empresa.

## 4. Plantilla por defecto (al crear una compañía)

El service `PlantillaCuentas` copia un plan de cuentas plantilla a una compañía
nueva. La plantilla por defecto es **`PA_ISR`**, descrita en el código como
*"plan de cuentas Formulario 2 DGI (ISR Panamá)"*.

Funcionamiento (`PlantillaCuentas::aplicar`):

1. No hace nada si la compañía **ya tiene** cuentas (no sobrescribe).
2. Busca la plantilla activa por código en `core_plantillas_cuentas`.
3. Copia cada cuenta de la plantilla a `cgl_cuentas` de la compañía, **reconstruyendo
   la jerarquía** (resuelve `cuenta_padre_id` por código) y asignando tipo,
   naturaleza, `permite_movimiento`, `conciliable` y `renglon_isr`.
4. Si la cuenta de la plantilla trae una **clave de cuenta por defecto**
   (`clave_default`), la registra en `core_cuentas_default` (ver §5).
5. Devuelve cuántas cuentas creó.

> ⚠ **Verificar con contador:** que el contenido de la plantilla `PA_ISR`
> (las cuentas, su numeración y el mapeo a renglones del Formulario ISR) esté
> actualizado conforme a la normativa vigente de la DGI. La plantilla vive en
> `core_plantillas_cuentas` / `core_plantillas_cuentas_detalle` (esquema maestro),
> no en este repositorio.

## 5. Cuentas por defecto (`core_cuentas_default`)

Para que los módulos puedan generar asientos automáticos, el sistema mapea **claves
contables** a cuentas concretas por compañía. Por ejemplo, la clave
`UTILIDADES_RETENIDAS` se usa en el cierre anual (`CuentaDefault::idPara(...)`).

Esto permite que un módulo diga "usa la cuenta de utilidades retenidas" sin conocer
el código específico que cada empresa le haya dado. La configuración de estas claves
se administra desde la interfaz (ver el manual del administrador) y se incluye al
aplicar la plantilla.

> ⚠ **Verificar:** que todas las claves por defecto necesarias para los módulos en
> uso (ventas, compras, ITBMS, bancos, cierre, etc.) estén configuradas en cada
> compañía. Si falta una clave, el módulo correspondiente fallará al intentar
> contabilizar (el cierre anual, por ejemplo, exige `UTILIDADES_RETENIDAS`).

## 6. Administración del plan de cuentas

Desde `CuentaContableController` se gestionan las cuentas (crear, editar, jerarquía).
La acción `aplicar-plantilla` (`cuentas-aplicar-plantilla`) aplica la plantilla a la
compañía. El acceso requiere el permiso `contabilidad.ver` (y los correspondientes
de gestión).
