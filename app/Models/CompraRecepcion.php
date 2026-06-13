<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CompraRecepcion extends Model
{
    protected $table = 'compras_recepciones';

    public const ESTADO_RECIBIDO = 'RECIBIDO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'compania_id',
        'orden_id',
        'proveedor_id',
        'numero',
        'fecha',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(CompraOrden::class, 'orden_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'proveedor_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(CompraRecepcionDetalle::class, 'recepcion_id');
    }

    /** Siguiente número RM- (recepción de mercancía). Llamar en transacción. */
    public static function siguienteNumero(int $companiaId): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$companiaId.'-RM-']);
        }

        $ultimo = self::where('compania_id', $companiaId)
            ->where('numero', 'like', 'RM-%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

        return 'RM-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }
}
