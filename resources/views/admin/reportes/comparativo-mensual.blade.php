<x-app-layout>
    @php
        $fmt = fn ($n) => abs($n) < 0.005 ? '—' : number_format($n, 2);
        $nombresMes = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
                       7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Encabezado y controles --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Comparativo Mensual</h1>
                <p class="text-sm text-slate-500">Análisis mes a mes de las cuentas de resultado del año.</p>
            </div>
            <div class="flex items-center gap-2">
                @if (! $sinDatos)
                    <form method="GET" class="flex items-center gap-2">
                        <select name="anio" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                            @foreach ($periodos->pluck('anio')->unique() as $a)
                                <option value="{{ $a }}" @selected($a == $anio)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">
                    Exportar PDF
                </button>
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Imprimir
                </button>
            </div>
        </div>

        @if ($sinDatos)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $companiaActiva->nombre ?? 'La compañía' }} aún no tiene asientos posteados — el comparativo mensual se llenará al postear asientos en Contabilidad.
            </div>
        @else
            {{-- Membrete --}}
            <div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm print:border-0 print:shadow-none">
                <h2 class="text-lg font-bold uppercase text-slate-900">{{ $companiaActiva->nombre ?? '' }}</h2>
                @if (!empty($companiaActiva?->ruc))
                    <p class="text-xs text-slate-500">RUC {{ $companiaActiva->ruc }}{{ $companiaActiva->dv ? ' DV '.$companiaActiva->dv : '' }}</p>
                @endif
                <p class="mt-1 text-sm font-semibold text-slate-700">Análisis Comparativo Mensual — Cuentas de Resultado</p>
                <p class="text-sm text-slate-500">Año {{ $anio }}</p>
                <p class="text-xs text-slate-400">Cifras en Balboas (B/.)</p>
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 px-5 py-3 text-left">Concepto</th>
                            @foreach ($meses as $m)
                                <th class="w-28 px-3 py-3 text-right">{{ $nombresMes[$m] }}</th>
                            @endforeach
                            <th class="w-32 border-l border-slate-200 px-4 py-3 text-right">Total {{ $anio }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td colspan="{{ $meses->count() + 2 }}" class="px-5 pt-4 pb-1 text-sm font-bold uppercase tracking-wide {{ $seccion['color'] === 'blue' ? 'text-blue-800' : 'text-red-700' }}">
                                    {{ $seccion['titulo'] }}
                                </td>
                            </tr>
                            @forelse ($seccion['grupos'] as $grupo)
                                @if ($seccion['grupos']->count() > 1)
                                    <tr class="bg-slate-50/60">
                                        <td colspan="{{ $meses->count() + 2 }}" class="px-5 pt-2 pb-1 text-xs font-bold uppercase tracking-wide text-slate-600">{{ $grupo['grupo'] }}</td>
                                    </tr>
                                @endif
                                @foreach ($grupo['cuentas'] as $cuenta)
                                    <tr class="hover:bg-slate-50">
                                        <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-5 py-1 pl-8 text-slate-700">
                                            <span class="mr-1 font-mono text-xs text-slate-400">{{ $cuenta['codigo'] }}</span>{{ $cuenta['nombre'] }}
                                        </td>
                                        @foreach ($meses as $m)
                                            <td class="px-3 py-1 text-right tabular-nums {{ $cuenta['valores'][$m] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $fmt($cuenta['valores'][$m]) }}</td>
                                        @endforeach
                                        <td class="border-l border-slate-200 px-4 py-1 text-right font-semibold tabular-nums {{ $cuenta['total'] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $fmt($cuenta['total']) }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="{{ $meses->count() + 2 }}" class="px-5 py-2 pl-8 text-slate-500">Sin movimientos.</td></tr>
                            @endforelse
                            <tr class="border-t border-slate-200 {{ $seccion['color'] === 'blue' ? 'bg-blue-50' : 'bg-red-50' }}">
                                <td class="sticky left-0 z-10 whitespace-nowrap px-5 py-2 text-sm font-bold {{ $seccion['color'] === 'blue' ? 'bg-blue-50 text-blue-900' : 'bg-red-50 text-red-800' }}">TOTAL {{ strtoupper($seccion['titulo']) }}</td>
                                @foreach ($meses as $m)
                                    <td class="px-3 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['color'] === 'blue' ? 'text-blue-900' : 'text-red-800' }}">{{ $fmt($seccion['total']['valores'][$m]) }}</td>
                                @endforeach
                                <td class="border-l border-slate-200 px-4 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['color'] === 'blue' ? 'text-blue-900' : 'text-red-800' }}">{{ $fmt($seccion['total']['total']) }}</td>
                            </tr>
                            @if (isset($seccion['subtotal']))
                                <tr class="bg-slate-100">
                                    <td class="sticky left-0 z-10 whitespace-nowrap bg-slate-100 px-5 py-2 text-sm font-bold text-slate-800">{{ $seccion['subtotal']['label'] }}</td>
                                    @foreach ($meses as $m)
                                        <td class="px-3 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['subtotal']['valores']['valores'][$m] < 0 ? 'text-red-700' : 'text-slate-800' }}">{{ $fmt($seccion['subtotal']['valores']['valores'][$m]) }}</td>
                                    @endforeach
                                    <td class="border-l border-slate-200 px-4 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['subtotal']['valores']['total'] < 0 ? 'text-red-700' : 'text-slate-800' }}">{{ $fmt($seccion['subtotal']['valores']['total']) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-[#0d2d5e]">
                            <td class="sticky left-0 z-10 whitespace-nowrap bg-[#0d2d5e] px-5 py-3 text-sm font-bold text-white">UTILIDAD (PÉRDIDA) NETA</td>
                            @foreach ($meses as $m)
                                <td class="px-3 py-3 text-right text-sm font-bold tabular-nums {{ $utilidadNeta['valores'][$m] < 0 ? 'text-red-300' : 'text-white' }}">{{ $fmt($utilidadNeta['valores'][$m]) }}</td>
                            @endforeach
                            <td class="border-l border-blue-900 px-4 py-3 text-right text-sm font-bold tabular-nums {{ $utilidadNeta['total'] < 0 ? 'text-red-300' : 'text-white' }}">{{ $fmt($utilidadNeta['total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                Generado desde los asientos contables posteados. Los meses sin movimiento se muestran con "—".
            </p>
        @endif
    </div>

    <style>
        @media print {
            aside, header { display: none !important; }
            @page { size: landscape; }
        }
    </style>
</x-app-layout>
