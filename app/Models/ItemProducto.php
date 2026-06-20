<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemProducto extends Model
{
    protected $table = 'item_productos_servicios';

    public const TIPO_PRODUCTO  = 'PRODUCTO';
    public const TIPO_SERVICIO  = 'SERVICIO';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'descripcion', 'tipo',
        'categoria_id', 'unidad_medida_id', 'precio_venta', 'costo',
        'cuenta_ingreso_id', 'cuenta_gasto_id', 'cuenta_inventario_id', 'cuenta_costo_venta_id',
        'impuesto_id', 'extra', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:4',
            'costo'        => 'decimal:4',
            'activo'       => 'boolean',
            'extra'        => 'array',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ItemCategoria::class, 'categoria_id');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(ItemUnidadMedida::class, 'unidad_medida_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(TaxImpuesto::class, 'impuesto_id');
    }

    public function cuentaIngreso(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_ingreso_id');
    }

    public function cuentaGasto(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_gasto_id');
    }

    public function cuentaInventario(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_inventario_id');
    }

    public function cuentaCostoVenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_costo_venta_id');
    }
}
