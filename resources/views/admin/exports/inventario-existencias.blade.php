<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        .meta { font-size: 8px; color: #555; margin-bottom: 4px; }
        .cab { text-align: center; margin-bottom: 10px; }
        .cab h1 { font-size: 14px; margin: 0; text-transform: uppercase; }
        .cab .sub { color: #555; font-size: 9px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #0d2d5e; color: #fff; font-size: 8px; text-transform: uppercase; }
        .num { text-align: right; }
        .neg { color: #b91c1c; }
        tfoot td { background: #f3f4f6; font-weight: bold; }
    </style>
</head>
@php
    $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 4), '0'), '.');
    $fmt = fn ($n) => number_format((float) $n, 2);
@endphp
<body>
    <div class="meta">{{ $usuario ?? '' }}<br>{{ ($generado ?? now())->format('d/m/Y H:i') }}</div>
    <div class="cab">
        <h1>Existencias por Almacén</h1>
        <div class="sub">{{ $compania->nombre ?? '' }}</div>
        <div class="sub">Existencias a {{ ($generado ?? now())->format('d/m/Y') }} — Cifras en B/.</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>UM</th>
                @foreach ($columnas as $col)
                    <th class="num">{{ $col->codigo }}</th>
                @endforeach
                <th class="num">Total</th>
                <th class="num">Costo prom.</th>
                <th class="num">Valor total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $f)
                <tr>
                    <td>{{ $f['codigo'] }}</td>
                    <td>{{ $f['nombre'] }}</td>
                    <td>{{ $f['um'] }}</td>
                    @foreach ($columnas as $col)
                        @php $c = $f['porAlmacen'][$col->id] ?? null; @endphp
                        <td class="num {{ $c !== null && $c < 0 ? 'neg' : '' }}">{{ $c === null ? '' : $cant($c) }}</td>
                    @endforeach
                    <td class="num {{ $f['totalCantidad'] < 0 ? 'neg' : '' }}">{{ $cant($f['totalCantidad']) }}</td>
                    <td class="num">{{ number_format((float) $f['costoProm'], 4) }}</td>
                    <td class="num">{{ $fmt($f['valor']) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 6 + $columnas->count() }}">Sin existencias para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
        @if (count($filas))
            <tfoot>
                <tr>
                    <td colspan="3" class="num">Totales</td>
                    @foreach ($columnas as $col)
                        <td class="num">{{ $cant($totalPorAlmacen[$col->id] ?? 0) }}</td>
                    @endforeach
                    <td></td>
                    <td></td>
                    <td class="num">{{ $fmt($totalValor) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
