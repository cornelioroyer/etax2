<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class TallerOrden extends Model
{
    protected $table = 'taller_ordenes';

    public const ESTADOS = [
        'recibida'              => 'Recibida',
        'en_diagnostico'        => 'En diagnóstico',
        'esperando_aprobacion'  => 'Esperando aprobación',
        'aprobada'              => 'Aprobada',
        'en_reparacion'         => 'En reparación',
        'esperando_repuesto'    => 'Esperando repuesto',
        'servicio_externo'      => 'Servicio externo',
        'control_calidad'       => 'Control de calidad',
        'lista_entrega'         => 'Lista para entrega',
        'entregada'             => 'Entregada',
        'facturada'             => 'Facturada',
        'cerrada'               => 'Cerrada',
        'cancelada'             => 'Cancelada',
    ];

    public const PRIORIDADES = [
        'baja'    => 'Baja',
        'normal'  => 'Normal',
        'alta'    => 'Alta',
        'urgente' => 'Urgente',
    ];

    public const TIPOS_SERVICIO = [
        'diagnostico'   => 'Diagnóstico',
        'mantenimiento' => 'Mantenimiento',
        'reparacion'    => 'Reparación',
        'instalacion'   => 'Instalación',
        'garantia'      => 'Garantía',
        'inspeccion'    => 'Inspección',
        'otro'          => 'Otro',
    ];

    public const ORIGENES = [
        'mostrador'   => 'Mostrador',
        'telefono'    => 'Teléfono',
        'whatsapp'    => 'WhatsApp',
        'web'         => 'Web',
        'aseguradora' => 'Aseguradora',
        'contrato'    => 'Contrato',
        'garantia'    => 'Garantía',
        'interno'     => 'Interno',
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
            'fecha_recepcion'  => 'datetime',
            'fecha_prometida'  => 'datetime',
            'fecha_inicio'     => 'datetime',
            'fecha_fin'        => 'datetime',
            'fecha_entrega'    => 'datetime',
            'subtotal'         => 'decimal:2',
            'descuento'        => 'decimal:2',
            'impuesto'         => 'decimal:2',
            'total'            => 'decimal:2',
            'saldo'            => 'decimal:2',
            'medidor_valor'    => 'decimal:4',
        ];
    }

    public static function colorEstado(string $estado): string
    {
        return match ($estado) {
            'recibida'             => 'blue',
            'en_diagnostico'       => 'indigo',
            'esperando_aprobacion' => 'yellow',
            'aprobada'             => 'cyan',
            'en_reparacion'        => 'orange',
            'esperando_repuesto'   => 'amber',
            'servicio_externo'     => 'purple',
            'control_calidad'      => 'teal',
            'lista_entrega'        => 'green',
            'entregada'            => 'emerald',
            'facturada'            => 'gray',
            'cerrada'              => 'gray',
            'cancelada'            => 'red',
            default                => 'gray',
        };
    }

    public static function siguienteNumero(int $tallerId): string
    {
        $anio = now()->year;
        $max  = static::where('taller_id', $tallerId)
            ->whereYear('created_at', $anio)
            ->max(DB::raw("CAST(SPLIT_PART(numero, '-', 3) AS INTEGER)"));
        $seq = ($max ?? 0) + 1;

        return 'OT-' . $anio . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(TallerEquipo::class, 'equipo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function sintomas(): HasMany
    {
        return $this->hasMany(TallerOrdenSintoma::class, 'orden_id');
    }

    public function diagnosticos(): HasMany
    {
        return $this->hasMany(TallerOrdenDiagnostico::class, 'orden_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(TallerOrdenServicio::class, 'orden_id');
    }

    public function manoObra(): HasMany
    {
        return $this->hasMany(TallerOrdenManoObra::class, 'orden_id');
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(TallerOrdenRepuesto::class, 'orden_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(TallerOrdenHistorial::class, 'orden_id')
            ->orderBy('created_at', 'desc');
    }

    public function aprobaciones(): HasMany
    {
        return $this->hasMany(TallerOrdenAprobacion::class, 'orden_id');
    }

    public function controlCalidad(): HasMany
    {
        return $this->hasMany(TallerControlCalidad::class, 'orden_id')
            ->orderBy('created_at', 'desc');
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(TallerEntrega::class, 'orden_id');
    }

    public function facturacion(): HasOne
    {
        return $this->hasOne(TallerFacturacion::class, 'orden_id');
    }
}
