<x-app-layout>
    @php
        $fmt = fn ($n) => 'B/. ' . number_format($n, 2);
        $pct = fn ($n) => $activos != 0.0 ? number_format(abs($n) / abs($activos) * 100, 2) . '%' : '—';
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        @if ($sinDatos)
            <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800 print:hidden">
                {{ $companiaActiva->nombre ?? 'La compañía' }} aún no tiene asientos posteados — el estado financiero se llenará automáticamente al postear asientos en Contabilidad.
            </div>
        @endif

        {{-- Encabezado --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Estado Financiero</h1>
                <p class="text-sm text-slate-500">{{ $companiaActiva->nombre ?? '' }} — al 31 de diciembre de {{ $anio }}</p>
            </div>
            <div class="flex items-center gap-2 print:hidden">
                @if ($anios->count() > 1)
                    <form method="GET">
                        <select name="anio" onchange="this.form.submit()"
                                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($anios as $a)
                                <option value="{{ $a }}" @selected($a == $anio)>Año {{ $a }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    Exportar PDF
                </button>
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" /></svg>
                    Imprimir
                </button>
            </div>
        </div>

        {{-- Tarjetas KPI --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" /></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-blue-700">Activos Totales</p>
                        <p class="text-xl font-bold text-slate-900">{{ $fmt($activos) }}</p>
                        <p class="text-xs text-slate-500">100% del total</p>
                    </div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-50 text-red-600">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5h18M4.5 10.5V18M9.75 10.5V18M14.25 10.5V18M19.5 10.5V18M3.75 21h16.5M12 3l8.25 4.5H3.75L12 3Z" /></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-red-600">Pasivos Totales</p>
                        <p class="text-xl font-bold text-slate-900">{{ $fmt($pasivos) }}</p>
                        <p class="text-xs text-slate-500">{{ $pct($pasivos) }} del total</p>
                    </div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-emerald-600">Patrimonio</p>
                        <p class="text-xl font-bold text-slate-900">{{ $fmt($patrimonio) }}</p>
                        <p class="text-xs text-slate-500">{{ $pct($patrimonio) }} del total</p>
                    </div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" /></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-indigo-600">Utilidad Neta {{ $anio }}</p>
                        <p class="text-xl font-bold {{ $utilidadAnio < 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $fmt($utilidadAnio) }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $ingresos != 0.0 ? number_format($utilidadAnio / $ingresos * 100, 2) . '% margen neto' : 'sin ingresos en el año' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Gráficas --}}
        <div class="mb-6 grid gap-4 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <h3 class="mb-4 text-sm font-semibold text-slate-900">1. Estado de Resultados {{ $anio }}</h3>
                <div class="h-72"><canvas id="chartResultados"></canvas></div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <h3 class="mb-4 text-sm font-semibold text-slate-900">2. Composición de Activos</h3>
                <div class="h-72"><canvas id="chartActivos"></canvas></div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm xl:col-span-1">
                <h3 class="mb-4 text-sm font-semibold text-slate-900">3. Pasivos y Patrimonio</h3>
                <div class="h-72"><canvas id="chartPasivos"></canvas></div>
            </div>
        </div>

        {{-- Detalle financiero --}}
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-200 px-5 py-4 text-sm font-semibold text-slate-900">4. Detalle Financiero</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Concepto</th>
                            <th class="px-5 py-3">Detalle</th>
                            <th class="px-5 py-3 text-right">Total (B/.)</th>
                            <th class="px-5 py-3 text-right">% del Activo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($detalle as $fila)
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $fila['seccion'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $fila['grupo'] }}</td>
                                <td class="px-5 py-3 text-right font-semibold {{ $fila['color'] }}">{{ number_format($fila['total'], 2) }}</td>
                                <td class="px-5 py-3 text-right text-slate-600">{{ $pct($fila['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">Sin movimientos contables registrados.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="2" class="px-5 py-3 text-sm font-bold text-[#0d2d5e]">ACTIVOS = PASIVOS + PATRIMONIO</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-[#0d2d5e]">{{ number_format($activos, 2) }}</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-[#0d2d5e]">100.00%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <p class="mt-4 flex items-center gap-2 text-xs text-slate-500">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
            Estados financieros generados desde los asientos contables posteados al período {{ $anio }}.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const azul = '#2563eb', rojo = '#dc2626', verde = '#16a34a';
            const moneda = v => 'B/. ' + Number(v).toLocaleString('es-PA', { minimumFractionDigits: 2 });
            const paleta = ['#2563eb', '#60a5fa', '#16a34a', '#fbbf24', '#7c3aed', '#dc2626', '#0ea5e9', '#f97316', '#14b8a6', '#a855f7', '#84cc16', '#64748b'];

            const cascada = @json($cascada);
            const activosGrupos = @json($activosGrupos);
            const pasivosGrupos = @json($pasivosGrupos);
            const patrimonioTotal = {{ json_encode($patrimonio) }};

            // 1. Estado de Resultados (cascada simplificada)
            const colorCascada = { pos: azul, neg: rojo, sub: azul, total: verde };
            new Chart(document.getElementById('chartResultados'), {
                type: 'bar',
                data: {
                    labels: cascada.map(f => f.label),
                    datasets: [{
                        data: cascada.map(f => f.valor),
                        backgroundColor: cascada.map(f => colorCascada[f.tipo]),
                        borderRadius: 3,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => moneda(ctx.raw) } } },
                    scales: {
                        x: { ticks: { font: { size: 8 }, maxRotation: 90, minRotation: 45 }, grid: { display: false } },
                        y: { ticks: { font: { size: 10 }, callback: v => (v / 1000) + 'k' } },
                    },
                },
            });

            // 2. Composición de Activos (dona)
            const totalActivos = activosGrupos.reduce((s, g) => s + Math.abs(g.total), 0) || 1;
            new Chart(document.getElementById('chartActivos'), {
                type: 'doughnut',
                data: {
                    labels: activosGrupos.map(g => `${g.nombre} (${(g.total / totalActivos * 100).toFixed(1)}%)`),
                    datasets: [{
                        data: activosGrupos.map(g => g.total),
                        backgroundColor: activosGrupos.map((_, i) => paleta[i % paleta.length]),
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '58%',
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } },
                        tooltip: { callbacks: { label: ctx => ' ' + moneda(ctx.raw) } },
                    },
                },
            });

            // 3. Pasivos y Patrimonio (barra apilada)
            new Chart(document.getElementById('chartPasivos'), {
                type: 'bar',
                data: {
                    labels: [''],
                    datasets: [
                        ...pasivosGrupos.map((g, i) => ({ label: g.nombre, data: [g.total], backgroundColor: i % 2 ? azul : rojo })),
                        { label: 'Patrimonio', data: [patrimonioTotal], backgroundColor: verde },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } },
                        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + moneda(ctx.raw) } },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, ticks: { font: { size: 10 }, callback: v => (v / 1000) + 'k' } },
                    },
                },
            });
        });
    </script>

    <style>
        @media print {
            aside { display: none !important; }
        }
    </style>
</x-app-layout>
