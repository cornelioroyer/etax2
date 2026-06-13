<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerEspecialidad extends Model
{
    protected $table = 'taller_especialidades';

    protected $fillable = [
        'taller_id', 'codigo', 'nombre', 'descripcion',
        'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }
}
