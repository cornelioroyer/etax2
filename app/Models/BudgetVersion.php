<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetVersion extends Model
{
    protected $table = 'budget_versiones';

    protected $fillable = [
        'compania_id',
        'nombre',
        'activa',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(BudgetPresupuesto::class, 'version_id');
    }
}
