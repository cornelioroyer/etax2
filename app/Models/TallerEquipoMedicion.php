<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerEquipoMedicion extends Model
{
    protected $table = 'taller_equipos_mediciones';

    public $timestamps = false;

    protected $fillable = [
        'equipo_id', 'fecha', 'tipo_medicion', 'valor',
        'unidad', 'observacion', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'float',
            'fecha' => 'datetime',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(TallerEquipo::class, 'equipo_id');
    }
}
