<?php

namespace App\Models;

use App\Models\Concerns\TipoDocumentoBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class CxpDocumento extends Model
{
    use TipoDocumentoBehavior;

    protected $table = 'cxp_documentos';

    /** Submayor de este documento: cuentas por pagar. */
    protected static function auxiliarSubmayor(): string
    {
        return TipoDocumento::AUX_CXP;
    }

    public const TIPO_FACTURA = 'FACTURA';

    public const TIPO_PAGO = 'PAGO';

    public const TIPO_NOTA_CREDITO = 'NOTA_CREDITO';

    public const TIPO_NOTA_DEBITO = 'NOTA_DEBITO';

    public const TIPO_REEMBOLSO = 'REEMBOLSO';

    public const TIPO_IMPORTACION = 'IMPORTACION';

    public const TIPO_ANTICIPO = 'ANTICIPO';

    public const TIPO_RETENCION = 'RETENCION';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_PARCIAL = 'PARCIAL';

    public const ESTADO_PAGADO = 'PAGADO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'compania_id',
        'proveedor_id',
        'tipo_documento',
        'numero',
        'cufe',
        'archivo_path',
        'archivo_disk',
        'fecha',
        'fecha_vencimiento',
        'moneda_id',
        'subtotal',
        'descuento',
        'impuesto',
        'retencion',
        'total',
        'saldo',
        'estado',
        'asiento_id',
        'adjunto_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_vencimiento' => 'date',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'retencion' => 'decimal:2',
            'total' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'proveedor_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(CxpDocumentoDetalle::class, 'documento_id')->orderBy('linea');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    /** Aplicaciones donde este documento es el pago (origen). */
    public function aplicacionesComoOrigen(): HasMany
    {
        return $this->hasMany(CxpAplicacion::class, 'documento_origen_id');
    }

    /** Aplicaciones recibidas (este documento es la factura destino). */
    public function aplicacionesComoDestino(): HasMany
    {
        return $this->hasMany(CxpAplicacion::class, 'documento_destino_id');
    }

    public function compraOrden(): HasOne
    {
        return $this->hasOne(CompraOrden::class, 'cxp_documento_id');
    }

    public function esAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    /** Borrador: aún no contabilizada (sin asiento), editable y eliminable. */
    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    /**
     * Tipos que generan un saldo pagable (facturas y notas de débito).
     * Alias de tiposConSaldo() (trait) para no romper llamadas existentes.
     * El signo (esCargo) lo aporta el trait desde core_tipos_documento.
     */
    public static function tiposPagables(): array
    {
        return static::tiposConSaldo();
    }

    /**
     * Tipos gestionados por el módulo de documentos por pagar (formulario único
     * "Nuevo documento por pagar" y listado de facturas): factura, reembolso e
     * importación (cargos +1) más las notas de crédito/débito.
     *
     * @return list<string>
     */
    public static function tiposModulo(): array
    {
        return [
            self::TIPO_FACTURA,
            self::TIPO_REEMBOLSO,
            self::TIPO_IMPORTACION,
            self::TIPO_NOTA_DEBITO,
            self::TIPO_NOTA_CREDITO,
        ];
    }

    /**
     * Tipos "tipo factura": cargos +1 cobrables que contabilizan Dr contrapartida
     * + Dr ITBMS / Cr CXP y pueden registrarse al contado.
     *
     * @return list<string>
     */
    public static function tiposFacturaCargo(): array
    {
        return [self::TIPO_FACTURA, self::TIPO_REEMBOLSO, self::TIPO_IMPORTACION];
    }

    /** Descripción legible del tipo, tomada del maestro core_tipos_documento. */
    public function etiquetaTipo(): string
    {
        return TipoDocumento::descripcion(static::auxiliarSubmayor(), (string) $this->tipo_documento);
    }

    /** Estado según el saldo (PENDIENTE / PARCIAL / PAGADO). */
    public function estadoSegunSaldo(): string
    {
        $saldo = round((float) $this->saldo, 2);

        if ($saldo <= 0.0) {
            return self::ESTADO_PAGADO;
        }

        return $saldo < round((float) $this->total, 2) ? self::ESTADO_PARCIAL : self::ESTADO_PENDIENTE;
    }

    /**
     * Siguiente número PG- de pagos en la compañía (las facturas de
     * proveedor usan el número del documento del proveedor).
     * Llamar dentro de una transacción.
     */
    public static function siguienteNumeroPago(int $companiaId): string
    {
        $prefijo = static::prefijoDe(self::TIPO_PAGO) ?? 'PG-';

        self::bloquearNumeracion($companiaId.'-'.$prefijo);

        $ultimo = self::where('compania_id', $companiaId)
            ->where('tipo_documento', self::TIPO_PAGO)
            ->where('numero', 'like', $prefijo.'%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        return $prefijo.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Siguiente número NC-/ND- de notas en la compañía.
     * Llamar dentro de una transacción.
     */
    public static function siguienteNumeroNota(int $companiaId, string $tipo): string
    {
        $prefijo = static::prefijoDe($tipo) ?? ($tipo === self::TIPO_NOTA_CREDITO ? 'NC-' : 'ND-');

        self::bloquearNumeracion($companiaId.'-'.$prefijo);

        $ultimo = self::where('compania_id', $companiaId)
            ->where('tipo_documento', $tipo)
            ->where('numero', 'like', $prefijo.'%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

        return $prefijo.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Siguiente número correlativo de un tipo con prefijo propio (AN-, etc.) en
     * la compañía, derivando el prefijo del maestro. Llamar dentro de una
     * transacción.
     */
    public static function siguienteNumeroTipo(int $companiaId, string $tipo): string
    {
        $prefijo = static::prefijoDe($tipo) ?? '';

        self::bloquearNumeracion($companiaId.'-'.$prefijo);

        $ultimo = self::where('compania_id', $companiaId)
            ->where('tipo_documento', $tipo)
            ->where('numero', 'like', $prefijo.'%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        return $prefijo.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Serializa la generación de números entre transacciones concurrentes.
     * En PostgreSQL usa un advisory lock de transacción; en otros motores
     * (SQLite en tests) es no-op porque la escritura ya está serializada.
     */
    protected static function bloquearNumeracion(string $clave): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$clave]);
        }
    }
}
