<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .sub { color: #555; font-size: 10px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; }
        th { background: #f0f0f0; text-align: left; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #f7f7f7; }
        .alerta { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="sub">
        {{ $compania }}<br>
        Saldos al {{ $corte->format('d/m/Y') }} — la edad se mide desde la fecha de vencimiento.
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ $entidadLabel }}</th>
                @foreach ($columnas as $clave => $titulo)
                    <th class="num">{{ $titulo }}</th>
                @endforeach
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $fila)
                <tr>
                    <td>{{ $fila['cliente']->nombre ?? '—' }}</td>
                    @foreach ($columnas as $clave => $titulo)
                        <td class="num {{ $loop->last && $fila[$clave] > 0 ? 'alerta' : '' }}">{{ number_format($fila[$clave], 2) }}</td>
                    @endforeach
                    <td class="num">{{ number_format($fila['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columnas) + 2 }}">Sin saldos pendientes.</td></tr>
            @endforelse
        </tbody>
        @if (count($clientes))
            <tfoot>
                <tr>
                    <td>TOTAL</td>
                    @foreach ($columnas as $clave => $titulo)
                        <td class="num">{{ number_format($totales[$clave], 2) }}</td>
                    @endforeach
                    <td class="num">{{ number_format($totales['total'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
