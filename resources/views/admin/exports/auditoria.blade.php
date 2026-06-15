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
    </style>
</head>
<body>
    <div class="meta">{{ $usuario ?? '' }}<br>{{ ($generado ?? now())->format('d/m/Y H:i') }}</div>
    <div class="cab">
        <h1>Auditoría de usuarios</h1>
        <div class="sub">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha y hora</th>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Detalle</th>
                <th>Compañía</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $r)
                <tr>
                    <td>{{ \App\Support\Fechas::hora($r->created_at) }}</td>
                    <td>{{ $r->usuario?->name ?: $r->usuario_nombre ?: '—' }}</td>
                    <td>{{ $etiquetas[$r->evento] ?? $r->evento }}</td>
                    <td>{{ $r->descripcion ?: $r->entidad }}</td>
                    <td>{{ $companias[$r->compania_id]->nombre ?? '—' }}</td>
                    <td>{{ $r->ip ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center">Sin actividad en el rango.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
