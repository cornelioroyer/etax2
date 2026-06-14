<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetPresupuestoDetalle extends Model
{
    protected $table = 'budget_presupuestos_detalle';

    protected $fillable = [
        'presupuesto_id',
        'cuenta_id',
        'monto_01',
        'monto_02',
        'monto_03',
        'monto_04',
        'monto_05',
        'monto_06',
        'monto_07',
        'monto_08',
        'monto_09',
        'monto_10',
        'monto_11',
        'monto_12',
        'monto_total',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monto_01' => 'decimal:2',
            'monto_02' => 'decimal:2',
            'monto_03' => 'decimal:2',
            'monto_04' => 'decimal:2',
            'monto_05' => 'decimal:2',
            'monto_06' => 'decimal:2',
            'monto_07' => 'decimal:2',
            'monto_08' => 'decimal:2',
            'monto_09' => 'decimal:2',
            'monto_10' => 'decimal:2',
            'monto_11' => 'decimal:2',
            'monto_12' => 'decimal:2',
            'monto_total' => 'decimal:2',
        ];
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(BudgetPresupuesto::class, 'presupuesto_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }
}
