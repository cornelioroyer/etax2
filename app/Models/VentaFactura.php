<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VentaFactura extends Model
{
    protected $table = 'ventas_facturas';

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_EMITIDA  = 'EMITIDA';
    public const ESTADO_PARCIAL  = 'PARCIAL';
    public const ESTADO_PAGADA   = 'PAGADA';
    public const ESTADO_ANULADA  = 'ANULADA';

    protected $fillable = [
        'compania_id', 'cliente_id', 'numero', 'cufe', 'fecha', 'fecha_vencimiento',
        'moneda_id', 'subtotal', 'descuento', 'itbms', 'total', 'saldo',
        'estado', 'notas', 'cotizacion_id', 'cxc_documento_id', 'asiento_id',
        'fel_documento_id', 'extra', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'             => 'date',
            'fecha_vencimiento' => 'date',
            'subtotal'          => 'decimal:2',
            'descuento'         => 'decimal:2',
            'itbms'             => 'decimal:2',
            'total'             => 'decimal:2',
            'saldo'             => 'decimal:2',
            'extra'             => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(VentaCotizacion::class, 'cotizacion_id');
    }

    public function cxcDocumento(): BelongsTo
    {
        return $this->belongsTo(CxcDocumento::class, 'cxc_documento_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(VentaFacturaDetalle::class, 'factura_id')->orderBy('linea');
    }

    public function esAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    /** Siguiente número FC- compartido con cxc_documentos. */
    public static function siguienteNumero(int $companiaId): string
    {
        return CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_FACTURA);
    }
}
