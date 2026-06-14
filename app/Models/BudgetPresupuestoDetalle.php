<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetPresupuestoDetalle extends Model
{
    protected $table = 'budget_presupuestos_detalle';

    protected $fillable = [
        'presupuesto_id',
        'periodo_id',
        'cuenta_id',
        'centro_costo_id',
        'departamento_id',
        'proyecto_id',
        'monto_presupuestado',
        'monto_real',
        'variacion',
        'porcentaje_variacion',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monto_presupuestado'  => 'decimal:2',
            'monto_real'           => 'decimal:2',
            'variacion'            => 'decimal:2',
            'porcentaje_variacion' => 'decimal:4',
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

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CoreCentroCosto::class, 'centro_costo_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(CoreDepartamento::class, 'departamento_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(CoreProyecto::class, 'proyecto_id');
    }
}
