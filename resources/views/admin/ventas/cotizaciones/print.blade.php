<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $cotizacion->numero }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 13px; color: #222; background: #fff; padding: 32px; }

  .no-print { margin-bottom: 16px; display: flex; gap: 10px; }
  @media print { .no-print { display: none; } }

  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d2d5e; padding-bottom: 16px; margin-bottom: 20px; }
  .header-left h1 { font-size: 18px; color: #0d2d5e; }
  .header-left p { font-size: 12px; color: #555; margin-top: 2px; }
  .header-right { text-align: right; }
  .header-right .numero { font-size: 20px; font-weight: 700; color: #0d2d5e; }
  .header-right .estado { display: inline-block; margin-top: 4px; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; background: #e0e7ff; color: #3730a3; }

  .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
  .meta-item dt { font-size: 10px; text-transform: uppercase; color: #888; margin-bottom: 2px; }
  .meta-item dd { font-weight: 600; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  thead th { background: #0d2d5e; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
  tbody tr:nth-child(even) td { background: #f8fafc; }
  tbody td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
  .num { text-align: right; }

  tfoot td { padding: 5px 10px; }
  tfoot tr:last-child td { font-weight: 700; font-size: 14px; border-top: 2px solid #0d2d5e; padding-top: 8px; }

  .notas { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-top: 16px; font-size: 12px; color: #444; }
  .notas strong { display: block; margin-bottom: 4px; }

  .firma { margin-top: 48px; display: flex; justify-content: flex-end; }
  .firma-box { border-top: 1px solid #999; width: 200px; text-align: center; padding-top: 6px; font-size: 11px; color: #666; }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()"
          style="background:#0d2d5e;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px">
    Imprimir / Guardar PDF
  </button>
  <button onclick="window.close()"
          style="background:#fff;color:#374151;border:1px solid #d1d5db;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px">
    Cerrar
  </button>
</div>

<div class="header">
  <div class="header-left">
    <h1>{{ $compania?->nombre ?? config('app.name') }}</h1>
    @if ($compania?->ruc)<p>RUC: {{ $compania->ruc }}</p>@endif
    @if ($compania?->telefono)<p>Tel: {{ $compania->telefono }}</p>@endif
    @if ($compania?->email)<p>{{ $compania->email }}</p>@endif
  </div>
  <div class="header-right">
    <div class="numero">{{ $cotizacion->numero }}</div>
    <div class="estado">{{ ucfirst(strtolower($cotizacion->estado)) }}</div>
  </div>
</div>

<div class="meta">
  <div class="meta-item">
    <dt>Cliente</dt>
    <dd>{{ $cotizacion->cliente->nombre ?? '—' }}</dd>
  </div>
  <div class="meta-item">
    <dt>Fecha</dt>
    <dd>{{ $cotizacion->fecha->format('d/m/Y') }}</dd>
  </div>
  <div class="meta-item">
    <dt>Válida hasta</dt>
    <dd>{{ $cotizacion->fecha_validez?->format('d/m/Y') ?? '—' }}</dd>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th style="width:36px">#</th>
      <th>Descripción</th>
      <th class="num" style="width:70px">Cant.</th>
      <th class="num" style="width:90px">Precio</th>
      <th class="num" style="width:110px">ITBMS</th>
      <th class="num" style="width:90px">Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($cotizacion->detalle as $linea)
    <tr>
      <td>{{ $linea->linea }}</td>
      <td>{{ $linea->descripcion }}</td>
      <td class="num">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
      <td class="num">B/. {{ number_format((float) $linea->precio_unitario, 2) }}</td>
      <td class="num">{{ $linea->impuesto?->nombre ?? 'Exento' }}<br><span style="color:#666">(B/. {{ number_format((float) $linea->impuesto_monto, 2) }})</span></td>
      <td class="num">B/. {{ number_format((float) $linea->total_linea, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td colspan="5" style="text-align:right;color:#666">Subtotal</td>
      <td class="num">B/. {{ number_format((float) $cotizacion->subtotal, 2) }}</td>
    </tr>
    @if ((float) $cotizacion->itbms > 0)
    <tr>
      <td colspan="5" style="text-align:right;color:#666">ITBMS</td>
      <td class="num">B/. {{ number_format((float) $cotizacion->itbms, 2) }}</td>
    </tr>
    @endif
    <tr>
      <td colspan="5" style="text-align:right">Total</td>
      <td class="num">B/. {{ number_format((float) $cotizacion->total, 2) }}</td>
    </tr>
  </tfoot>
</table>

@if ($cotizacion->notas)
<div class="notas">
  <strong>Notas / términos</strong>
  {{ $cotizacion->notas }}
</div>
@endif

<div class="firma">
  <div class="firma-box">Firma autorizada</div>
</div>

</body>
</html>
