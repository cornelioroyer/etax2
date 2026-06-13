<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerConfiguracion extends Model
{
    protected $table = 'taller_configuracion';

    protected $fillable = [
        'taller_id',
        'generar_cxc_al_facturar',
        'emitir_factura_electronica',
        'permitir_entrega_sin_pago',
        'permitir_facturar_sin_calidad',
        'fel_configuracion_id',
        'cuenta_cxc_id',
        'cuenta_ingreso_servicio_id',
        'cuenta_ingreso_repuestos_id',
        'cuenta_costo_repuestos_id',
        'cuenta_inventario_id',
        'cuenta_garantia_id',
        'cuenta_banco_default_id',
        'bco_cuenta_default_id',
        'dias_garantia_default',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'generar_cxc_al_facturar'        => 'boolean',
            'emitir_factura_electronica'       => 'boolean',
            'permitir_entrega_sin_pago'        => 'boolean',
            'permitir_facturar_sin_calidad'    => 'boolean',
        ];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }
}
