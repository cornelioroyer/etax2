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
        tr.inicial td { background: #fafafa; font-weight: bold; }
        tfoot td { font-weight: bold; background: #f7f7f7; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="sub">
        {{ $compania }}<br>
        {{ $entidad }} — del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Documento</th>
                <th>Tipo</th>
                <th class="num">Cargo</th>
                <th class="num">Abono</th>
                <th class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr class="inicial">
                <td colspan="5">Saldo inicial al {{ $desde->format('d/m/Y') }}</td>
                <td class="num">{{ number_format($saldoInicial, 2) }}</td>
            </tr>
            @forelse ($movimientos as $mov)
                <tr>
                    <td>{{ $mov['doc']->fecha->format('d/m/Y') }}</td>
                    <td>{{ $mov['doc']->numero }}</td>
                    <td>{{ $mov['cargo'] > 0 ? $tipoCargo : $tipoAbono }}</td>
                    <td class="num">{{ $mov['cargo'] > 0 ? number_format($mov['cargo'], 2) : '' }}</td>
                    <td class="num">{{ $mov['abono'] > 0 ? number_format($mov['abono'], 2) : '' }}</td>
                    <td class="num">{{ number_format($mov['saldo'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sin movimientos en el período.</td></tr>
            @endforelse
        </tbody>
        @if (count($movimientos))
            <tfoot>
                <tr>
                    <td colspan="3">TOTAL DEL PERÍODO</td>
                    <td class="num">{{ number_format($totalCargos, 2) }}</td>
                    <td class="num">{{ number_format($totalAbonos, 2) }}</td>
                    <td class="num">{{ number_format(end($movimientos)['saldo'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
