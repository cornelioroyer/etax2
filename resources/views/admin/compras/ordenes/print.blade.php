<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Orden de Compra {{ $orden->numero }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy: #0d2d5e;
    --navy-soft: #1b3f7a;
    --accent: #2563eb;
    --line: #e5e7eb;
    --muted: #6b7280;
    --bg-soft: #f8fafc;
  }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #1f2937;
    background: #eef2f7;
    padding: 24px;
  }

  .sheet {
    max-width: 820px;
    margin: 0 auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 6px 24px rgba(13, 45, 94, .12);
    overflow: hidden;
  }

  .no-print { max-width: 820px; margin: 0 auto 16px; display: flex; gap: 10px; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { box-shadow: none; border-radius: 0; max-width: none; }
    .no-print { display: none; }
  }

  /* ---- Cabecera ---- */
  .top { background: var(--navy); color: #fff; padding: 26px 36px; display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
  .brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
  .brand .logo { height: 64px; width: auto; max-width: 180px; object-fit: contain; background: #fff; border-radius: 8px; padding: 6px; }
  .brand .empresa h1 { font-size: 20px; font-weight: 700; line-height: 1.15; }
  .brand .empresa p { font-size: 12px; color: #c5d3ee; margin-top: 3px; }

  .doc { text-align: right; flex-shrink: 0; }
  .doc .tipo { font-size: 13px; letter-spacing: 3px; text-transform: uppercase; color: #9db8e6; font-weight: 600; }
  .doc .numero { font-size: 26px; font-weight: 800; margin-top: 2px; }
  .doc .estado { display: inline-block; margin-top: 8px; padding: 3px 12px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; background: #fff; color: var(--navy); }

  /* ---- Cuerpo ---- */
  .body { padding: 28px 36px 36px; }

  .info { display: grid; grid-template-columns: 1.4fr 1fr; gap: 28px; margin-bottom: 26px; }
  .info .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 6px; font-weight: 700; }
  .proveedor-nombre { font-size: 16px; font-weight: 700; color: var(--navy); }
  .proveedor-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }

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
  .imp-tag { color: var(--muted); font-size: 11px; }

  /* ---- Totales ---- */
  .totales { display: flex; justify-content: flex-end; margin-top: 18px; }
  .totales table { width: 320px; margin: 0; }
  .totales td { padding: 6px 4px; border: none; }
  .totales .t-label { color: var(--muted); }
  .totales .t-total td { border-top: 2px solid var(--navy); padding-top: 10px; font-size: 16px; font-weight: 800; color: var(--navy); }

  .notas { border-left: 4px solid var(--accent); background: var(--bg-soft); border-radius: 0 8px 8px 0; padding: 12px 16px; margin-top: 28px; font-size: 12px; color: #374151; }
  .notas strong { display: block; margin-bottom: 4px; color: var(--navy); text-transform: uppercase; font-size: 10.5px; letter-spacing: .8px; }

  .firmas { margin-top: 56px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
  .firma-box { border-top: 1.5px solid #9ca3af; text-align: center; padding-top: 6px; font-size: 11px; color: var(--muted); }

  .pie { margin-top: 30px; padding-top: 14px; border-top: 1px solid var(--line); text-align: center; font-size: 11px; color: var(--muted); }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()"
          style="background:#0d2d5e;color:#fff;border:none;padding:9px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600">
    Imprimir / Guardar PDF
  </button>
  <button onclick="window.close()"
          style="background:#fff;color:#374151;border:1px solid #d1d5db;padding:9px 20px;border-radius:6px;cursor:pointer;font-size:13px">
    Cerrar
  </button>
</div>

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
        <p>
          @if ($compania?->telefono)Tel: {{ $compania->telefono }}@endif
          @if ($compania?->email) · {{ $compania->email }}@endif
        </p>
      </div>
    </div>
    <div class="doc">
      <div class="tipo">Orden de Compra</div>
      <div class="numero">{{ $orden->numero }}</div>
      <div class="estado">{{ ucfirst(strtolower(str_replace('_', ' ', $orden->estado))) }}</div>
    </div>
  </div>

  <div class="body">

    <div class="info">
      <div>
        <div class="label">Proveedor</div>
        <div class="proveedor-nombre">{{ $orden->proveedor->nombre ?? '—' }}</div>
        @if ($orden->proveedor?->identificacion)
          <div class="proveedor-sub">RUC: {{ $orden->proveedor->identificacion }}@if($orden->proveedor?->dv) DV {{ $orden->proveedor->dv }}@endif</div>
        @endif
        @if ($orden->proveedor?->direccion)
          <div class="proveedor-sub">{{ $orden->proveedor->direccion }}</div>
        @endif
        @if ($orden->proveedor?->email)
          <div class="proveedor-sub">{{ $orden->proveedor->email }}</div>
        @endif
      </div>
      <dl class="fechas">
        <div class="box">
          <dt>Fecha</dt>
          <dd>{{ $orden->fecha->format('d/m/Y') }}</dd>
        </div>
        <div class="box">
          <dt>N° Orden</dt>
          <dd>{{ $orden->numero }}</dd>
        </div>
      </dl>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:34px">#</th>
          <th>Descripción</th>
          <th class="num" style="width:70px">Cant.</th>
          <th class="num" style="width:90px">Precio</th>
          <th class="num" style="width:120px">ITBMS</th>
          <th class="num" style="width:95px">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($orden->detalle as $linea)
        @php
            $impMonto = round((float) $linea->cantidad * (float) $linea->precio_unitario * (float) ($linea->impuesto->porcentaje ?? 0) / 100, 2);
        @endphp
        <tr>
          <td>{{ $linea->linea }}</td>
          <td>{{ $linea->descripcion }}</td>
          <td class="num">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
          <td class="num">B/. {{ number_format((float) $linea->precio_unitario, 2) }}</td>
          <td class="num">{{ $linea->impuesto?->nombre ?? 'Exento' }}<br><span class="imp-tag">B/. {{ number_format($impMonto, 2) }}</span></td>
          <td class="num">B/. {{ number_format((float) $linea->total_linea, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="totales">
      <table>
        <tr>
          <td class="t-label">Subtotal</td>
          <td class="num">B/. {{ number_format((float) $orden->subtotal, 2) }}</td>
        </tr>
        @if ((float) $orden->itbms > 0)
        <tr>
          <td class="t-label">ITBMS</td>
          <td class="num">B/. {{ number_format((float) $orden->itbms, 2) }}</td>
        </tr>
        @endif
        <tr class="t-total">
          <td>Total</td>
          <td class="num">B/. {{ number_format((float) $orden->total, 2) }}</td>
        </tr>
      </table>
    </div>

    @if ($orden->observaciones)
    <div class="notas">
      <strong>Observaciones</strong>
      {{ $orden->observaciones }}
    </div>
    @endif

    <div class="firmas">
      <div class="firma-box">Elaborado por</div>
      <div class="firma-box">Aprobado por</div>
    </div>

    <div class="pie">
      Documento generado por eTax2. Montos expresados en Balboas (B/.).
    </div>

  </div>
</div>

</body>
</html>
