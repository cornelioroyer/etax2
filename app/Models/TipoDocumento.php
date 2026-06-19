<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Maestro (auxiliar, tipo_documento) → comportamiento contable del submayor.
 *
 * Única fuente de verdad para "¿este documento suma o resta?", su prefijo de
 * numeración y si genera saldo. Los montos se graban positivos; el signo se
 * deriva de aquí. No reemplaza la partida doble del mayor (asientos).
 *
 * Resiliencia: si la tabla aún no se migró (o un tipo no está catalogado), se
 * cae a DEFAULTS —los mismos valores del seed—, así el sistema funciona igual
 * antes y después de correr la migración. El fallback vive en UN solo lugar.
 */
class TipoDocumento extends Model
{
    protected $table = 'core_tipos_documento';

    public $incrementing = false;
    protected $primaryKey = null;

    public const AUX_CXC = 'CXC';
    public const AUX_CXP = 'CXP';

    protected $fillable = [
        'auxiliar', 'tipo_documento', 'descripcion', 'signo',
        'naturaleza', 'cobrable', 'prefijo', 'reversa',
    ];

    protected function casts(): array
    {
        return [
            'signo'    => 'integer',
            'cobrable' => 'boolean',
            'reversa'  => 'boolean',
        ];
    }

    /**
     * Comportamiento por defecto, idéntico al seed. Es el fallback cuando la
     * tabla no existe todavía o el tipo no está catalogado.
     *
     * @var array<string, array<string, array{signo:int, naturaleza:string, cobrable:bool, prefijo:?string, reversa:bool, descripcion:string}>>
     */
    private const DEFAULTS = [
        self::AUX_CXC => [
            'FACTURA'      => ['signo' => 1,  'naturaleza' => 'CARGO', 'cobrable' => true,  'prefijo' => 'FC-', 'reversa' => false, 'descripcion' => 'Factura'],
            'NOTA_DEBITO'  => ['signo' => 1,  'naturaleza' => 'CARGO', 'cobrable' => true,  'prefijo' => 'ND-', 'reversa' => false, 'descripcion' => 'Nota de débito'],
            'NOTA_CREDITO' => ['signo' => -1, 'naturaleza' => 'ABONO', 'cobrable' => false, 'prefijo' => 'NC-', 'reversa' => true,  'descripcion' => 'Nota de crédito'],
            'REEMBOLSO'    => ['signo' => 1,  'naturaleza' => 'CARGO', 'cobrable' => true,  'prefijo' => 'RE-', 'reversa' => false, 'descripcion' => 'Reembolso'],
            'PAGO'         => ['signo' => -1, 'naturaleza' => 'ABONO', 'cobrable' => false, 'prefijo' => 'RC-', 'reversa' => false, 'descripcion' => 'Recibo de cobro'],
        ],
        self::AUX_CXP => [
            'FACTURA'      => ['signo' => 1,  'naturaleza' => 'CARGO', 'cobrable' => true,  'prefijo' => null,  'reversa' => false, 'descripcion' => 'Factura'],
            'NOTA_DEBITO'  => ['signo' => 1,  'naturaleza' => 'CARGO', 'cobrable' => true,  'prefijo' => 'ND-', 'reversa' => false, 'descripcion' => 'Nota de débito'],
            'NOTA_CREDITO' => ['signo' => -1, 'naturaleza' => 'ABONO', 'cobrable' => false, 'prefijo' => 'NC-', 'reversa' => true,  'descripcion' => 'Nota de crédito'],
            'REEMBOLSO'    => ['signo' => 1,  'naturaleza' => 'CARGO', 'cobrable' => true,  'prefijo' => 'RE-', 'reversa' => false, 'descripcion' => 'Reembolso de compra'],
            'IMPORTACION'  => ['signo' => 1,  'naturaleza' => 'CARGO', 'cobrable' => true,  'prefijo' => 'IM-', 'reversa' => false, 'descripcion' => 'Factura de importación'],
            'ANTICIPO'     => ['signo' => -1, 'naturaleza' => 'ABONO', 'cobrable' => false, 'prefijo' => 'AN-', 'reversa' => false, 'descripcion' => 'Anticipo a proveedor'],
            'RETENCION'    => ['signo' => -1, 'naturaleza' => 'ABONO', 'cobrable' => false, 'prefijo' => 'RT-', 'reversa' => false, 'descripcion' => 'Retención a proveedor'],
            'PAGO'         => ['signo' => -1, 'naturaleza' => 'ABONO', 'cobrable' => false, 'prefijo' => 'PG-', 'reversa' => false, 'descripcion' => 'Comprobante de pago'],
        ],
    ];

    /**
     * Mapa [tipo_documento => atributos] de un auxiliar, cacheado en memoria del
     * request. Lee de la tabla; si no existe o está vacía, usa DEFAULTS.
     *
     * @return array<string, array{signo:int, naturaleza:string, cobrable:bool, prefijo:?string, reversa:bool, descripcion:string}>
     */
    public static function mapa(string $auxiliar): array
    {
        return Cache::store('array')->rememberForever("tipos_doc.$auxiliar", function () use ($auxiliar) {
            try {
                $filas = static::query()->where('auxiliar', $auxiliar)->get();
            } catch (Throwable) {
                $filas = collect();
            }

            if ($filas->isEmpty()) {
                return self::DEFAULTS[$auxiliar] ?? [];
            }

            return $filas->keyBy('tipo_documento')->map(fn ($t) => [
                'signo'       => (int) $t->signo,
                'naturaleza'  => $t->naturaleza,
                'cobrable'    => (bool) $t->cobrable,
                'prefijo'     => $t->prefijo,
                'reversa'     => (bool) $t->reversa,
                'descripcion' => $t->descripcion,
            ])->all();
        });
    }

    private static function atributo(string $auxiliar, string $tipo, string $clave, mixed $default): mixed
    {
        return static::mapa($auxiliar)[$tipo][$clave]
            ?? self::DEFAULTS[$auxiliar][$tipo][$clave]
            ?? $default;
    }

    /** Signo del tipo en el auxiliar (+1 cargo, -1 abono). */
    public static function signo(string $auxiliar, string $tipo): int
    {
        return (int) static::atributo($auxiliar, $tipo, 'signo', 1);
    }

    /** ¿El tipo genera saldo cobrable/pagable en el submayor? */
    public static function esCobrable(string $auxiliar, string $tipo): bool
    {
        return (bool) static::atributo($auxiliar, $tipo, 'cobrable', false);
    }

    /** Prefijo de numeración del tipo (null = sin serie propia). */
    public static function prefijo(string $auxiliar, string $tipo): ?string
    {
        return static::atributo($auxiliar, $tipo, 'prefijo', null);
    }

    /** ¿Es un contra-documento que revierte otro? (NC revierte factura). */
    public static function esReversa(string $auxiliar, string $tipo): bool
    {
        return (bool) static::atributo($auxiliar, $tipo, 'reversa', false);
    }

    /** Tipos cobrables (generan saldo) de un auxiliar. */
    public static function tiposCobrables(string $auxiliar): array
    {
        return array_keys(array_filter(
            static::mapa($auxiliar),
            fn ($t) => $t['cobrable']
        ));
    }
}
