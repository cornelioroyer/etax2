# Asientos contables y partida doble

Cómo genera el sistema los asientos, cómo garantiza el cuadre (débito = crédito) y
cómo usa las transacciones y triggers de PostgreSQL. Basado en
`app/Services/AsientoAutomatico.php`, `app/Models/Asiento.php`,
`app/Observers/AsientoObserver.php` y la función PL/pgSQL
`fn_validar_asiento_posteado()`.

---

## 1. Modelo de partida doble

Un asiento (`cgl_asientos`) tiene varias líneas (`cgl_asientos_detalle`). Cada
línea afecta una cuenta con un importe **al débito o al crédito**. La regla
fundamental: la suma de débitos debe igualar la suma de créditos.

Estados de un asiento (constantes en el modelo `Asiento`):

- `BORRADOR` — creado pero sin efecto en saldos.
- `POSTEADO` — confirmado; afecta saldos y reportes.
- `ANULADO` — reversado.

## 2. Generación automática desde los módulos

Los módulos (Ventas, Compras, CxC, CxP, Bancos, Cierre…) **no** escriben asientos a
mano: usan el service `AsientoAutomatico`. Su método principal:

```php
$asiento = $asientoAutomatico->postear(
    companiaId, fecha, descripcion, referencia,
    $lineas,            // [['cuenta_id'=>.., 'debito'=>.., 'credito'=>..], ...]
    origenModulo, origenTabla, origenId,
    $usuario
);
```

Pasos que ejecuta:

1. **Suma y valida el cuadre en PHP:** calcula total débito y total crédito
   (redondeados a 2 decimales). Si la diferencia supera **0.004** o el total es
   ≤ 0, lanza una `ValidationException` con el mensaje
   *"Asiento descuadrado: débito B/. X ≠ crédito B/. Y"*.
2. **Resuelve el período contable** de la fecha (`PeriodoContable::paraFecha`) y
   verifica que esté **ABIERTO**; si no, rechaza con el período y su estado.
3. **Crea la cabecera** en estado `BORRADOR` con su número consecutivo
   `AS-NNNNNN`, el diario general, el origen (módulo/tabla/id) y los totales.
4. **Crea las líneas** del detalle (número de línea, cuenta, contacto opcional,
   débito/crédito, y los importes en moneda local con tasa de cambio 1).
5. **Postea**: actualiza el asiento a `POSTEADO`, asignando período, usuario que
   postea y fecha de posteo. Este cambio de estado dispara el **trigger de
   PostgreSQL** (ver §5).

### Anulación
`AsientoAutomatico->anular($asiento, $usuario)` cambia el estado a `ANULADO` solo si
estaba `POSTEADO`. El módulo que originó el documento es responsable de invocar la
anulación cuando se reversa su documento.

## 3. Numeración consecutiva sin huecos ni duplicados

`Asiento::siguienteNumero($companiaId)` genera `AS-000001`, `AS-000002`, … Como
PostgreSQL no permite `FOR UPDATE` con agregados (`max`), se usa un **advisory lock
de transacción**:

```php
DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['asiento-'.$companiaId]);
```

Esto serializa la asignación del número entre usuarios concurrentes. **Debe
llamarse dentro de una transacción** (el código lo hace).

## 4. Uso de transacciones

La regla del proyecto (documentada en el propio service): *"Llamar siempre dentro
de una transacción: el módulo que lo invoca debe poder revertir su documento si el
asiento falla."*

Así, si la creación del asiento falla (por ejemplo, el trigger detecta un
descuadre), **toda la operación del módulo se revierte** (el documento de venta, la
factura, el pago, etc.), dejando la base de datos consistente. El cierre anual, por
ejemplo, envuelve toda la creación del asiento en `DB::transaction(...)`.

## 5. Control contable en la base de datos (trigger PL/pgSQL)

Además de la validación en PHP, PostgreSQL revalida con la función
`fn_validar_asiento_posteado()`. Cuando un asiento se inserta/actualiza a
`POSTEADO`, el trigger:

1. **Determina el período**: si el asiento ya trae asignado un **período de ajuste
   (mes > 12)** de la compañía, lo respeta (caso del cierre anual); de lo contrario
   lo deriva de la fecha entre los períodos operativos (mes ≤ 12). Asigna ese
   período al asiento.
2. **Verifica que exista el período** para esa fecha; si no, lanza
   *"no existe periodo contable para la fecha"*.
3. **Verifica que el período esté ABIERTO**; si no, lanza
   *"el periodo de la fecha … está … — no se puede postear"*.
4. **Verifica el cuadre** sumando el detalle: si `débito ≠ crédito`, lanza
   *"Asiento descuadrado"*. Si el total es 0, lanza *"sin líneas de detalle"*.
5. **Verifica coherencia de totales**: que `total_debito`/`total_credito` de la
   cabecera coincidan con la suma del detalle.
6. Fija `fecha_posteo` si no venía.

> Resultado: el cuadre y el control de período están garantizados en **dos capas**
> (PHP + base de datos). Aunque alguien intentara insertar un asiento descuadrado
> saltándose la aplicación, la base de datos lo rechazaría.

> ⚠ **Nota técnica importante.** La **función** del trigger se define en la
> migración `2026_06_14_000001_cierre_anual_periodo_ajuste.php`, pero la sentencia
> `CREATE TRIGGER` que la asocia a la tabla `cgl_asientos` **no está en el
> repositorio**: pertenece al esquema maestro (ver `DECISIONES.md` → D-01/D-02).
> Conviene **verificar en producción** que el trigger esté efectivamente creado y
> activo; si no lo estuviera, solo quedaría la validación de PHP.

## 6. Saldos

Los saldos por período y cuenta se mantienen en `cgl_saldos`. El cierre anual y los
reportes leen de esa tabla. La actualización de saldos al postear/anular forma
parte del control contable del esquema maestro.
⚠ **Pendiente de verificar:** confirmar dónde se actualiza `cgl_saldos` (trigger o
proceso) en producción.

## 7. Sincronización con Bancos

`AsientoObserver` detecta cuándo un asiento pasa a `POSTEADO` o `ANULADO` y llama a
`BancoSync` para reflejar/retirar el movimiento bancario correspondiente, en las
cuentas contables enlazadas a un banco. Crear el movimiento bancario **no** genera
otro asiento, evitando recursión. Ver `DECISIONES.md` → D-10.

## 8. Inconsistencias detectadas

- **Tolerancia de cuadre distinta entre PHP y base de datos.** En PHP se acepta una
  diferencia de hasta `0.004` (`abs($debito - $credito) > 0.004`), mientras que el
  trigger exige igualdad estricta (`v_debito <> v_credito`). En la práctica, como
  todos los importes se redondean a 2 decimales antes de sumar, ambos coinciden;
  pero la tolerancia de PHP podría, en un caso límite, permitir algo que el trigger
  rechace. ⚠ Verificar que el redondeo previo elimina por completo el caso.
- **`CREATE TRIGGER` fuera del repositorio** (ver §5). Riesgo de entorno donde la
  validación de base de datos no esté activa.
