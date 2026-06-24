<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Comprobante de Pago {{ $pago->numero }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --navy: #0d2d5e; --accent: #2563eb; --line: #e5e7eb; --muted: #6b7280; --bg-soft: #f8fafc; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1f2937; background: #eef2f7; padding: 24px; }
  .sheet { max-width: 820px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 6px 24px rgba(13,45,94,.12); overflow: hidden; }
  .no-print { max-width: 820px; margin: 0 auto 16px; display: flex; gap: 10px; }
  @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; border-radius: 0; max-width: none; } .no-print { display: none; } }

  .top { background: var(--navy); color: #fff; padding: 26px 36px; display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
  .brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
  .brand .logo { height: 64px; width: auto; max-width: 180px; object-fit: contain; background: #fff; border-radius: 8px; padding: 6px; }
  .brand .empresa h1 { font-size: 20px; font-weight: 700; line-height: 1.15; }
  .brand .empresa p { font-size: 12px; color: #c5d3ee; margin-top: 3px; }
  .doc { text-align: right; flex-shrink: 0; }
  .doc .tipo { font-size: 13px; letter-spacing: 3px; text-transform: uppercase; color: #9db8e6; font-weight: 600; }
  .doc .numero { font-size: 26px; font-weight: 800; margin-top: 2px; }
  .doc .estado { display: inline-block; margin-top: 8px; padding: 3px 12px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; background: #fff; color: var(--navy); }

  .body { padding: 28px 36px 36px; }
  .info { display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; margin-bottom: 26px; }
  .info .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 6px; font-weight: 700; }
  .prov-nombre { font-size: 16px; font-weight: 700; color: var(--navy); }
  .prov-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
  .fechas { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .fechas .box { background: var(--bg-soft); border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; }
  .fechas .box dt { font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); font-weight: 700; }
  .fechas .box dd { font-weight: 700; margin-top: 3px; font-size: 13px; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  thead th { background: var(--navy); color: #fff; padding: 10px 12px; text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
  thead th:first-child { border-top-left-radius: 6px; }
  thead th:last-child { border-top-right-radius: 6px; }
  tbody td { padding: 9px 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
  tbody tr:nth-child(even) td { background: var(--bg-soft); }
  .num { text-align: right; white-space: nowrap; }

  .totales { display: flex; justify-content: flex-end; margin-top: 18px; }
  .totales table { width: 340px; margin: 0; }
  .totales td { padding: 6px 4px; border: none; }
  .totales .t-label { color: var(--muted); }
  .totales .t-total td { border-top: 2px solid var(--navy); padding-top: 10px; font-size: 16px; font-weight: 800; color: var(--navy); }

  .firmas { margin-top: 56px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
  .firma-box { border-top: 1.5px solid #9ca3af; text-align: center; padding-top: 6px; font-size: 11px; color: var(--muted); }
  .pie { margin-top: 30px; padding-top: 14px; border-top: 1px solid var(--line); text-align: center; font-size: 11px; color: var(--muted); }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" style="background:#0d2d5e;color:#fff;border:none;padding:9px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600">Imprimir / Guardar PDF</button>
  <button onclick="window.close()" style="background:#fff;color:#374151;border:1px solid #d1d5db;padding:9px 20px;border-radius:6px;cursor:pointer;font-size:13px">Cerrar</button>
</div>

@php
  $efectivo = (float) $pago->total - (float) $pago->retencion - (float) $pago->descuento;
@endphp

<div class="sheet">

  <div class="top">
    <div class="brand">
      @if ($compania?->logo_url)
        <img class="logo" src="{{ asset('storage/'.$compania->logo_url) }}" alt="Logo">
      @endif
      <div class="empresa">
        <h1>{{ $compania?->nombre ?? config('app.name') }}</h1>
        @if ($compania?->ruc)<p>RUC: {{ $compania->ruc }}@if($compania?->dv) DV {{ $compania->dv }}@endif</p>@endif
        @if ($compania?->direccion)<p>{{ $compania->direccion }}</p>@endif
      </div>
    </div>
    <div class="doc">
      <div class="tipo">Comprobante de Pago</div>
      <div class="numero">{{ $pago->numero }}</div>
      <div class="estado">{{ $pago->esAnulado() ? 'Anulado' : 'Aplicado' }}</div>
    </div>
  </div>

  <div class="body">

    <div class="info">
      <div>
        <div class="label">Pagado a</div>
        <div class="prov-nombre">{{ $pago->proveedor->nombre ?? '—' }}</div>
        @if ($pago->proveedor?->identificacion)
          <div class="prov-sub">RUC: {{ $pago->proveedor->identificacion }}@if($pago->proveedor?->dv) DV {{ $pago->proveedor->dv }}@endif</div>
        @endif
        @if ($pago->cuentaPago)
          <div class="prov-sub">Pagado desde: {{ $pago->cuentaPago->codigo }} — {{ $pago->cuentaPago->nombre }}</div>
        @endif
        @if ($pago->referencia)
          <div class="prov-sub">Referencia: {{ $pago->referencia }}</div>
        @endif
      </div>
      <dl class="fechas">
        <div class="box"><dt>Fecha</dt><dd>{{ $pago->fecha->format('d/m/Y') }}</dd></div>
        <div class="box"><dt>Asiento</dt><dd>{{ $pago->asiento->numero ?? '—' }}</dd></div>
      </dl>
    </div>

    <table>
      <thead>
        <tr>
          <th>Factura</th>
          <th>Fecha factura</th>
          <th class="num" style="width:140px">Monto aplicado</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($pago->aplicacionesComoOrigen as $aplicacion)
        <tr>
          <td>{{ $aplicacion->destino->numero ?? '—' }}</td>
          <td>{{ $aplicacion->destino?->fecha?->format('d/m/Y') ?? '—' }}</td>
          <td class="num">B/. {{ number_format((float) $aplicacion->monto_aplicado, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="3" style="color:var(--muted)">Sin aplicaciones (pago anulado).</td></tr>
        @endforelse
      </tbody>
    </table>

    <div class="totales">
      <table>
        <tr>
          <td class="t-label">Total liquidado</td>
          <td class="num">B/. {{ number_format((float) $pago->total, 2) }}</td>
        </tr>
        @if ((float) $pago->retencion_itbms > 0)
        <tr><td class="t-label">Retención ITBMS</td><td class="num">− B/. {{ number_format((float) $pago->retencion_itbms, 2) }}</td></tr>
        @endif
        @if ((float) $pago->retencion_isr > 0)
        <tr><td class="t-label">Retención ISR</td><td class="num">− B/. {{ number_format((float) $pago->retencion_isr, 2) }}</td></tr>
        @endif
        @if ((float) $pago->descuento > 0)
        <tr><td class="t-label">Descuento pronto pago</td><td class="num">− B/. {{ number_format((float) $pago->descuento, 2) }}</td></tr>
        @endif
        <tr class="t-total">
          <td>Efectivo pagado</td>
          <td class="num">B/. {{ number_format($efectivo, 2) }}</td>
        </tr>
      </table>
    </div>

    <div class="firmas">
      <div class="firma-box">Recibido por</div>
      <div class="firma-box">Autorizado por</div>
    </div>

    <div class="pie">Documento generado por eTax2. Montos expresados en Balboas (B/.).</div>

  </div>
</div>

</body>
</html>
