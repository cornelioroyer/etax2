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

    public const ESTADO_PARCIALMENTE_FACTURADA = 'PARCIALMENTE_FACTURADA';

    public const ESTADO_FACTURADA = 'FACTURADA';

    public const ESTADO_CERRADA = 'CERRADA';

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

    /** Facturas (CxP) generadas desde esta orden. Relación 1:N (varias parciales). */
    public function cxpDocumentos(): HasMany
    {
        return $this->hasMany(CxpDocumento::class, 'orden_id');
    }

    /** Sólo se puede recibir/facturar desde APROBADA o recepción parcial. */
    public function esRecibible(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_APROBADA,
            self::ESTADO_RECIBIDA_PARCIAL,
            self::ESTADO_PARCIALMENTE_FACTURADA,
        ], true);
    }

    /**
     * Cantidad recibida vigente por línea (excluye recepciones anuladas).
     *
     * @return \Illuminate\Support\Collection<int,float> orden_detalle_id => recibido
     */
    public function recibidoPorLinea(): \Illuminate\Support\Collection
    {
        return CompraRecepcionDetalle::query()
            ->whereIn('recepcion_id', $this->recepciones()->where('estado', '!=', CompraRecepcion::ESTADO_ANULADO)->pluck('id'))
            ->selectRaw('orden_detalle_id, SUM(cantidad) AS recibido')
            ->groupBy('orden_detalle_id')
            ->pluck('recibido', 'orden_detalle_id');
    }

    /**
     * Cantidad pendiente de facturar por cada línea de la orden. Los bienes
     * inventariables se topan por lo RECIBIDO no facturado; los servicios y demás
     * por lo ORDENADO no facturado (no requieren recepción).
     *
     * @return array<int,float> orden_detalle_id => cantidad facturable
     */
    public function facturablePorLinea(): array
    {
        $this->loadMissing('detalle');
        $recibido = $this->recibidoPorLinea();
        $itemIds = $this->detalle->pluck('item_id')->filter()->unique();
        $items = $itemIds->isEmpty() ? collect() : ItemProducto::whereIn('id', $itemIds)
            ->get(['id', 'tipo'])->keyBy('id');

        $out = [];
        foreach ($this->detalle as $linea) {
            $facturado = (float) $linea->cantidad_facturada;
            $item = $linea->item_id ? $items->get($linea->item_id) : null;
            $esProducto = $item && $item->tipo === ItemProducto::TIPO_PRODUCTO;

            $tope = $esProducto
                ? (float) ($recibido[$linea->id] ?? 0)   // bienes: solo lo recibido
                : (float) $linea->cantidad;               // servicios: lo ordenado
            $out[$linea->id] = round(max(0, $tope - $facturado), 4);
        }

        return $out;
    }

    public function esFacturable(): bool
    {
        if (in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_ANULADA, self::ESTADO_CERRADA, self::ESTADO_FACTURADA], true)) {
            return false;
        }

        return collect($this->facturablePorLinea())->sum() > 0.0001;
    }

    /**
     * Recalcula el estado de facturación tras emitir una factura parcial/total.
     * No toca BORRADOR/ANULADA. Cierra la orden cuando todo lo ordenado quedó
     * facturado (y por ende recibido para los bienes).
     */
    public function refrescarEstadoFacturacion(): void
    {
        if (in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_ANULADA], true)) {
            return;
        }

        $this->loadMissing('detalle');
        $algoFacturado = false;
        $todoFacturado = true;

        foreach ($this->detalle as $linea) {
            $fact = (float) $linea->cantidad_facturada;
            if ($fact > 0.0001) {
                $algoFacturado = true;
            }
            if ($fact + 0.0001 < (float) $linea->cantidad) {
                $todoFacturado = false;
            }
        }

        if ($todoFacturado && $algoFacturado) {
            $estado = self::ESTADO_FACTURADA;
        } elseif ($algoFacturado) {
            $estado = self::ESTADO_PARCIALMENTE_FACTURADA;
        } else {
            // Sin nada facturado: conserva el estado de recepción.
            $this->refrescarEstadoRecepcion();

            return;
        }

        if ($estado !== $this->estado) {
            $this->update(['estado' => $estado]);
        }
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
            ->whereIn('recepcion_id', $this->recepciones()->where('estado', '!=', CompraRecepcion::ESTADO_ANULADO)->pluck('id'))
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
        } else {
            // Nada recibido vigente (p. ej. se anularon todas las recepciones):
            // la orden vuelve a quedar aprobada y recibible.
            $estado = self::ESTADO_APROBADA;
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
