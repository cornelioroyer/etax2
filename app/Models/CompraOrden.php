<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CompraOrden extends Model
{
    protected $table = 'compras_ordenes';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_APROBADA = 'APROBADA';

    public const ESTADO_RECIBIDA_PARCIAL = 'RECIBIDA_PARCIAL';

    public const ESTADO_RECIBIDA = 'RECIBIDA';

    public const ESTADO_FACTURADA = 'FACTURADA';

    public const ESTADO_ANULADA = 'ANULADA';

    protected $fillable = [
        'compania_id',
        'proveedor_id',
        'numero',
        'fecha',
        'estado',
        'subtotal',
        'itbms',
        'total',
        'observaciones',
        'adjunto_id',
        'cxp_documento_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'subtotal' => 'decimal:2',
            'itbms' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'proveedor_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(CompraOrdenDetalle::class, 'orden_id')->orderBy('linea');
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(CompraRecepcion::class, 'orden_id')->orderByDesc('fecha');
    }

    public function cxpDocumento(): BelongsTo
    {
        return $this->belongsTo(CxpDocumento::class, 'cxp_documento_id');
    }

    /** Sólo se puede recibir/facturar desde APROBADA o recepción parcial. */
    public function esRecibible(): bool
    {
        return in_array($this->estado, [self::ESTADO_APROBADA, self::ESTADO_RECIBIDA_PARCIAL], true);
    }

    public function esFacturable(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_APROBADA,
            self::ESTADO_RECIBIDA_PARCIAL,
            self::ESTADO_RECIBIDA,
        ], true) && $this->cxp_documento_id === null;
    }

    /**
     * Recalcula el estado de recepción comparando lo recibido contra lo
     * ordenado por línea. No toca FACTURADA/ANULADA.
     */
    public function refrescarEstadoRecepcion(): void
    {
        if (in_array($this->estado, [self::ESTADO_FACTURADA, self::ESTADO_ANULADA], true)) {
            return;
        }

        $recibidoPorLinea = CompraRecepcionDetalle::query()
            ->whereIn('recepcion_id', $this->recepciones()->pluck('id'))
            ->selectRaw('orden_detalle_id, SUM(cantidad) AS recibido')
            ->groupBy('orden_detalle_id')
            ->pluck('recibido', 'orden_detalle_id');

        $algoRecibido = false;
        $todoRecibido = true;

        foreach ($this->detalle as $linea) {
            $recibido = (float) ($recibidoPorLinea[$linea->id] ?? 0);
            if ($recibido > 0) {
                $algoRecibido = true;
            }
            if ($recibido + 0.0001 < (float) $linea->cantidad) {
                $todoRecibido = false;
            }
        }

        $estado = $this->estado;
        if ($todoRecibido && $algoRecibido) {
            $estado = self::ESTADO_RECIBIDA;
        } elseif ($algoRecibido) {
            $estado = self::ESTADO_RECIBIDA_PARCIAL;
        }

        if ($estado !== $this->estado) {
            $this->update(['estado' => $estado]);
        }
    }

    /** Siguiente número OC- de la compañía. Llamar dentro de una transacción. */
    public static function siguienteNumero(int $companiaId): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$companiaId.'-OC-']);
        }

        $ultimo = self::where('compania_id', $companiaId)
            ->where('numero', 'like', 'OC-%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

        return 'OC-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }
}
