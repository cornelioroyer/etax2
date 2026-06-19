<?php

namespace App\Models;

use App\Models\Concerns\TipoDocumentoBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Nota de débito de venta: cargo adicional al cliente (aumenta lo que debe).
 * Comparte tabla con las facturas (ventas_facturas), distinguida por
 * tipo_documento = NOTA_DEBITO.
 */
class VentaNotaDebito extends Model
{
    use TipoDocumentoBehavior;

    protected $table = 'ventas_facturas';

    public const TIPO_DOCUMENTO = 'NOTA_DEBITO';

    /** Submayor: cuentas por cobrar (carga el saldo del cliente). */
    protected static function auxiliarSubmayor(): string
    {
        return TipoDocumento::AUX_CXC;
    }

    public const ESTADO_EMITIDA  = 'EMITIDA';
    public const ESTADO_PARCIAL  = 'PARCIAL';
    public const ESTADO_PAGADA   = 'PAGADA';
    public const ESTADO_ANULADA  = 'ANULADA';

    protected $fillable = [
        'compania_id', 'cliente_id', 'tipo_documento', 'numero', 'fecha', 'fecha_vencimiento', 'motivo',
        'subtotal', 'descuento', 'itbms', 'total', 'saldo',
        'cxc_documento_id', 'asiento_id', 'estado', 'extra',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_vencimiento' => 'date',
            'total' => 'decimal:2',
            'saldo' => 'decimal:2',
            'extra' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tipoNotaDebito', function ($builder) {
            $builder->where('ventas_facturas.tipo_documento', self::TIPO_DOCUMENTO);
        });

        static::creating(function (VentaNotaDebito $nota) {
            $nota->tipo_documento = self::TIPO_DOCUMENTO;
            $nota->subtotal ??= $nota->total ?? 0;
            $nota->descuento ??= 0;
            $nota->itbms ??= 0;
            $nota->saldo ??= $nota->total ?? 0;
            if ($nota->extra === null) {
                $nota->extra = [];
            }
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function cxcDocumento(): BelongsTo
    {
        return $this->belongsTo(CxcDocumento::class, 'cxc_documento_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function esAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    /** Siguiente número ND- de la compañía (sobre ventas_facturas). */
    public static function siguienteNumero(int $companiaId): string
    {
        return DB::transaction(function () use ($companiaId) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$companiaId * 1000 + 9]);
            }

            $prefijo = static::prefijoDe(self::TIPO_DOCUMENTO) ?? 'ND-';

            $max = self::where('compania_id', $companiaId)
                ->where('numero', 'LIKE', $prefijo.'%')
                ->max('numero');

            $siguiente = 1;
            if ($max && preg_match('/'.preg_quote($prefijo, '/').'(\d+)$/', $max, $m)) {
                $siguiente = (int) $m[1] + 1;
            }

            return $prefijo.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
        });
    }
}
