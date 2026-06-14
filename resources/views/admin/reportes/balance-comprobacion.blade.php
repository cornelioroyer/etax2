<x-app-layout>
    @php
        $fmt = fn ($n) => abs($n) < 0.01 ? '–' : number_format($n, 2);
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        // En la presentación de saldos, Débito = saldo deudor y Crédito = saldo acreedor.
        $cuadra = abs($totalDeudor - $totalAcreedor) < 0.01;
        $logo = ! empty($companiaActiva?->logo_url) ? $companiaActiva->logo_url : asset('images/logo-etax2.png');
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Controles --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Balance de Comprobación</h1>
                <p class="text-sm text-slate-500">Saldos de las cuentas, acumulados desde los asientos posteados.</p>
            </div>
            <div class="flex items-center gap-2">
                @if (! $sinDatos)
                    <form method="GET" class="flex items-center gap-2">
                        <select name="mes" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]" onchange="this.form.submit()">
                            @foreach ($periodos->where('anio', $anio) as $p)
                                <option value="{{ $p->mes }}" @selected($p->mes == $mes)>{{ $meses[$p->mes] }}</option>
                            @endforeach
                        </select>
                        <select name="anio" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]" onchange="this.form.submit()">
                            @foreach ($periodos->pluck('anio')->unique() as $a)
                                <option value="{{ $a }}" @selected($a == $anio)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0a2347]">
                    Exportar PDF
                </button>
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Imprimir
                </button>
            </div>
        </div>

        @if ($sinDatos)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $companiaActiva->nombre ?? 'La compañía' }} aún no tiene asientos posteados — el balance de comprobación se llenará al postear asientos en Contabilidad.
            </div>
        @else
            <div class="mx-auto max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">

                {{-- ░░ Encabezado con logo de la compañía ░░ --}}
                <div class="relative overflow-hidden bg-gradient-to-br from-[#0d2d5e] via-[#0e3268] to-[#0a2347] px-8 py-8 text-center">
                    {{-- esquinas decorativas --}}
                    <div class="pointer-events-none absolute -left-10 -top-10 h-28 w-28 rotate-45 bg-[#005293]/40"></div>
                    <div class="pointer-events-none absolute -right-12 -bottom-12 h-32 w-32 rotate-45 bg-[#005293]/30"></div>

                    <div class="relative mx-auto mb-4 flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-4 border-white/90 bg-white shadow-lg">
                        <img src="{{ $logo }}" alt="Logo" class="h-full w-full object-contain p-2" onerror="this.onerror=null;this.src='{{ asset('images/logo-etax2.png') }}'">
                    </div>

                    <h2 class="relative text-2xl font-extrabold uppercase tracking-wide text-white">Balance de Comprobación</h2>
                    <div class="relative mx-auto my-2 flex items-center justify-center gap-2">
                        <span class="h-px w-16 bg-white/40"></span>
                        <span class="h-2 w-2 rotate-45 bg-[#7fb2e5]"></span>
                        <span class="h-px w-16 bg-white/40"></span>
                    </div>
                    <p class="relative text-lg font-semibold text-white">{{ $companiaActiva->nombre ?? '' }}</p>
                    @if (!empty($companiaActiva?->ruc))
                        <p class="relative text-xs text-blue-200">RUC {{ $companiaActiva->ruc }}{{ $companiaActiva->dv ? ' DV '.$companiaActiva->dv : '' }}</p>
                    @endif
                    <p class="relative mt-1 text-sm font-medium text-blue-200">Al {{ $corte->day }} de {{ $meses[$corte->month] }} de {{ $corte->year }}</p>
                </div>

                <div class="p-6 sm:p-8">

                    {{-- ░░ Tarjetas resumen ░░ --}}
                    <div class="mb-6 grid gap-4 sm:grid-cols-2">
                        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#0d2d5e] text-white">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-6L12 15m0 0 4.5-4.5M12 15V3" /></svg>
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Débitos</p>
                                <p class="text-2xl font-bold tabular-nums text-[#0d2d5e]">{{ number_format($totalDeudor, 2) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#005293] text-white">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 7.5 12 3m0 0L7.5 7.5M12 3v13.5" /></svg>
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Créditos</p>
                                <p class="text-2xl font-bold tabular-nums text-[#005293]">{{ number_format($totalAcreedor, 2) }}</p>
                            </div>
                        </div>
                    </div>

                    @unless ($cuadra)
                        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-[#d21034] print:hidden">
                            Atención: el balance no cuadra — saldo Deudor {{ number_format($totalDeudor, 2) }} ≠ Acreedor {{ number_format($totalAcreedor, 2) }}.
                        </div>
                    @endunless

                    {{-- ░░ Tabla ░░ --}}
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs font-bold uppercase tracking-wide text-white">
                                    <th class="bg-[#0a2347] px-4 py-3 text-left">Código</th>
                                    <th class="bg-[#0a2347] px-4 py-3 text-left">Cuenta</th>
                                    <th class="bg-[#0d2d5e] px-4 py-3 text-right">
                                        <span class="inline-flex items-center justify-end gap-1.5">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg>
                                            Débito
                                        </span>
                                    </th>
                                    <th class="bg-[#005293] px-4 py-3 text-right">
                                        <span class="inline-flex items-center justify-end gap-1.5">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                                            Crédito
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($cuentas as $cuenta)
                                    <tr class="odd:bg-white even:bg-slate-50/60 hover:bg-blue-50/50">
                                        <td class="px-4 py-2 font-mono text-xs font-semibold text-[#0d2d5e]">{{ $cuenta['codigo'] }}</td>
                                        <td class="px-4 py-2 text-slate-700">{{ $cuenta['nombre'] }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums {{ $cuenta['deudor'] >= 0.01 ? 'font-medium text-slate-800' : 'text-slate-300' }}">{{ $fmt($cuenta['deudor']) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums {{ $cuenta['acreedor'] >= 0.01 ? 'font-medium text-slate-800' : 'text-slate-300' }}">{{ $fmt($cuenta['acreedor']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-4 text-slate-500">Sin movimientos en el período.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="text-white">
                                    <td class="bg-[#0a2347] px-4 py-3" colspan="2">
                                        <span class="inline-flex items-center gap-2 text-base font-bold uppercase tracking-wide">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m0-18 6.75 2.25M12 3 5.25 5.25M3 9l2.25-3.75M3 9a2.25 2.25 0 0 0 4.5 0M3 9l2.25 3.75M21 9l-2.25-3.75M21 9a2.25 2.25 0 0 1-4.5 0M21 9l-2.25 3.75M4.5 21h15" /></svg>
                                            Totales
                                        </span>
                                    </td>
                                    <td class="bg-[#0d2d5e] px-4 py-3 text-right text-base font-bold tabular-nums {{ $cuadra ? '' : 'text-red-300' }}">{{ number_format($totalDeudor, 2) }}</td>
                                    <td class="bg-[#005293] px-4 py-3 text-right text-base font-bold tabular-nums {{ $cuadra ? '' : 'text-red-300' }}">{{ number_format($totalAcreedor, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <p class="mt-4 text-center text-xs text-slate-400">
                        Cifras en Balboas (B/.) · Generado desde los asientos contables posteados al corte.
                        Por partida doble, el total de saldos Deudor y Acreedor debe ser igual.
                    </p>
                </div>
            </div>
        @endif
    </div>

    <style>
        @media print {
            aside, header { display: none !important; }
        }
    </style>
</x-app-layout>
