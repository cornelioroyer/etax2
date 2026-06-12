<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FelDocumentoDetalle extends Model
{
    protected $table = 'fel_documentos_detalle';

    protected $fillable = [
        'fel_documento_id',
        'linea',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'impuesto_monto',
        'total_linea',
        'created_by',
        'updated_by',
    ];
}
