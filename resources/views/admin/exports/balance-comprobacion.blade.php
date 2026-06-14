<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        .cab { text-align: center; margin-bottom: 10px; }
        .cab h1 { font-size: 14px; margin: 0; text-transform: uppercase; }
        .cab .titulo { font-size: 12px; font-weight: bold; margin: 4px 0 0; text-transform: uppercase; }
        .cab .sub { color: #555; font-size: 9px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; }
        th { background: #0d2d5e; color: #fff; text-align: right; font-size: 9px; text-transform: uppercase; }
        th.txt { text-align: left; }
        td.num { text-align: right; }
        tr.grupo td { background: #e8edf5; font-weight: bold; text-transform: uppercase; }
        tr.suma td { background: #f3f3f3; font-weight: bold; }
        tr.suma td.lbl { text-align: right; text-transform: uppercase; font-size: 9px; }
        tfoot td { font-weight: bold; background: #0d2d5e; color: #fff; }
    </style>
</head>
@php
    $fmt = function ($n) {
        $n = round((float) $n, 2);
        if (abs($n) < 0.005) return '0.00';
        return $n < 0 ? '(' . number_format(abs($n), 2) . ')' : number_format($n, 2);
    };
@endphp
<body>
    <div class="cab">
        <h1>{{ $compania->nombre ?? '' }}</h1>
        @if (!empty($compania?->ruc))
            <div class="sub">RUC {{ $compania->ruc }}{{ $compania->dv ? ' DV '.$compania->dv : '' }}</div>
        @endif
        <div class="titulo">Balance de Comprobación</div>
        <div class="sub">Período al {{ $corte->format('d/m/Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="txt">Cuenta</th>
                <th class="txt">Descripción</th>
                <th>Balance Inicial</th>
                <th>Débito</th>
                <th>Crédito</th>
                <th>Corriente</th>
                <th>Balance Final</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                @if ($fila['tipo'] === 'grupo')
                    <tr class="grupo">
                        <td>{{ $fila['codigo'] }}</td>
                        <td colspan="6">{{ $fila['nombre'] }}</td>
                    </tr>
                @elseif ($fila['tipo'] === 'suma')
                    <tr class="suma">
                        <td></td>
                        <td class="lbl">{{ $fila['nombre'] }}</td>
                        <td class="num">{{ $fmt($fila['inicial']) }}</td>
                        <td class="num">{{ $fmt($fila['debito']) }}</td>
                        <td class="num">{{ $fmt($fila['credito']) }}</td>
                        <td class="num">{{ $fmt($fila['corriente']) }}</td>
                        <td class="num">{{ $fmt($fila['final']) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $fila['codigo'] }}</td>
                        <td>{{ $fila['nombre'] }}</td>
                        <td class="num">{{ $fmt($fila['inicial']) }}</td>
                        <td class="num">{{ $fmt($fila['debito']) }}</td>
                        <td class="num">{{ $fmt($fila['credito']) }}</td>
                        <td class="num">{{ $fmt($fila['corriente']) }}</td>
                        <td class="num">{{ $fmt($fila['final']) }}</td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="7">Sin movimientos en el período.</td></tr>
            @endforelse
        </tbody>
        @if ($filas->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="2">TOTALES</td>
                    <td class="num">{{ $fmt($totales['inicial']) }}</td>
                    <td class="num">{{ $fmt($totales['debito']) }}</td>
                    <td class="num">{{ $fmt($totales['credito']) }}</td>
                    <td class="num">{{ $fmt($totales['corriente']) }}</td>
                    <td class="num">{{ $fmt($totales['final']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
