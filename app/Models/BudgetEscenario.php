<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetEscenario extends Model
{
    protected $table = 'budget_escenarios';

    protected $fillable = [
        'compania_id',
        'nombre',
        'created_by',
        'updated_by',
    ];

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(BudgetPresupuesto::class, 'escenario_id');
    }
}
