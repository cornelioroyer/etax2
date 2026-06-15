<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CxpDocumento extends Model
{
    protected $table = 'cxp_documentos';

    public const TIPO_FACTURA = 'FACTURA';

    public const TIPO_PAGO = 'PAGO';

    public const TIPO_NOTA_CREDITO = 'NOTA_CREDITO';

    public const TIPO_NOTA_DEBITO = 'NOTA_DEBITO';

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
        'fecha',
        'fecha_vencimiento',
        'moneda_id',
        'subtotal',
        'descuento',
        'impuesto',
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
     * Cargo (aumenta lo que debemos al proveedor): facturas y notas de débito.
     * Abono (lo reduce): pagos y notas de crédito.
     */
    public function esCargo(): bool
    {
        return in_array($this->tipo_documento, [self::TIPO_FACTURA, self::TIPO_NOTA_DEBITO], true);
    }

    /** Tipos que generan un saldo pagable (facturas y notas de débito). */
    public static function tiposPagables(): array
    {
        return [self::TIPO_FACTURA, self::TIPO_NOTA_DEBITO];
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
        self::bloquearNumeracion($companiaId.'-PG-');

        $ultimo = self::where('compania_id', $companiaId)
            ->where('tipo_documento', self::TIPO_PAGO)
            ->where('numero', 'like', 'PG-%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

        return 'PG-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Siguiente número NC-/ND- de notas en la compañía.
     * Llamar dentro de una transacción.
     */
    public static function siguienteNumeroNota(int $companiaId, string $tipo): string
    {
        $prefijo = $tipo === self::TIPO_NOTA_CREDITO ? 'NC-' : 'ND-';

        self::bloquearNumeracion($companiaId.'-'.$prefijo);

        $ultimo = self::where('compania_id', $companiaId)
            ->where('tipo_documento', $tipo)
            ->where('numero', 'like', $prefijo.'%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

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
