<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Factura {{ $factura->numero }}</title>
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
  .cliente-nombre { font-size: 16px; font-weight: 700; color: var(--navy); }
  .cliente-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }

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
  .totales .t-saldo td { color: #b91c1c; font-weight: 700; padding-top: 8px; }

  .notas { border-left: 4px solid var(--accent); background: var(--bg-soft); border-radius: 0 8px 8px 0; padding: 12px 16px; margin-top: 28px; font-size: 12px; color: #374151; }
  .notas strong { display: block; margin-bottom: 4px; color: var(--navy); text-transform: uppercase; font-size: 10.5px; letter-spacing: .8px; }

  .firma { margin-top: 56px; display: flex; justify-content: space-between; align-items: flex-end; }
  .firma .sello img { max-height: 90px; max-width: 200px; object-fit: contain; opacity: .9; }
  .firma-box { border-top: 1.5px solid #9ca3af; width: 230px; text-align: center; padding-top: 6px; font-size: 11px; color: var(--muted); }

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
      <div class="tipo">Factura</div>
      <div class="numero">{{ $factura->numero }}</div>
      <div class="estado">{{ ucfirst(strtolower($factura->estado)) }}</div>
    </div>
  </div>

  <div class="body">

    <div class="info">
      <div>
        <div class="label">Cliente</div>
        <div class="cliente-nombre">{{ $factura->cliente->nombre ?? '—' }}</div>
        @if ($factura->cliente?->identificacion)<div class="cliente-sub">RUC: {{ $factura->cliente->identificacion }}@if($factura->cliente?->dv) DV {{ $factura->cliente->dv }}@endif</div>@endif
        @if ($factura->cliente?->direccion)<div class="cliente-sub">{{ $factura->cliente->direccion }}</div>@endif
        @if ($factura->cliente?->email)<div class="cliente-sub">{{ $factura->cliente->email }}</div>@endif
      </div>
      <dl class="fechas">
        <div class="box">
          <dt>Fecha</dt>
          <dd>{{ $factura->fecha->format('d/m/Y') }}</dd>
        </div>
        <div class="box">
          <dt>Vencimiento</dt>
          <dd>{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</dd>
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
        @foreach ($factura->detalle as $linea)
        <tr>
          <td>{{ $linea->linea }}</td>
          <td>{{ $linea->descripcion }}</td>
          <td class="num">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
          <td class="num">B/. {{ number_format((float) $linea->precio_unitario, 2) }}</td>
          <td class="num">{{ $linea->impuesto?->nombre ?? 'Exento' }}<br><span class="imp-tag">B/. {{ number_format((float) $linea->impuesto_monto, 2) }}</span></td>
          <td class="num">B/. {{ number_format((float) $linea->total_linea, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="totales">
      <table>
        <tr>
          <td class="t-label">Subtotal</td>
          <td class="num">B/. {{ number_format((float) $factura->subtotal, 2) }}</td>
        </tr>
        @if ((float) $factura->itbms > 0)
        <tr>
          <td class="t-label">ITBMS</td>
          <td class="num">B/. {{ number_format((float) $factura->itbms, 2) }}</td>
        </tr>
        @endif
        <tr class="t-total">
          <td>Total</td>
          <td class="num">B/. {{ number_format((float) $factura->total, 2) }}</td>
        </tr>
        @if ($factura->estado !== 'ANULADA' && (float) $factura->saldo > 0 && (float) $factura->saldo < (float) $factura->total)
        <tr class="t-saldo">
          <td>Saldo pendiente</td>
          <td class="num">B/. {{ number_format((float) $factura->saldo, 2) }}</td>
        </tr>
        @endif
      </table>
    </div>

    @if (!empty($factura->notas))
    <div class="notas">
      <strong>Notas / términos</strong>
      {{ $factura->notas }}
    </div>
    @endif

    <div class="firma">
      <div class="sello">
        @if ($compania?->sello_url)
          <img src="{{ asset('storage/'.$compania->sello_url) }}" alt="Sello">
        @endif
      </div>
      <div class="firma-box">Firma autorizada</div>
    </div>

    <div class="pie">
      Documento generado por eTax2. Montos expresados en Balboas (B/.).
    </div>

  </div>
</div>

</body>
</html>
