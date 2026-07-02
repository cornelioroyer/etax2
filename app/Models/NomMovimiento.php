<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una corrida de planilla: empleado x concepto x monto. El monto es
 * SIEMPRE positivo; el efecto (ingreso/deducción/patronal) lo da el concepto.
 */
class NomMovimiento extends Model
{
    protected $table = 'nom_movimientos';

    protected $fillable = [
        'compania_id',
        'planilla_id',
        'empleado_id',
        'concepto_id',
        'cantidad',
        'base',
        'monto',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'base' => 'decimal:2',
            'monto' => 'decimal:2',
        ];
    }

    public function planilla(): BelongsTo
    {
        return $this->belongsTo(NomPlanilla::class, 'planilla_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NomEmpleado::class, 'empleado_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(NomConcepto::class, 'concepto_id');
    }
}
