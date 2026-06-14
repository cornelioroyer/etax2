<x-app-layout>
    @php
        // Convención débito − crédito: los saldos acreedores se muestran entre paréntesis.
        $fmt = function ($n) {
            $n = round((float) $n, 2);
            if (abs($n) < 0.005) return '0.00';
            return $n < 0 ? '(' . number_format(abs($n), 2) . ')' : number_format($n, 2);
        };
        $cuadra = abs($totales['debito'] - $totales['credito']) < 0.01;
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Controles --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Balance de Comprobación</h1>
                <p class="text-sm text-slate-500">Sumas y saldos por período, desde los asientos posteados.</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <form method="GET" class="flex items-end gap-2">
                    <label class="text-xs font-medium text-slate-600">
                        Desde
                        <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                               class="mt-1 block rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                    </label>
                    <label class="text-xs font-medium text-slate-600">
                        Hasta
                        <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                               class="mt-1 block rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                    </label>
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-3 py-2 text-sm font-semibold text-white hover:bg-[#0a2347]">
                        Ver
                    </button>
                </form>

                @if (! $sinDatos)
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
                       style="background-color:#d21034;color:#fff"
                       class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold hover:opacity-90">
                        PDF
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"
                       style="background-color:#1d6f42;color:#fff"
                       class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold hover:opacity-90">
                        Excel
                    </a>
                    <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Imprimir
                    </button>
                @endif
            </div>
        </div>

        @if ($sinDatos)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $companiaActiva->nombre ?? 'La compañía' }} aún no tiene asientos posteados — el balance de comprobación se llenará al postear asientos en Contabilidad.
            </div>
        @else
            <div class="mx-auto max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">

                {{-- ░░ Encabezado del informe ░░ --}}
                <div class="relative border-b-2 border-[#0d2d5e] px-8 py-6 text-center">
                    {{-- usuario y fecha/hora en el extremo superior izquierdo --}}
                    <div class="absolute left-4 top-3 text-left text-[11px] leading-tight text-slate-500">
                        <p class="font-semibold text-slate-600">{{ $usuario }}</p>
                        <p>{{ $generado->format('d/m/Y H:i') }}</p>
                    </div>

                    <h2 class="text-xl font-extrabold uppercase tracking-wider" style="color:#0d2d5e">Balance de Comprobación</h2>
                    <p class="mt-1 text-lg font-bold" style="color:#005293">{{ $compania->nombre ?? '' }}</p>
                    @if (!empty($compania?->ruc))
                        <p class="text-xs text-slate-500">RUC {{ $compania->ruc }}{{ $compania->dv ? ' DV '.$compania->dv : '' }}</p>
                    @endif
                    <p class="mt-1 text-sm font-medium text-slate-600">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>
                </div>

                <div class="p-4 sm:p-6">
                    @unless ($cuadra)
                        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-[#d21034] print:hidden">
                            Atención: el balance no cuadra — Débito {{ number_format($totales['debito'], 2) }} ≠ Crédito {{ number_format($totales['credito'], 2) }}.
                        </div>
                    @endunless

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold uppercase tracking-wide text-white">
                                    <th class="px-3 py-3 text-left" style="background-color:#0a2347">Cuenta</th>
                                    <th class="px-3 py-3 text-left" style="background-color:#0a2347">Descripción</th>
                                    <th class="px-3 py-3 text-right" style="background-color:#0d2d5e">Balance Inicial</th>
                                    <th class="px-3 py-3 text-right" style="background-color:#0d2d5e">Débito</th>
                                    <th class="px-3 py-3 text-right" style="background-color:#0d2d5e">Crédito</th>
                                    <th class="px-3 py-3 text-right" style="background-color:#005293">Corriente</th>
                                    <th class="px-3 py-3 text-right" style="background-color:#005293">Balance Final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($filas as $fila)
                                    @if ($fila['tipo'] === 'grupo')
                                        <tr class="bg-slate-100">
                                            <td class="px-3 py-2 font-mono text-xs font-bold text-[#0d2d5e]">{{ $fila['codigo'] }}</td>
                                            <td class="px-3 py-2 font-bold uppercase tracking-wide text-slate-700" colspan="6"
                                                style="padding-left: {{ 0.75 + ($fila['nivel'] - 1) * 1 }}rem">{{ $fila['nombre'] }}</td>
                                        </tr>
                                    @elseif ($fila['tipo'] === 'suma')
                                        <tr class="border-t border-slate-300 bg-slate-50 font-semibold text-slate-800">
                                            <td class="px-3 py-2"></td>
                                            <td class="px-3 py-2 text-right uppercase tracking-wide text-xs">{{ $fila['nombre'] }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($fila['inicial']) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($fila['debito']) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($fila['credito']) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($fila['corriente']) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($fila['final']) }}</td>
                                        </tr>
                                    @else
                                        <tr class="hover:bg-blue-50/50">
                                            <td class="px-3 py-1.5 font-mono text-xs font-semibold text-[#0d2d5e]"
                                                style="padding-left: {{ 0.75 + ($fila['nivel'] - 1) * 1 }}rem">{{ $fila['codigo'] }}</td>
                                            <td class="px-3 py-1.5 text-slate-700">{{ $fila['nombre'] }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums text-slate-700">{{ $fmt($fila['inicial']) }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums text-slate-700">{{ $fmt($fila['debito']) }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums text-slate-700">{{ $fmt($fila['credito']) }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums text-slate-700">{{ $fmt($fila['corriente']) }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums font-medium text-slate-800">
                                                <button type="button" data-detalle-saldo
                                                        data-cuenta="{{ $fila['id'] }}"
                                                        data-label="{{ $fila['codigo'].' '.$fila['nombre'] }}"
                                                        class="cursor-pointer rounded px-1 font-medium text-[#005293] underline decoration-dotted underline-offset-2 hover:bg-blue-50 hover:text-[#0d2d5e] print:no-underline print:text-slate-800"
                                                        title="Ver detalle del saldo">{{ $fmt($fila['final']) }}</button>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr><td colspan="7" class="px-4 py-4 text-slate-500">Sin movimientos en el período.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($filas->isNotEmpty())
                                <tfoot>
                                    <tr class="text-white">
                                        <td class="px-3 py-3 text-base font-bold uppercase tracking-wide" colspan="2" style="background-color:#0a2347">Totales</td>
                                        <td class="px-3 py-3 text-right text-sm font-bold tabular-nums" style="background-color:#0d2d5e">{{ $fmt($totales['inicial']) }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-bold tabular-nums {{ $cuadra ? '' : 'text-red-300' }}" style="background-color:#0d2d5e">{{ $fmt($totales['debito']) }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-bold tabular-nums {{ $cuadra ? '' : 'text-red-300' }}" style="background-color:#0d2d5e">{{ $fmt($totales['credito']) }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-bold tabular-nums" style="background-color:#005293">{{ $fmt($totales['corriente']) }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-bold tabular-nums" style="background-color:#005293">{{ $fmt($totales['final']) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <p class="mt-4 text-center text-xs text-slate-400">
                        Cifras en Balboas (B/.) · Convención débito − crédito (los saldos acreedores van entre paréntesis).
                        Por partida doble, Débito y Crédito deben coincidir.
                    </p>
                </div>
            </div>
        @endif
    </div>

    @unless ($sinDatos)
        {{-- ░░ Modal: detalle del saldo de una cuenta ░░ --}}
        <div id="detalle-saldo-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 print:hidden">
            <div class="absolute inset-0 bg-slate-900/50" onclick="cerrarDetalleSaldo()"></div>
            <div class="relative flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4" style="background-color:#0d2d5e">
                    <div>
                        <h3 class="text-base font-bold text-white">Detalle del saldo</h3>
                        <p id="detalle-saldo-cuenta" class="text-sm text-slate-200"></p>
                        <p id="detalle-saldo-periodo" class="text-xs text-slate-300"></p>
                    </div>
                    <button type="button" onclick="cerrarDetalleSaldo()"
                            class="rounded-md p-1 text-slate-200 hover:bg-white/10 hover:text-white" aria-label="Cerrar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0">
                            <tr class="text-xs font-bold uppercase tracking-wide text-white">
                                <th class="px-3 py-2 text-left" style="background-color:#0a2347">Fecha</th>
                                <th class="px-3 py-2 text-left" style="background-color:#0a2347">Asiento</th>
                                <th class="px-3 py-2 text-left" style="background-color:#0a2347">Descripción</th>
                                <th class="px-3 py-2 text-right" style="background-color:#0a2347">Débito</th>
                                <th class="px-3 py-2 text-right" style="background-color:#0a2347">Crédito</th>
                                <th class="px-3 py-2 text-right" style="background-color:#005293">Saldo</th>
                            </tr>
                        </thead>
                        <tbody id="detalle-saldo-body" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-6 py-3 text-right text-xs text-slate-400">
                    Saldo en convención débito − crédito (los saldos acreedores van entre paréntesis).
                </div>
            </div>
        </div>

        <script>
            (function () {
                const ENDPOINT = @json(route('admin.reportes.comprobacion.detalle'));
                const DESDE = @json($desde->format('Y-m-d'));
                const HASTA = @json($hasta->format('Y-m-d'));

                const fmt = (n) => {
                    n = Math.round((Number(n) || 0) * 100) / 100;
                    if (Math.abs(n) < 0.005) return '0.00';
                    const s = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    return n < 0 ? '(' + s + ')' : s;
                };
                const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                const modal = document.getElementById('detalle-saldo-modal');
                const body = document.getElementById('detalle-saldo-body');

                window.cerrarDetalleSaldo = function () {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                };

                document.querySelectorAll('[data-detalle-saldo]').forEach((btn) => {
                    btn.addEventListener('click', () => verDetalleSaldo(btn.dataset.cuenta, btn.dataset.label));
                });

                window.verDetalleSaldo = async function (cuentaId, etiqueta) {
                    document.getElementById('detalle-saldo-cuenta').textContent = etiqueta;
                    document.getElementById('detalle-saldo-periodo').textContent = '';
                    body.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Cargando…</td></tr>';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');

                    try {
                        const url = ENDPOINT + '?cuenta=' + encodeURIComponent(cuentaId)
                            + '&desde=' + encodeURIComponent(DESDE) + '&hasta=' + encodeURIComponent(HASTA);
                        const resp = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!resp.ok) throw new Error('HTTP ' + resp.status);
                        const data = await resp.json();

                        document.getElementById('detalle-saldo-periodo').textContent =
                            'Del ' + data.periodo.desde + ' al ' + data.periodo.hasta;

                        let html = '<tr class="bg-slate-50 font-medium text-slate-600">'
                            + '<td class="px-3 py-2" colspan="5">Balance inicial</td>'
                            + '<td class="px-3 py-2 text-right tabular-nums">' + fmt(data.inicial) + '</td></tr>';

                        if (data.movimientos.length === 0) {
                            html += '<tr><td colspan="6" class="px-4 py-4 text-center text-slate-500">Sin movimientos en el período.</td></tr>';
                        } else {
                            for (const m of data.movimientos) {
                                html += '<tr class="hover:bg-blue-50/50">'
                                    + '<td class="px-3 py-1.5 whitespace-nowrap text-slate-700">' + esc(m.fecha) + '</td>'
                                    + '<td class="px-3 py-1.5 whitespace-nowrap font-mono text-xs text-[#0d2d5e]">' + esc(m.numero) + '</td>'
                                    + '<td class="px-3 py-1.5 text-slate-700">' + esc(m.descripcion) + '</td>'
                                    + '<td class="px-3 py-1.5 text-right tabular-nums text-slate-700">' + fmt(m.debito) + '</td>'
                                    + '<td class="px-3 py-1.5 text-right tabular-nums text-slate-700">' + fmt(m.credito) + '</td>'
                                    + '<td class="px-3 py-1.5 text-right tabular-nums font-medium text-slate-800">' + fmt(m.saldo) + '</td></tr>';
                            }
                        }

                        html += '<tr class="border-t-2 border-slate-300 bg-slate-100 font-bold text-slate-800">'
                            + '<td class="px-3 py-2 text-right uppercase tracking-wide text-xs" colspan="5">Balance final</td>'
                            + '<td class="px-3 py-2 text-right tabular-nums" style="color:#005293">' + fmt(data.final) + '</td></tr>';

                        body.innerHTML = html;
                    } catch (e) {
                        body.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-[#d21034]">No se pudo cargar el detalle.</td></tr>';
                    }
                };

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && !modal.classList.contains('hidden')) cerrarDetalleSaldo();
                });
            })();
        </script>
    @endunless

    <style>
        @media print {
            aside, header { display: none !important; }
        }
    </style>
</x-app-layout>
