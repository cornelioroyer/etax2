<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Asiento extends Model
{
    protected $table = 'cgl_asientos';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_POSTEADO = 'POSTEADO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'compania_id',
        'periodo_id',
        'diario_id',
        'numero',
        'fecha',
        'descripcion',
        'referencia',
        'estado',
        'origen_modulo',
        'origen_tabla',
        'origen_id',
        'total_debito',
        'total_credito',
        'usuario_id',
        'posteado_por',
        'fecha_posteo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_posteo' => 'datetime',
            'total_debito' => 'decimal:2',
            'total_credito' => 'decimal:2',
        ];
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(AsientoDetalle::class, 'asiento_id')->orderBy('linea');
    }

    public function diario(): BelongsTo
    {
        return $this->belongsTo(Diario::class, 'diario_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }

    public function posteadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posteado_por');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function esPosteado(): bool
    {
        return $this->estado === self::ESTADO_POSTEADO;
    }

    /**
     * Siguiente número AS-NNNNNN de la compañía.
     * Llamar dentro de una transacción.
     */
    public static function siguienteNumero(int $companiaId): string
    {
        // PostgreSQL no permite FOR UPDATE con agregados (max); usamos un
        // advisory lock de transacción para serializar la numeración.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['asiento-'.$companiaId]);
        }

        $ultimo = self::where('compania_id', $companiaId)
            ->where('numero', 'like', 'AS-%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

        return 'AS-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }
}
