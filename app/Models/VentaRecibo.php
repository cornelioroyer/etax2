<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class VentaRecibo extends Model
{
    protected $table = 'ventas_recibos';

    public const ESTADO_APLICADO = 'APLICADO';
    public const ESTADO_ANULADO  = 'ANULADO';

    protected $fillable = [
        'compania_id', 'cliente_id', 'numero', 'fecha', 'metodo_pago',
        'moneda_id', 'total', 'estado', 'cxc_documento_id', 'asiento_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
        ];
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

    public function detalle(): HasMany
    {
        return $this->hasMany(VentaReciboDetalle::class, 'recibo_id');
    }

    public function esAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    public static function siguienteNumero(int $companiaId): string
    {
        return DB::transaction(function () use ($companiaId) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$companiaId * 1000 + 7]);
            }

            $max = self::where('compania_id', $companiaId)
                ->where('numero', 'LIKE', 'RC-%')
                ->max('numero');

            $siguiente = 1;
            if ($max && preg_match('/RC-(\d+)$/', $max, $m)) {
                $siguiente = (int) $m[1] + 1;
            }

            return 'RC-' . str_pad($siguiente, 6, '0', STR_PAD_LEFT);
        });
    }
}
