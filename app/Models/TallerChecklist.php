<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerChecklist extends Model
{
    protected $table = 'taller_checklist';

    public const TIPOS = [
        'recepcion'   => 'Recepción',
        'diagnostico' => 'Diagnóstico',
        'calidad'     => 'Control de calidad',
        'entrega'     => 'Entrega',
        'garantia'    => 'Garantía',
    ];

    protected $fillable = [
        'taller_id', 'tipo_equipo_id', 'codigo', 'nombre', 'tipo_checklist',
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

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TallerTipoEquipo::class, 'tipo_equipo_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(TallerChecklistDetalle::class, 'checklist_id')->orderBy('orden');
    }
}
