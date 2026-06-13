<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerMarca extends Model
{
    protected $table = 'taller_marcas';

    protected $fillable = [
        'taller_id', 'codigo', 'nombre', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function modelos(): HasMany
    {
        return $this->hasMany(TallerModelo::class, 'marca_id');
    }
}
