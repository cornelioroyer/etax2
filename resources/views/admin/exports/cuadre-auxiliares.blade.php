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
        .sub-row td { background: #f3f4f6; color: #555; padding-left: 14px; }
    </style>
</head>
@php $fmt = fn ($n) => number_format((float) $n, 2); @endphp
<body>
    <div class="meta">{{ $usuario ?? '' }}<br>{{ ($generado ?? now())->format('d/m/Y H:i') }}</div>
    <div class="cab">
        <h1>Cuadre de Auxiliares</h1>
        <div class="sub">{{ $compania->nombre ?? '' }}</div>
        <div class="sub">Saldos a {{ ($generado ?? now())->format('d/m/Y') }} — Cifras en B/.</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Auxiliar</th>
                <th>Cuenta de control</th>
                <th class="num">Saldo auxiliar</th>
                <th class="num">Saldo mayor</th>
                <th class="num">Diferencia</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($secciones as $s)
                <tr>
                    <td>{{ $s['titulo'] }}</td>
                    <td>
                        @if ($s['sin_cuenta']) No configurada
                        @elseif ($s['varias_cuentas']) Varias ({{ count($s['detalle']) }})
                        @elseif ($s['cuenta']) {{ $s['cuenta']->codigo }} {{ $s['cuenta']->nombre }}
                        @endif
                    </td>
                    <td class="num">{{ $fmt($s['auxiliar']) }}</td>
                    <td class="num">{{ $fmt($s['mayor']) }}</td>
                    <td class="num">{{ $fmt($s['diferencia']) }}</td>
                    <td>{{ $s['sin_cuenta'] ? 'Sin cuenta' : ($s['cuadra'] ? 'Cuadra' : 'Descuadre') }}</td>
                </tr>
                @foreach ($s['detalle'] as $d)
                    <tr class="sub-row">
                        <td></td>
                        <td>{{ $d['codigo'] }} {{ $d['nombre'] }}</td>
                        <td class="num">{{ $fmt($d['auxiliar']) }}</td>
                        <td class="num">{{ $fmt($d['mayor']) }}</td>
                        <td class="num">{{ $fmt($d['diferencia']) }}</td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
