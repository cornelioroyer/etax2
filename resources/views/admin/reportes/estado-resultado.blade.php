<x-app-layout>
    @php
        $fmt = fn ($n) => number_format($n, 2);
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        $pctYtd = fn ($n) => ($ingresos['ytd'] ?? 0) != 0.0 ? number_format($n / $ingresos['ytd'] * 100, 1) . '%' : '—';
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Encabezado y controles --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Estado de Resultado</h1>
                <p class="text-sm text-slate-500">Resultados del período generados desde los asientos posteados.</p>
            </div>
            <div class="flex items-center gap-2">
                @if (! $sinDatos)
                    <form method="GET" class="flex items-center gap-2">
                        <select name="mes" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                            @foreach ($periodos->where('anio', $anio) as $p)
                                <option value="{{ $p->mes }}" @selected($p->mes == $mes)>{{ $meses[$p->mes] }}</option>
                            @endforeach
                        </select>
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
                {{ $companiaActiva->nombre ?? 'La compañía' }} aún no tiene asientos posteados — el estado de resultado se llenará al postear asientos en Contabilidad.
            </div>
        @else
            {{-- Membrete --}}
            <div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm print:border-0 print:shadow-none">
                <h2 class="text-lg font-bold uppercase text-slate-900">{{ $companiaActiva->nombre ?? '' }}</h2>
                @if (!empty($companiaActiva?->ruc))
                    <p class="text-xs text-slate-500">RUC {{ $companiaActiva->ruc }}{{ $companiaActiva->dv ? ' DV '.$companiaActiva->dv : '' }}</p>
                @endif
                <p class="mt-1 text-sm font-semibold text-slate-700">Estado de Resultado</p>
                <p class="text-sm text-slate-500">Del 1 de Enero al {{ $corte->day }} de {{ $meses[$corte->month] }} de {{ $corte->year }}</p>
                <p class="text-xs text-slate-400">Cifras en Balboas (B/.)</p>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Concepto</th>
                            <th class="w-36 px-5 py-3 text-right">{{ $meses[$mes] }}</th>
                            <th class="w-36 px-5 py-3 text-right">Acumulado {{ $anio }}</th>
                            <th class="w-24 px-5 py-3 text-right print:hidden">% Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td colspan="4" class="px-5 pt-4 pb-1 text-sm font-bold uppercase tracking-wide {{ $seccion['color'] === 'blue' ? 'text-blue-800' : 'text-red-700' }}">
                                    {{ $seccion['titulo'] }}
                                </td>
                            </tr>
                            @forelse ($seccion['grupos'] as $grupo)
                                @if ($seccion['grupos']->count() > 1)
                                    <tr class="bg-slate-50/60">
                                        <td colspan="4" class="px-5 pt-2 pb-1 text-xs font-bold uppercase tracking-wide text-slate-600">{{ $grupo['grupo'] }}</td>
                                    </tr>
                                @endif
                                @foreach ($grupo['cuentas'] as $cuenta)
                                    <tr>
                                        <td class="px-5 py-1 pl-8 text-slate-700">
                                            <span class="mr-1 font-mono text-xs text-slate-400">{{ $cuenta['codigo'] }}</span>{{ $cuenta['nombre'] }}
                                        </td>
                                        <td class="px-5 py-1 text-right tabular-nums {{ $cuenta['mes'] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $fmt($cuenta['mes']) }}</td>
                                        <td class="px-5 py-1 text-right tabular-nums {{ $cuenta['ytd'] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $fmt($cuenta['ytd']) }}</td>
                                        <td class="px-5 py-1 text-right text-xs tabular-nums text-slate-500 print:hidden">{{ $pctYtd($cuenta['ytd']) }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="4" class="px-5 py-2 pl-8 text-slate-500">Sin movimientos.</td></tr>
                            @endforelse
                            <tr class="border-t border-slate-200 {{ $seccion['color'] === 'blue' ? 'bg-blue-50' : 'bg-red-50' }}">
                                <td class="px-5 py-2 text-sm font-bold {{ $seccion['color'] === 'blue' ? 'text-blue-900' : 'text-red-800' }}">TOTAL {{ strtoupper($seccion['titulo']) }}</td>
                                <td class="px-5 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['color'] === 'blue' ? 'text-blue-900' : 'text-red-800' }}">{{ $fmt($seccion['total']['mes']) }}</td>
                                <td class="px-5 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['color'] === 'blue' ? 'text-blue-900' : 'text-red-800' }}">{{ $fmt($seccion['total']['ytd']) }}</td>
                                <td class="px-5 py-2 text-right text-xs font-semibold tabular-nums text-slate-500 print:hidden">{{ $pctYtd($seccion['total']['ytd']) }}</td>
                            </tr>
                            @if (isset($seccion['subtotal']))
                                <tr class="bg-slate-100">
                                    <td class="px-5 py-2 text-sm font-bold text-slate-800">{{ $seccion['subtotal']['label'] }}</td>
                                    <td class="px-5 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['subtotal']['valores']['mes'] < 0 ? 'text-red-700' : 'text-slate-800' }}">{{ $fmt($seccion['subtotal']['valores']['mes']) }}</td>
                                    <td class="px-5 py-2 text-right text-sm font-bold tabular-nums {{ $seccion['subtotal']['valores']['ytd'] < 0 ? 'text-red-700' : 'text-slate-800' }}">{{ $fmt($seccion['subtotal']['valores']['ytd']) }}</td>
                                    <td class="px-5 py-2 text-right text-xs font-semibold tabular-nums text-slate-500 print:hidden">{{ $pctYtd($seccion['subtotal']['valores']['ytd']) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-[#0d2d5e]">
                            <td class="px-5 py-3 text-sm font-bold text-white">UTILIDAD (PÉRDIDA) NETA</td>
                            <td class="px-5 py-3 text-right text-sm font-bold tabular-nums {{ $utilidadNeta['mes'] < 0 ? 'text-red-300' : 'text-white' }}">{{ $fmt($utilidadNeta['mes']) }}</td>
                            <td class="px-5 py-3 text-right text-sm font-bold tabular-nums {{ $utilidadNeta['ytd'] < 0 ? 'text-red-300' : 'text-white' }}">{{ $fmt($utilidadNeta['ytd']) }}</td>
                            <td class="px-5 py-3 text-right text-xs font-bold tabular-nums text-blue-200 print:hidden">{{ $pctYtd($utilidadNeta['ytd']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                Generado desde los asientos contables posteados. La columna "% Ingresos" es sobre el acumulado del año y no se imprime.
            </p>
        @endif
    </div>

    <style>
        @media print {
            aside, header { display: none !important; }
        }
    </style>
</x-app-layout>
