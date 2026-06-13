<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerOrdenChecklist extends Model
{
    protected $table = 'taller_orden_checklist';

    protected $fillable = [
        'orden_id',
        'checklist_id',
        'tipo_checklist',
        'estado',
        'observacion',
        'created_by',
        'updated_by',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(TallerOrdenChecklistDetalle::class, 'orden_checklist_id');
    }
}
