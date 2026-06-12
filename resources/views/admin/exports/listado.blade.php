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
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="sub">
        {{ $compania }}<br>
        {{ $subtitulo }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($encabezados as $col)
                    <th class="{{ ($col['num'] ?? false) ? 'num' : '' }}">{{ $col['titulo'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $i => $celda)
                        <td class="{{ ($encabezados[$i]['num'] ?? false) ? 'num' : '' }}">{{ $celda }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($encabezados) }}">Sin registros.</td></tr>
            @endforelse
        </tbody>
        @if (! empty($totales))
            <tfoot>
                <tr>
                    @foreach ($totales as $i => $celda)
                        <td class="{{ ($encabezados[$i]['num'] ?? false) ? 'num' : '' }}">{{ $celda }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
