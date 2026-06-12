<?php

namespace App\Http\Controllers\Concerns;

use App\Exports\VistaExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta un reporte a PDF o Excel cuando la petición trae ?export=pdf|xlsx,
 * reutilizando la misma vista Blade para ambos formatos. Devuelve null si no
 * se solicitó exportación (el controlador sigue mostrando la vista normal).
 */
trait ExportaReporte
{
    protected function exportarReporte(Request $request, string $vista, array $datos, string $base): ?Response
    {
        return match ($request->query('export')) {
            'pdf' => Pdf::loadView($vista, $datos)->download($base.'.pdf'),
            'xlsx' => Excel::download(new VistaExport($vista, $datos), $base.'.xlsx'),
            default => null,
        };
    }
}
