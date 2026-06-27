<?php

namespace App\Models;

use App\Models\Concerns\TipoDocumentoBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class CxcDocumento extends Model
{
    use TipoDocumentoBehavior;

    protected $table = 'cxc_documentos';

    /** Submayor de este documento: cuentas por cobrar. */
    protected static function auxiliarSubmayor(): string
    {
        return TipoDocumento::AUX_CXC;
    }

    public const TIPO_FACTURA = 'FACTURA';

    public const TIPO_PAGO = 'PAGO';

    public const TIPO_NOTA_CREDITO = 'NOTA_CREDITO';

    public const TIPO_NOTA_DEBITO = 'NOTA_DEBITO';

    public const TIPO_REEMBOLSO = 'REEMBOLSO';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_PARCIAL = 'PARCIAL';

    public const ESTADO_PAGADO = 'PAGADO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'compania_id',
        'cliente_id',
        'tipo_documento',
        'numero',
        'referencia',
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

    /**
     * La factura de venta cuyo espejo cobrable es este documento, si existe.
     * El vínculo es ventas_facturas.cxc_documento_id → cxc_documentos.id. Solo
     * los documentos de tipo FACTURA tienen una venta detrás; cobros y notas
     * no son espejo de ninguna venta (la relación devuelve null). El global
     * scope de VentaFactura ya restringe a tipo_documento='FACTURA'.
     */
    public function facturaVenta(): HasOne
    {
        return $this->hasOne(VentaFactura::class, 'cxc_documento_id');
    }

    public function esAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    /**
     * Propaga el saldo y el estado de este documento a la factura de venta que
     * lo tiene como espejo (ventas_facturas.cxc_documento_id), para que el
     * submayor de Ventas no quede desincronizado cuando el cobro, la nota de
     * crédito o su anulación se realizan por el lado de CxC. CxC es la fuente de
     * verdad del saldo cobrable. No-op si el documento no es espejo de ninguna
     * venta (cobros, notas, facturas nativas de CxC). Llamar dentro de la misma
     * transacción que actualizó el saldo de este documento.
     */
    public function sincronizarFacturaVenta(?string $usuarioEmail = null): void
    {
        $factura = $this->facturaVenta()->lockForUpdate()->first();

        if (! $factura) {
            return;
        }

        $saldo = round((float) $this->saldo, 2);

        if ($this->esAnulado()) {
            $estado = VentaFactura::ESTADO_ANULADA;
        } elseif ($saldo <= 0.0) {
            $estado = VentaFactura::ESTADO_PAGADA;
        } elseif ($saldo < round((float) $factura->total, 2)) {
            $estado = VentaFactura::ESTADO_PARCIAL;
        } else {
            $estado = VentaFactura::ESTADO_EMITIDA;
        }

        $factura->saldo = max(0.0, $saldo);
        $factura->estado = $estado;

        if ($usuarioEmail !== null) {
            $factura->updated_by = $usuarioEmail;
        }

        $factura->save();
    }

    /**
     * Tipos que generan un saldo cobrable (facturas y notas de débito).
     * Alias de tiposConSaldo() (trait) para no romper llamadas existentes.
     */
    public static function tiposCobrables(): array
    {
        return static::tiposConSaldo();
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
        // Prefijo declarado en el maestro core_tipos_documento (auxiliar CXC);
        // fallback FC- por compatibilidad si el tipo no estuviera catalogado.
        $prefijo = static::prefijoDe($tipo) ?? 'FC-';

        // PostgreSQL no permite FOR UPDATE con agregados (max); usamos un
        // advisory lock por compañía+prefijo para serializar la numeración.
        self::bloquearNumeracion($companiaId.'-'.$prefijo);

        $len  = strlen($prefijo);
        $base = self::where('compania_id', $companiaId)
            ->where('tipo_documento', $tipo)
            ->where('numero', 'like', $prefijo.'%');

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Solo cuenta números con formato estricto PREFIJO+dígitos y toma el
            // máximo NUMÉRICO (no lexical), así un número manual con otro formato
            // —p.ej. FC-A100— no envenena el correlativo automático ni provoca
            // colisiones por ancho variable.
            $maxNum = (clone $base)
                ->where('numero', '~', '^'.$prefijo.'[0-9]+$')
                ->selectRaw('MAX(CAST(SUBSTRING(numero FROM '.($len + 1).') AS INTEGER)) AS n')
                ->value('n');
            $siguiente = ((int) $maxNum) + 1;
        } else {
            $ultimo    = $base->max('numero');
            $siguiente = $ultimo ? ((int) substr($ultimo, $len)) + 1 : 1;
        }

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
