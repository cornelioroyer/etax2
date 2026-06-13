<x-app-layout>
    @php
        $fmt = fn($n) => number_format($n, 2);
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        $colorClass = ['blue' => 'text-blue-800 bg-blue-50', 'amber' => 'text-amber-800 bg-amber-50', 'emerald' => 'text-emerald-800 bg-emerald-50'];
        $totalClass = ['blue' => 'bg-blue-100 text-blue-900', 'amber' => 'bg-amber-100 text-amber-900', 'emerald' => 'bg-emerald-100 text-emerald-900'];
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Flujo de Efectivo</h1>
                <p class="text-sm text-slate-500">Método indirecto. Movimientos de efectivo del período desde los asientos posteados.</p>
            </div>
            <div class="flex items-center gap-2">
                @if (! $sinDatos)
                    <form method="GET" class="flex items-center gap-2">
                        <select name="mes" class="rounded-md border-slate-300 text-sm shadow-sm" onchange="this.form.submit()">
                            @foreach ($periodos->where('anio', $anio) as $p)
                                <option value="{{ $p->mes }}" @selected($p->mes == $mes)>{{ $meses[$p->mes] }}</option>
                            @endforeach
                        </select>
                        <select name="anio" class="rounded-md border-slate-300 text-sm shadow-sm" onchange="this.form.submit()">
                            @foreach ($periodos->pluck('anio')->unique() as $a)
                                <option value="{{ $a }}" @selected($a == $anio)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Imprimir</button>
            </div>
        </div>

        @if ($sinDatos)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Aún no hay asientos posteados — el flujo de efectivo se calculará cuando existan asientos contabilizados.
            </div>
        @else

        {{-- Membrete --}}
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 text-center shadow-sm print:border-0">
            <h2 class="text-lg font-bold uppercase text-slate-900">{{ $companiaActiva->nombre ?? '' }}</h2>
            @if (!empty($companiaActiva?->ruc))
                <p class="text-xs text-slate-500">RUC {{ $companiaActiva->ruc }}{{ $companiaActiva->dv ? ' DV '.$companiaActiva->dv : '' }}</p>
            @endif
            <p class="mt-1 text-sm font-semibold text-slate-700">Estado de Flujos de Efectivo</p>
            <p class="text-sm text-slate-500">Por el mes de {{ $meses[$corte->month] }} de {{ $corte->year }}</p>
            <p class="text-xs text-slate-400">Cifras en Balboas (B/.) — Método indirecto</p>
        </div>

        {{-- Efectivo inicio --}}
        <div class="mb-3 flex items-center justify-between rounded-lg border border-slate-200 bg-white px-5 py-3 shadow-sm">
            <span class="text-sm font-medium text-slate-600">Efectivo y equivalentes al inicio del período</span>
            <span class="tabular-nums font-semibold text-slate-800">B/. {{ $fmt($efectivoInicio) }}</span>
        </div>

        {{-- Secciones --}}
        <div class="space-y-4">
            @foreach ($secciones as $seccion)
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <h3 class="border-b border-slate-200 px-5 py-3 text-sm font-bold {{ $colorClass[$seccion['color']] ?? 'bg-slate-50 text-slate-700' }}">
                        {{ $seccion['titulo'] }}
                    </h3>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($seccion['items'] as $item)
                                <tr class="{{ ($item['vacio'] ?? false) ? 'text-slate-400 italic' : 'hover:bg-slate-50' }}">
                                    <td class="px-5 py-2 {{ ($item['negrita'] ?? false) ? 'font-semibold text-slate-800' : 'text-slate-700 pl-8' }}">
                                        {{ $item['nombre'] }}
                                    </td>
                                    <td class="px-5 py-2 text-right tabular-nums w-40
                                        {{ ($item['vacio'] ?? false) ? '' : ($item['monto'] >= 0 ? 'text-slate-800' : 'text-red-600') }}">
                                        @unless ($item['vacio'] ?? false)
                                            {{ $item['monto'] < 0 ? '(' . $fmt(abs($item['monto'])) . ')' : $fmt($item['monto']) }}
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="{{ $totalClass[$seccion['color']] ?? 'bg-slate-100' }}">
                                <td class="px-5 py-3 text-sm font-bold">{{ $seccion['label_total'] }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-sm font-bold w-40
                                    {{ $seccion['total'] < 0 ? 'text-red-700' : '' }}">
                                    {{ $seccion['total'] < 0 ? '(' . $fmt(abs($seccion['total'])) . ')' : $fmt($seccion['total']) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach
        </div>

        {{-- Resumen final --}}
        <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-5 py-3 shadow-sm">
                <span class="text-sm font-semibold text-slate-700">D. Variación neta de efectivo</span>
                <span class="tabular-nums font-bold text-sm {{ $variacionNeta < 0 ? 'text-red-700' : 'text-slate-800' }}">
                    B/. {{ $variacionNeta < 0 ? '(' . $fmt(abs($variacionNeta)) . ')' : $fmt($variacionNeta) }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-[#0d2d5e] px-5 py-3 shadow-sm">
                <span class="text-sm font-bold text-white">Efectivo y equivalentes al cierre del período</span>
                <span class="tabular-nums font-bold text-white text-sm">B/. {{ $fmt($efectivoFin) }}</span>
            </div>
        </div>

        <p class="mt-3 text-xs text-slate-500 print:hidden">
            Calculado desde los asientos contables posteados (cgl_saldos). Cuentas de efectivo: códigos 11xxx.
            Los valores entre paréntesis representan salidas o disminuciones de efectivo.
        </p>

        @endif
    </div>

    <style>
        @media print { aside, header { display: none !important; } }
    </style>
</x-app-layout>
