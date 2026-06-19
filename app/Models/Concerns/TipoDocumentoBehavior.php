<?php

namespace App\Models\Concerns;

use App\Models\TipoDocumento;

/**
 * Comportamiento contable de un documento derivado del maestro
 * core_tipos_documento. Lo usan TODOS los modelos de documento (ventas,
 * compras, CxC, CxP) para que "¿suma o resta?", el prefijo de numeración y
 * "¿genera saldo?" tengan UNA sola definición en datos, no copias en código.
 *
 * El modelo que lo usa solo debe declarar su submayor:
 *
 *   protected static function auxiliarSubmayor(): string
 *   {
 *       return TipoDocumento::AUX_CXC;
 *   }
 */
trait TipoDocumentoBehavior
{
    /** Submayor al que pertenece el documento (CXC, CXP, …). */
    abstract protected static function auxiliarSubmayor(): string;

    /** Signo del documento en el submayor (+1 cargo, -1 abono). */
    public function signoDocumento(): int
    {
        return TipoDocumento::signo(static::auxiliarSubmayor(), (string) $this->tipo_documento);
    }

    /** Cargo: aumenta el saldo del submayor (facturas, notas de débito). */
    public function esCargo(): bool
    {
        return $this->signoDocumento() > 0;
    }

    /** Abono: reduce el saldo del submayor (cobros/pagos, notas de crédito). */
    public function esAbono(): bool
    {
        return ! $this->esCargo();
    }

    /** ¿Es un contra-documento que revierte otro? (NC revierte factura). */
    public function esReversa(): bool
    {
        return TipoDocumento::esReversa(static::auxiliarSubmayor(), (string) $this->tipo_documento);
    }

    /** Tipos que generan saldo (cobrable/pagable) en este submayor. */
    public static function tiposConSaldo(): array
    {
        return TipoDocumento::tiposCobrables(static::auxiliarSubmayor());
    }

    /** Prefijo de numeración del tipo según el maestro (null = sin serie propia). */
    public static function prefijoDe(string $tipo): ?string
    {
        return TipoDocumento::prefijo(static::auxiliarSubmayor(), $tipo);
    }
}
