<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TallerPresupuesto extends Model
{
    protected $table = 'taller_presupuestos';

    public const ESTADOS = [
        'borrador'   => 'Borrador',
        'enviado'    => 'Enviado',
        'aprobado'   => 'Aprobado',
        'rechazado'  => 'Rechazado',
        'vencido'    => 'Vencido',
        'convertido' => 'Convertido',
        'anulado'    => 'Anulado',
    ];

    protected $fillable = [
        'taller_id', 'compania_id', 'cliente_id', 'equipo_id',
        'numero', 'fecha', 'fecha_vencimiento',
        'descripcion',
        'subtotal', 'descuento', 'impuesto', 'total',
        'estado', 'cxc_documento_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'            => 'date',
            'fecha_vencimiento'=> 'date',
            'subtotal'         => 'decimal:2',
            'descuento'        => 'decimal:2',
            'impuesto'         => 'decimal:2',
            'total'            => 'decimal:2',
        ];
    }

    public static function siguienteNumero(int $tallerId): string
    {
        $anio = now()->year;
        $max  = static::where('taller_id', $tallerId)
            ->whereYear('created_at', $anio)
            ->max(DB::raw("CAST(SPLIT_PART(numero, '-', 3) AS INTEGER)"));
        $seq = ($max ?? 0) + 1;

        return 'PP-' . $anio . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(TallerEquipo::class, 'equipo_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(TallerPresupuestoDetalle::class, 'presupuesto_id')
            ->orderBy('orden')
            ->orderBy('id');
    }
}
