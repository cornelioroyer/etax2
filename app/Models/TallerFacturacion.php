<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerFacturacion extends Model
{
    protected $table = 'taller_facturacion';

    public const TIPOS = [
        'factura'            => 'Factura',
        'proforma'           => 'Proforma',
        'garantia_sin_cargo' => 'Garantía sin cargo',
    ];

    protected $fillable = [
        'taller_id', 'orden_id', 'compania_id', 'cliente_id',
        'fecha', 'tipo_facturacion',
        'requiere_factura_electronica',
        'cxc_documento_id', 'fel_documento_id',
        'estado_cxc', 'estado_fel',
        'subtotal', 'descuento', 'impuesto', 'total',
        'pagado', 'saldo',
        'observacion',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'                       => 'date',
            'requiere_factura_electronica'=> 'boolean',
            'subtotal'                    => 'decimal:2',
            'descuento'                   => 'decimal:2',
            'impuesto'                    => 'decimal:2',
            'total'                       => 'decimal:2',
            'pagado'                      => 'decimal:2',
            'saldo'                       => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }
}
