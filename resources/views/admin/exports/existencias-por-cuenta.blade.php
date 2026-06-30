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
        .grupo td { background: #e8eef7; font-weight: bold; color: #0d2d5e; }
        .subtotal td { background: #f3f4f6; font-weight: bold; }
        tfoot td { background: #0d2d5e; color: #fff; font-weight: bold; }
    </style>
</head>
@php
    $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 4), '0'), '.');
    $fmt = fn ($n) => number_format((float) $n, 2);
@endphp
<body>
    <div class="meta">{{ $usuario ?? '' }}<br>{{ ($generado ?? now())->format('d/m/Y H:i') }}</div>
    <div class="cab">
        <h1>Existencia por Cuenta de Mayor</h1>
        <div class="sub">{{ $compania->nombre ?? '' }}</div>
        <div class="sub">Existencias a {{ ($generado ?? now())->format('d/m/Y') }} — Cifras en B/.</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Descripción</th>
                <th class="num">Cantidad</th>
                <th class="num">Costo unitario</th>
                <th class="num">Costo</th>
            </tr>
        </thead>
        @forelse ($grupos as $g)
            <tbody>
                <tr class="grupo">
                    <td colspan="5">
                        @if ($g['cuenta_id']){{ $g['cuenta_codigo'] }} — {{ $g['cuenta_nombre'] }}@else{{ $g['cuenta_nombre'] }}@endif
                    </td>
                </tr>
                @foreach ($g['lineas'] as $l)
                    <tr>
                        <td>{{ $l['codigo'] }}</td>
                        <td>{{ $l['descripcion'] }}</td>
                        <td class="num {{ $l['cantidad'] < 0 ? 'neg' : '' }}">{{ $cant($l['cantidad']) }}</td>
                        <td class="num">{{ number_format((float) $l['costo_unitario'], 4) }}</td>
                        <td class="num {{ $l['costo'] < 0 ? 'neg' : '' }}">{{ $fmt($l['costo']) }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="2" class="num">Subtotal {{ $g['cuenta_codigo'] ?: '' }}</td>
                    <td class="num {{ $g['totalCantidad'] < 0 ? 'neg' : '' }}">{{ $cant($g['totalCantidad']) }}</td>
                    <td></td>
                    <td class="num">{{ $fmt($g['totalCosto']) }}</td>
                </tr>
            </tbody>
        @empty
            <tbody>
                <tr><td colspan="5">Sin existencias para los filtros seleccionados.</td></tr>
            </tbody>
        @endforelse
        @if (count($grupos))
            <tfoot>
                <tr>
                    <td colspan="2" class="num">Total general</td>
                    <td class="num">{{ $cant($totalCantidad) }}</td>
                    <td></td>
                    <td class="num">{{ $fmt($totalCosto) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
