<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerChecklistDetalle extends Model
{
    protected $table = 'taller_checklist_detalle';

    public $timestamps = false;

    public const TIPOS_RESPUESTA = [
        'si_no'  => 'Sí / No',
        'texto'  => 'Texto',
        'numero' => 'Número',
        'fecha'  => 'Fecha',
        'lista'  => 'Lista de opciones',
    ];

    protected $fillable = [
        'checklist_id', 'codigo', 'descripcion', 'tipo_respuesta',
        'obligatorio', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo'      => 'boolean',
            'obligatorio' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TallerChecklist::class, 'checklist_id');
    }
}
