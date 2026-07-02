<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo de pago — {{ $empleado->codigo }} — {{ $planilla->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 4px 6px; text-align: left; }
        th { border-bottom: 1px solid #999; font-size: 10px; text-transform: uppercase; color: #555; }
        td.num, th.num { text-align: right; font-family: monospace; }
        .totales td { border-top: 1px solid #999; font-weight: bold; }
        .encabezado { display: flex; justify-content: space-between; }
        .firma { margin-top: 60px; display: flex; gap: 60px; }
        .firma div { border-top: 1px solid #333; width: 220px; padding-top: 4px; text-align: center; font-size: 10px; color: #555; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right; margin-bottom:8px;">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <div class="encabezado">
        <div>
            <h1>Recibo de pago</h1>
            <div class="muted">Planilla {{ $planilla->numero }} — {{ $planilla->periodo?->etiqueta() }}</div>
        </div>
        <div style="text-align:right;">
            <div><b>{{ $empleado->codigo }} — {{ trim($empleado->nombre.' '.$empleado->apellido) }}</b></div>
            @if ($empleado->cedula)<div class="muted">Cédula: {{ $empleado->cedula }}</div>@endif
            @if ($empleado->seguro_social)<div class="muted">S.S.: {{ $empleado->seguro_social }}</div>@endif
        </div>
    </div>

    @php
        $ingresos = $movimientos->filter(fn ($m) => $m->concepto->tipo === 'INGRESO');
        $deducciones = $movimientos->filter(fn ($m) => $m->concepto->tipo === 'DEDUCCION');
        $totalIngresos = $ingresos->sum(fn ($m) => (float) $m->monto);
        $totalDeducciones = $deducciones->sum(fn ($m) => (float) $m->monto);
    @endphp

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Concepto</th>
                <th class="num">Cantidad</th>
                <th class="num">Ingreso B/.</th>
                <th class="num">Deducción B/.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movimientos as $m)
                <tr>
                    <td>{{ $m->concepto->codigo }}</td>
                    <td>{{ $m->concepto->descripcion }}{{ $m->descripcion ? ' — '.$m->descripcion : '' }}</td>
                    <td class="num">{{ $m->cantidad ? number_format((float) $m->cantidad, 2) : '' }}</td>
                    <td class="num">{{ $m->concepto->tipo === 'INGRESO' ? number_format((float) $m->monto, 2) : '' }}</td>
                    <td class="num">{{ $m->concepto->tipo === 'DEDUCCION' ? number_format((float) $m->monto, 2) : '' }}</td>
                </tr>
            @endforeach
            <tr class="totales">
                <td colspan="3">Totales</td>
                <td class="num">{{ number_format($totalIngresos, 2) }}</td>
                <td class="num">{{ number_format($totalDeducciones, 2) }}</td>
            </tr>
            <tr class="totales">
                <td colspan="4">NETO A PAGAR</td>
                <td class="num">B/. {{ number_format($totalIngresos - $totalDeducciones, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="firma">
        <div>Empleado</div>
        <div>Empleador</div>
    </div>
</body>
</html>
