<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class VentaNotaCredito extends Model
{
    protected $table = 'ventas_notas_credito';

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_EMITIDA  = 'EMITIDA';
    public const ESTADO_APLICADA = 'APLICADA';
    public const ESTADO_ANULADA  = 'ANULADA';

    protected $fillable = [
        'compania_id', 'cliente_id', 'numero', 'fecha', 'motivo',
        'total', 'cxc_documento_id', 'asiento_id', 'estado',
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

    public function esAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    public static function siguienteNumero(int $companiaId): string
    {
        return DB::transaction(function () use ($companiaId) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$companiaId * 1000 + 8]);
            }

            $max = self::where('compania_id', $companiaId)
                ->where('numero', 'LIKE', 'NC-%')
                ->max('numero');

            $siguiente = 1;
            if ($max && preg_match('/NC-(\d+)$/', $max, $m)) {
                $siguiente = (int) $m[1] + 1;
            }

            return 'NC-' . str_pad($siguiente, 6, '0', STR_PAD_LEFT);
        });
    }
}
