<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contacto extends Model
{
    public const FORMA_PAGO_CONTADO = 'CONTADO';
    public const FORMA_PAGO_CREDITO = 'CREDITO';

    /** Concepto de compra DGI por defecto: Compra o Adquisiciones. */
    public const CONCEPTO_DEFAULT = '1';

    /** Conceptos de compra DGI para proveedores (valor => etiqueta). */
    public const CONCEPTOS = [
        '1' => 'Compra o Adquisiciones (1)',
        '2' => 'Servicios Básicos (2)',
        '3' => 'Honorarios y Comisiones (3)',
        '4' => 'Alquileres por Arrendamientos Comerciales (4)',
        '5' => 'Cargos Bancarios, Intereses y otros Gastos Financieros (5)',
        '6' => 'Compras o Servicios al Exterior (6)',
        '7' => 'Compras o Servicios Consolidados (7)',
    ];

    /** Etiqueta legible del concepto (o el código crudo si no está catalogado). */
    public function conceptoEtiqueta(): ?string
    {
        return $this->concepto ? (self::CONCEPTOS[$this->concepto] ?? $this->concepto) : null;
    }

    protected $table = 'contact_contactos';

    protected $fillable = [
        'compania_id',
        'codigo',
        'nombre',
        'razon_social',
        'tipo_persona',
        'identificacion',
        'dv',
        'forma_pago',
        'dias_credito',
        'email',
        'telefono',
        'direccion',
        'pais',
        'provincia',
        'distrito',
        'cuenta_gasto_id',
        'concepto',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'dias_credito' => 'integer',
        ];
    }

    /** True si el contacto opera a crédito. */
    public function esCredito(): bool
    {
        return $this->forma_pago === self::FORMA_PAGO_CREDITO;
    }

    /**
     * Calcula la fecha de vencimiento para un documento de este contacto.
     * Si es a crédito usa los días de crédito configurados (default 30 si
     * está marcado como crédito pero sin días); si es contado, vence el mismo día.
     */
    public function calcularVencimiento(\Carbon\Carbon|string $fecha): string
    {
        $base = $fecha instanceof \Carbon\Carbon ? $fecha->copy() : \Carbon\Carbon::parse($fecha);

        if ($this->esCredito()) {
            return $base->addDays((int) ($this->dias_credito ?: 30))->format('Y-m-d');
        }

        return $base->format('Y-m-d');
    }

    public function tipos(): BelongsToMany
    {
        return $this->belongsToMany(TipoContacto::class, 'contact_contactos_tipos', 'contacto_id', 'tipo_id');
    }

    public function cuentaGasto()
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_gasto_id');
    }

    public function esTipo(string $codigo): bool
    {
        return $this->tipos->contains('codigo', $codigo);
    }
}
