<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FelDocumento extends Model
{
    protected $table = 'fel_documentos';

    protected $fillable = [
        'compania_id',
        'tipo_documento',
        'documento_origen',
        'documento_id',
        'numero',
        'fecha',
        'cliente_id',
        'subtotal',
        'itbms',
        'total',
        'estado_fel',
        'cufe',
        'qr',
        'xml_path',
        'pdf_path',
        'respuesta_dgi',
        'fecha_envio',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_envio' => 'datetime',
            'respuesta_dgi' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(FelDocumentoDetalle::class, 'fel_documento_id')->orderBy('linea');
    }
}
