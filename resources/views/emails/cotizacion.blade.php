<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
  .wrapper { max-width: 640px; margin: 24px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
  .header { background: #0d2d5e; color: #fff; padding: 24px 32px; }
  .header h1 { margin: 0; font-size: 20px; }
  .header p { margin: 4px 0 0; font-size: 13px; opacity: .8; }
  .body { padding: 24px 32px; }
  .meta { display: flex; gap: 32px; margin-bottom: 20px; }
  .meta div { font-size: 13px; }
  .meta dt { color: #666; margin-bottom: 2px; }
  .meta dd { font-weight: 600; margin: 0; }
  .mensaje { background: #f8f9fa; border-left: 4px solid #0d2d5e; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; border-radius: 0 4px 4px 0; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead th { background: #f0f3f8; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; color: #555; }
  tbody td { padding: 8px 10px; border-bottom: 1px solid #eee; }
  .num { text-align: right; }
  tfoot td { padding: 6px 10px; font-size: 13px; }
  tfoot tr:last-child td { font-weight: 700; font-size: 14px; border-top: 2px solid #0d2d5e; }
  .footer { background: #f8f9fa; padding: 16px 32px; font-size: 12px; color: #888; text-align: center; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>{{ $compania?->nombre ?? config('app.name') }}</h1>
    <p>Cotización {{ $cotizacion->numero }}</p>
  </div>

  <div class="body">
    @if ($mensajePersonal)
    <div class="mensaje">{{ $mensajePersonal }}</div>
    @endif

    <div class="meta">
      <div>
        <dt>Cliente</dt>
        <dd>{{ $cotizacion->cliente->nombre ?? '—' }}</dd>
      </div>
      <div>
        <dt>Fecha</dt>
        <dd>{{ $cotizacion->fecha->format('d/m/Y') }}</dd>
      </div>
      @if ($cotizacion->fecha_validez)
      <div>
        <dt>Válida hasta</dt>
        <dd>{{ $cotizacion->fecha_validez->format('d/m/Y') }}</dd>
      </div>
      @endif
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Descripción</th>
          <th class="num">Cant.</th>
          <th class="num">Precio</th>
          <th class="num">ITBMS</th>
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($cotizacion->detalle as $linea)
        <tr>
          <td>{{ $linea->linea }}</td>
          <td>{{ $linea->descripcion }}</td>
          <td class="num">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
          <td class="num">B/. {{ number_format((float) $linea->precio_unitario, 2) }}</td>
          <td class="num">{{ $linea->impuesto?->nombre ?? 'Exento' }} (B/. {{ number_format((float) $linea->impuesto_monto, 2) }})</td>
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
    <div style="margin-top:20px;font-size:13px;color:#555">
      <strong>Notas / términos</strong>
      <p style="white-space:pre-line;margin-top:4px">{{ $cotizacion->notas }}</p>
    </div>
    @endif
  </div>

  <div class="footer">
    {{ $compania?->nombre ?? config('app.name') }}
    @if ($compania?->telefono) · {{ $compania->telefono }} @endif
    @if ($compania?->email) · {{ $compania->email }} @endif
  </div>
</div>
</body>
</html>
