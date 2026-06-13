<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrden extends Model
{
    protected $table = 'taller_ordenes';

    public const ESTADOS = [
        'recibida'    => 'Recibida',
        'diagnostico' => 'En diagnóstico',
        'aprobada'    => 'Aprobada',
        'reparacion'  => 'En reparación',
        'calidad'     => 'Control de calidad',
        'lista'       => 'Lista para entrega',
        'entregada'   => 'Entregada',
        'anulada'     => 'Anulada',
    ];

    public const PRIORIDADES = [
        'baja'   => 'Baja',
        'normal' => 'Normal',
        'alta'   => 'Alta',
        'urgente'=> 'Urgente',
    ];

    protected $fillable = [
        'taller_id', 'compania_id', 'sucursal_id', 'area_actual_id',
        'cliente_id', 'contacto_entrega_id', 'equipo_id', 'presupuesto_id', 'cita_id',
        'numero', 'fecha_recepcion', 'fecha_prometida', 'fecha_inicio', 'fecha_fin', 'fecha_entrega',
        'prioridad', 'tipo_servicio', 'origen',
        'sintomas_reportados', 'observacion_recepcion', 'medidor_valor', 'medidor_unidad',
        'estado', 'subtotal', 'descuento', 'impuesto', 'total', 'saldo',
        'garantia_dias', 'cxc_documento_id', 'fel_documento_id', 'asiento_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_recepcion'  => 'date',
            'fecha_prometida'  => 'date',
            'fecha_inicio'     => 'datetime',
            'fecha_fin'        => 'datetime',
            'fecha_entrega'    => 'datetime',
            'subtotal'         => 'decimal:2',
            'descuento'        => 'decimal:2',
            'impuesto'         => 'decimal:2',
            'total'            => 'decimal:2',
            'saldo'            => 'decimal:2',
        ];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(TallerEquipo::class, 'equipo_id');
    }
}
