<x-app-layout>
    @php
        $fmt = fn ($n) => number_format($n, 2);
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        $cuadra = abs($totalActivos - ($totalPasivos + $totalPatrimonio)) < 0.01;
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Encabezado y controles --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Balance de Situación</h1>
                <p class="text-sm text-slate-500">Estado de situación financiera generado desde los asientos posteados.</p>
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
                {{ $companiaActiva->nombre ?? 'La compañía' }} aún no tiene asientos posteados — el balance se llenará al postear asientos en Contabilidad.
            </div>
        @else
            {{-- Membrete --}}
            <div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm print:border-0 print:shadow-none">
                <h2 class="text-lg font-bold uppercase text-slate-900">{{ $companiaActiva->nombre ?? '' }}</h2>
                @if (!empty($companiaActiva?->ruc))
                    <p class="text-xs text-slate-500">RUC {{ $companiaActiva->ruc }}{{ $companiaActiva->dv ? ' DV '.$companiaActiva->dv : '' }}</p>
                @endif
                <p class="mt-1 text-sm font-semibold text-slate-700">Balance de Situación</p>
                <p class="text-sm text-slate-500">Al {{ $corte->day }} de {{ $meses[$corte->month] }} de {{ $corte->year }}</p>
                <p class="text-xs text-slate-400">Cifras en Balboas (B/.)</p>
            </div>

            @unless ($cuadra)
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 print:hidden">
                    Atención: el balance no cuadra — Activos {{ $fmt($totalActivos) }} ≠ Pasivos + Patrimonio {{ $fmt($totalPasivos + $totalPatrimonio) }}.
                </div>
            @endunless

            <div class="grid gap-6 md:grid-cols-2 print:grid-cols-2">
                {{-- ACTIVOS --}}
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">
                    <h3 class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm font-bold uppercase tracking-wide text-blue-800">Activos</h3>
                    <table class="min-w-full text-sm">
                        <tbody>
                            @forelse ($activos as $grupo)
                                <tr class="bg-slate-50/60">
                                    <td colspan="2" class="px-5 pt-3 pb-1 text-xs font-bold uppercase tracking-wide text-slate-600">{{ $grupo['grupo'] }}</td>
                                </tr>
                                @foreach ($grupo['cuentas'] as $cuenta)
                                    <tr>
                                        <td class="px-5 py-1 text-slate-700">
                                            <span class="mr-1 font-mono text-xs text-slate-400">{{ $cuenta['codigo'] }}</span>{{ $cuenta['nombre'] }}
                                        </td>
                                        <td class="px-5 py-1 text-right tabular-nums {{ $cuenta['saldo'] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $fmt($cuenta['saldo']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-b border-slate-100">
                                    <td class="px-5 py-1 text-xs font-semibold text-slate-500">Total {{ $grupo['grupo'] }}</td>
                                    <td class="border-t border-slate-300 px-5 py-1 text-right text-xs font-semibold tabular-nums text-slate-700">{{ $fmt($grupo['subtotal']) }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-5 py-4 text-slate-500">Sin saldos de activo.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-blue-50">
                                <td class="px-5 py-3 text-sm font-bold text-blue-900">TOTAL ACTIVOS</td>
                                <td class="px-5 py-3 text-right text-sm font-bold tabular-nums text-blue-900">{{ $fmt($totalActivos) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- PASIVOS Y PATRIMONIO --}}
                <div class="space-y-6">
                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">
                        <h3 class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm font-bold uppercase tracking-wide text-red-700">Pasivos</h3>
                        <table class="min-w-full text-sm">
                            <tbody>
                                @forelse ($pasivos as $grupo)
                                    <tr class="bg-slate-50/60">
                                        <td colspan="2" class="px-5 pt-3 pb-1 text-xs font-bold uppercase tracking-wide text-slate-600">{{ $grupo['grupo'] }}</td>
                                    </tr>
                                    @foreach ($grupo['cuentas'] as $cuenta)
                                        <tr>
                                            <td class="px-5 py-1 text-slate-700">
                                                <span class="mr-1 font-mono text-xs text-slate-400">{{ $cuenta['codigo'] }}</span>{{ $cuenta['nombre'] }}
                                            </td>
                                            <td class="px-5 py-1 text-right tabular-nums {{ $cuenta['saldo'] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $fmt($cuenta['saldo']) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="border-b border-slate-100">
                                        <td class="px-5 py-1 text-xs font-semibold text-slate-500">Total {{ $grupo['grupo'] }}</td>
                                        <td class="border-t border-slate-300 px-5 py-1 text-right text-xs font-semibold tabular-nums text-slate-700">{{ $fmt($grupo['subtotal']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="px-5 py-4 text-slate-500">Sin saldos de pasivo.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="bg-red-50">
                                    <td class="px-5 py-3 text-sm font-bold text-red-800">TOTAL PASIVOS</td>
                                    <td class="px-5 py-3 text-right text-sm font-bold tabular-nums text-red-800">{{ $fmt($totalPasivos) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">
                        <h3 class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-sm font-bold uppercase tracking-wide text-emerald-700">Patrimonio</h3>
                        <table class="min-w-full text-sm">
                            <tbody>
                                @foreach ($patrimonio as $grupo)
                                    <tr class="bg-slate-50/60">
                                        <td colspan="2" class="px-5 pt-3 pb-1 text-xs font-bold uppercase tracking-wide text-slate-600">{{ $grupo['grupo'] }}</td>
                                    </tr>
                                    @foreach ($grupo['cuentas'] as $cuenta)
                                        <tr>
                                            <td class="px-5 py-1 text-slate-700">
                                                @if ($cuenta['codigo'])<span class="mr-1 font-mono text-xs text-slate-400">{{ $cuenta['codigo'] }}</span>@endif{{ $cuenta['nombre'] }}
                                            </td>
                                            <td class="px-5 py-1 text-right tabular-nums {{ $cuenta['saldo'] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $fmt($cuenta['saldo']) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="border-b border-slate-100">
                                        <td class="px-5 py-1 text-xs font-semibold text-slate-500">Total {{ $grupo['grupo'] }}</td>
                                        <td class="border-t border-slate-300 px-5 py-1 text-right text-xs font-semibold tabular-nums text-slate-700">{{ $fmt($grupo['subtotal']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-emerald-50">
                                    <td class="px-5 py-3 text-sm font-bold text-emerald-800">TOTAL PATRIMONIO</td>
                                    <td class="px-5 py-3 text-right text-sm font-bold tabular-nums text-emerald-800">{{ $fmt($totalPatrimonio) }}</td>
                                </tr>
                                <tr class="bg-[#0d2d5e]">
                                    <td class="px-5 py-3 text-sm font-bold text-white">TOTAL PASIVOS + PATRIMONIO</td>
                                    <td class="px-5 py-3 text-right text-sm font-bold tabular-nums text-white">{{ $fmt($totalPasivos + $totalPatrimonio) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500">
                Generado desde los asientos contables posteados al corte. El patrimonio incluye los resultados acumulados y la utilidad del período (cuentas de resultado sin cerrar).
            </p>
        @endif
    </div>

    <style>
        @media print {
            aside, header { display: none !important; }
        }
    </style>
</x-app-layout>
