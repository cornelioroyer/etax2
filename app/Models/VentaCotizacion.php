<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class VentaCotizacion extends Model
{
    protected $table = 'ventas_cotizaciones';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_ENVIADA = 'ENVIADA';

    public const ESTADO_ACEPTADA = 'ACEPTADA';

    public const ESTADO_RECHAZADA = 'RECHAZADA';

    public const ESTADO_FACTURADA = 'FACTURADA';

    public const ESTADO_ANULADA = 'ANULADA';

    protected $fillable = [
        'compania_id',
        'cliente_id',
        'numero',
        'fecha',
        'fecha_validez',
        'subtotal',
        'descuento',
        'itbms',
        'total',
        'estado',
        'extra',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_validez' => 'date',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'itbms' => 'decimal:2',
            'total' => 'decimal:2',
            'extra' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(VentaCotizacionDetalle::class, 'cotizacion_id')->orderBy('linea');
    }

    /** Nota libre almacenada en extra->notas */
    public function getNotasAttribute(): ?string
    {
        return $this->extra['notas'] ?? null;
    }

    public function esFacturable(): bool
    {
        return in_array($this->estado, [self::ESTADO_ENVIADA, self::ESTADO_ACEPTADA], true);
    }

    /**
     * Siguiente número COT- de la compañía. Llamar dentro de una transacción.
     */
    public static function siguienteNumero(int $companiaId): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$companiaId.'-COT-']);
        }

        $ultimo = self::where('compania_id', $companiaId)
            ->where('numero', 'like', 'COT-%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 4)) + 1 : 1;

        return 'COT-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }
}
