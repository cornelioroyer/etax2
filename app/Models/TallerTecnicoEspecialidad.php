<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerTecnicoEspecialidad extends Model
{
    protected $table = 'taller_tecnico_especialidades';

    public $timestamps = false;

    protected $fillable = [
        'tecnico_id', 'especialidad_id', 'nivel', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(TallerTecnico::class, 'tecnico_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(TallerEspecialidad::class, 'especialidad_id');
    }
}
