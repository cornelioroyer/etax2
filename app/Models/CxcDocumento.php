<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CxcDocumento extends Model
{
    protected $table = 'cxc_documentos';

    public const TIPO_FACTURA = 'FACTURA';

    public const TIPO_PAGO = 'PAGO';

    public const TIPO_NOTA_CREDITO = 'NOTA_CREDITO';

    public const TIPO_NOTA_DEBITO = 'NOTA_DEBITO';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_PARCIAL = 'PARCIAL';

    public const ESTADO_PAGADO = 'PAGADO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'compania_id',
        'cliente_id',
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
        'fel_documento_id',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(CxcDocumentoDetalle::class, 'documento_id')->orderBy('linea');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    /** Aplicaciones donde este documento es el pago (origen). */
    public function aplicacionesComoOrigen(): HasMany
    {
        return $this->hasMany(CxcAplicacion::class, 'documento_origen_id');
    }

    /** Aplicaciones recibidas (este documento es la factura destino). */
    public function aplicacionesComoDestino(): HasMany
    {
        return $this->hasMany(CxcAplicacion::class, 'documento_destino_id');
    }

    public function esAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    /**
     * Cargo (aumenta la deuda del cliente): facturas y notas de débito.
     * Abono (la reduce): cobros y notas de crédito.
     */
    public function esCargo(): bool
    {
        return in_array($this->tipo_documento, [self::TIPO_FACTURA, self::TIPO_NOTA_DEBITO], true);
    }

    /** Tipos que generan un saldo cobrable (facturas y notas de débito). */
    public static function tiposCobrables(): array
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
     * Siguiente número del tipo (FC- facturas, RC- cobros) en la compañía.
     * Llamar dentro de una transacción.
     */
    public static function siguienteNumero(int $companiaId, string $tipo): string
    {
        $prefijo = match ($tipo) {
            self::TIPO_PAGO => 'RC-',
            self::TIPO_NOTA_CREDITO => 'NC-',
            self::TIPO_NOTA_DEBITO => 'ND-',
            default => 'FC-',
        };

        // PostgreSQL no permite FOR UPDATE con agregados (max); usamos un
        // advisory lock por compañía+prefijo para serializar la numeración.
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
     * En PostgreSQL usa un advisory lock de transacción (se libera al
     * cerrar la transacción); en otros motores (SQLite en tests) es no-op
     * porque la escritura ya está serializada.
     */
    protected static function bloquearNumeracion(string $clave): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$clave]);
        }
    }
}
