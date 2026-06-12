<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Exportación genérica a Excel a partir de una vista Blade con una tabla
 * HTML. La misma vista se reutiliza para el PDF (dompdf), de modo que cada
 * reporte mantiene una sola plantilla para ambos formatos.
 */
class VistaExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private string $vista,
        private array $datos,
    ) {}

    public function view(): View
    {
        return view($this->vista, $this->datos);
    }
}
