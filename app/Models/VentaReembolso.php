<?php

namespace App\Models;

use App\Models\Concerns\TipoDocumentoBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Reembolso de venta (DGI tipo 09): cobro al cliente por gastos pagados a su
 * cuenta. Es un cargo cobrable, como una factura. Comparte tabla con las
 * facturas (ventas_facturas), distinguido por tipo_documento = REEMBOLSO.
 */
class VentaReembolso extends Model
{
    use TipoDocumentoBehavior;

    protected $table = 'ventas_facturas';

    public const TIPO_DOCUMENTO = 'REEMBOLSO';

    /** Submayor: cuentas por cobrar (carga el saldo del cliente). */
    protected static function auxiliarSubmayor(): string
    {
        return TipoDocumento::AUX_CXC;
    }

    public const ESTADO_EMITIDA = 'EMITIDA';
    public const ESTADO_PARCIAL = 'PARCIAL';
    public const ESTADO_PAGADA  = 'PAGADA';
    public const ESTADO_ANULADA = 'ANULADA';

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
        static::addGlobalScope('tipoReembolso', function ($builder) {
            $builder->where('ventas_facturas.tipo_documento', self::TIPO_DOCUMENTO);
        });

        static::creating(function (VentaReembolso $doc) {
            $doc->tipo_documento = self::TIPO_DOCUMENTO;
            $doc->subtotal ??= $doc->total ?? 0;
            $doc->descuento ??= 0;
            $doc->itbms ??= 0;
            $doc->saldo ??= $doc->total ?? 0;
            if ($doc->extra === null) {
                $doc->extra = [];
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

    /** Siguiente número RE- de la compañía (sobre ventas_facturas). */
    public static function siguienteNumero(int $companiaId): string
    {
        return DB::transaction(function () use ($companiaId) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$companiaId * 1000 + 10]);
            }

            $prefijo = static::prefijoDe(self::TIPO_DOCUMENTO) ?? 'RE-';

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
