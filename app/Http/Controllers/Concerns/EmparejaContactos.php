<?php

namespace App\Http\Controllers\Concerns;

/**
 * Emparejamiento tolerante de contactos (clientes/proveedores) para los importadores
 * genéricos de Ventas y Compras. Evita duplicar contactos por diferencias menores de
 * nombre (tildes, mayúsculas, puntuación, espacios). El índice se construye una vez por
 * importación y los contactos creados durante el lote se agregan para que las filas
 * siguientes los reconozcan.
 *
 * Estructura del índice: ['ruc' => [...], 'codigo' => [...], 'nombre' => [...]]
 * (RUC y código exactos; nombre NORMALIZADO).
 */
trait EmparejaContactos
{
    /** Agrega un contacto al índice de emparejamiento (RUC, código y nombre normalizado). */
    protected function indexarContacto(array &$indice, $c): void
    {
        if (! empty($c->identificacion)) {
            $indice['ruc'][trim((string) $c->identificacion)] ??= $c;
        }
        if (! empty($c->codigo)) {
            $indice['codigo'][trim((string) $c->codigo)] ??= $c;
        }
        $norm = $this->normalizarTexto((string) $c->nombre);
        if ($norm !== '') {
            $indice['nombre'][$norm] ??= $c;
        }
    }

    /**
     * Normaliza un texto para emparejar nombres sin falsos negativos: quita tildes,
     * pasa a minúsculas, elimina puntuación y colapsa espacios. Ej. "Téc. Soluciones,
     * S.A." y "TEC SOLUCIONES SA" → "tec soluciones sa".
     */
    protected function normalizarTexto(string $s): string
    {
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }
}
