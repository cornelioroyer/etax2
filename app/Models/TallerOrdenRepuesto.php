<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenRepuesto extends Model
{
    protected $table = 'taller_orden_repuestos';

    public const ESTADOS = [
        'solicitado' => 'Solicitado',
        'reservado'  => 'Reservado',
        'usado'      => 'Usado',
        'devuelto'   => 'Devuelto',
        'anulado'    => 'Anulado',
    ];

    protected $fillable = [
        'orden_id',
        'item_id',
        'almacen_id',
        'inv_movimiento_id',
        'descripcion',
        'cantidad_solicitada',
        'cantidad_usada',
        'cantidad_devuelta',
        'costo_unitario',
        'precio_unitario',
        'descuento',
        'impuesto_id',
        'impuesto',
        'total',
        'estado',
        'garantia_dias',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:4',
            'cantidad_usada'      => 'decimal:4',
            'cantidad_devuelta'   => 'decimal:4',
            'costo_unitario'      => 'decimal:4',
            'precio_unitario'     => 'decimal:4',
            'descuento'           => 'decimal:2',
            'impuesto'            => 'decimal:2',
            'total'               => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }
}
