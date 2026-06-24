<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxImpuesto extends Model
{
    protected $table = 'tax_impuestos';

    protected $fillable = ['compania_id', 'codigo', 'nombre', 'tipo', 'porcentaje', 'activo'];

    /**
     * Fuente canónica de las tasas ITBMS de Panamá (porcentajes enteros).
     *
     * Toda validación o cálculo de ITBMS debe derivar de aquí en lugar de
     * repetir el arreglo [0, 7, 10, 15]. Las filas globales de `tax_impuestos`
     * y los factores FEL (ver DGI_CODIGO_POR_PORCENTAJE) deben mantenerse
     * consistentes con esta constante. Ver docs/DECISIONES.md → D-06.
     */
    public const PORCENTAJES_ITBMS = [0, 7, 10, 15];

    /**
     * Código del catálogo de la DGI (campo dTasaITBMS del XML FEL) por
     * porcentaje. Es el puente entre el porcentaje contable y el código que
     * exige la facturación electrónica.
     */
    public const DGI_CODIGO_POR_PORCENTAJE = [0 => '00', 7 => '01', 10 => '02', 15 => '03'];

    /**
     * Factor (fracción) de ITBMS a partir de un código DGI ('00'..'03'),
     * derivado de los porcentajes canónicos. Devuelve 0.0 si el código no
     * pertenece al catálogo.
     */
    public static function factorItbmsPorCodigoDgi(string $codigoDgi): float
    {
        $porcentaje = array_search($codigoDgi, self::DGI_CODIGO_POR_PORCENTAJE, true);

        return $porcentaje === false ? 0.0 : $porcentaje / 100;
    }

    protected function casts(): array
    {
        return ['porcentaje' => 'decimal:2', 'activo' => 'boolean'];
    }

    /** Las 4 tasas ITBMS globales (compania_id null) ordenadas por porcentaje. */
    public static function itbmsGlobales()
    {
        return static::whereNull('compania_id')
            ->where('tipo', 'VENTAS')
            ->where('activo', true)
            ->orderBy('porcentaje')
            ->get(['id', 'codigo', 'nombre', 'porcentaje']);
    }
}
