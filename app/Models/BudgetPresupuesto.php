<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPresupuesto extends Model
{
    protected $table = 'budget_presupuestos';

    const ESTADO_BORRADOR = 'BORRADOR';
    const ESTADO_APROBADO = 'APROBADO';
    const ESTADO_CERRADO  = 'CERRADO';

    const ESTADOS = [
        self::ESTADO_BORRADOR => 'Borrador',
        self::ESTADO_APROBADO => 'Aprobado',
        self::ESTADO_CERRADO  => 'Cerrado',
    ];

    protected $fillable = [
        'compania_id',
        'escenario_id',
        'version_id',
        'nombre',
        'anio',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
        ];
    }

    public function escenario(): BelongsTo
    {
        return $this->belongsTo(BudgetEscenario::class, 'escenario_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class, 'version_id');
    }

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(BudgetPresupuestoDetalle::class, 'presupuesto_id');
    }
}
