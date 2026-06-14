<x-app-layout>
    @php
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
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
            <div class="flex items-center gap-2">
                @if (! $sinDatos)
                    <form method="GET" class="flex items-center gap-2">
                        <select name="mes" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]" onchange="this.form.submit()">
                            @foreach ($periodos->where('anio', $anio)->sortBy('mes') as $p)
                                <option value="{{ $p->mes }}" @selected($p->mes == $mes)>{{ $meses[$p->mes] }}</option>
                            @endforeach
                        </select>
                        <select name="anio" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]" onchange="this.form.submit()">
                            @foreach ($periodos->pluck('anio')->unique() as $a)
                                <option value="{{ $a }}" @selected($a == $anio)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </form>

                    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
                       class="inline-flex items-center gap-2 rounded-md bg-[#d21034] px-3 py-2 text-sm font-semibold text-white hover:bg-[#b00d2b]">
                        PDF
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"
                       class="inline-flex items-center gap-2 rounded-md bg-[#1d6f42] px-3 py-2 text-sm font-semibold text-white hover:bg-[#175836]">
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
                {{ $companiaActiva->nombre ?? 'La compañía' }} aún no tiene períodos contables — el balance de comprobación se llenará al postear asientos en Contabilidad.
            </div>
        @else
            <div class="mx-auto max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">

                {{-- ░░ Encabezado del informe ░░ --}}
                <div class="border-b-2 border-[#0d2d5e] px-8 py-6 text-center">
                    <h2 class="text-xl font-extrabold uppercase tracking-wide text-[#0d2d5e]">{{ $compania->nombre ?? '' }}</h2>
                    @if (!empty($compania?->ruc))
                        <p class="text-xs text-slate-500">RUC {{ $compania->ruc }}{{ $compania->dv ? ' DV '.$compania->dv : '' }}</p>
                    @endif
                    <p class="mt-2 text-lg font-bold uppercase tracking-wider text-[#005293]">Balance de Comprobación</p>
                    <p class="text-sm font-medium text-slate-600">Período al {{ $corte->format('d/m/Y') }}</p>
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
                                    <th class="bg-[#0a2347] px-3 py-3 text-left">Cuenta</th>
                                    <th class="bg-[#0a2347] px-3 py-3 text-left">Descripción</th>
                                    <th class="bg-[#0d2d5e] px-3 py-3 text-right">Balance Inicial</th>
                                    <th class="bg-[#0d2d5e] px-3 py-3 text-right">Débito</th>
                                    <th class="bg-[#0d2d5e] px-3 py-3 text-right">Crédito</th>
                                    <th class="bg-[#005293] px-3 py-3 text-right">Corriente</th>
                                    <th class="bg-[#005293] px-3 py-3 text-right">Balance Final</th>
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
                                            <td class="px-3 py-1.5 text-right tabular-nums font-medium text-slate-800">{{ $fmt($fila['final']) }}</td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr><td colspan="7" class="px-4 py-4 text-slate-500">Sin movimientos en el período.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($filas->isNotEmpty())
                                <tfoot>
                                    <tr class="text-white">
                                        <td class="bg-[#0a2347] px-3 py-3 text-base font-bold uppercase tracking-wide" colspan="2">Totales</td>
                                        <td class="bg-[#0d2d5e] px-3 py-3 text-right text-sm font-bold tabular-nums">{{ $fmt($totales['inicial']) }}</td>
                                        <td class="bg-[#0d2d5e] px-3 py-3 text-right text-sm font-bold tabular-nums {{ $cuadra ? '' : 'text-red-300' }}">{{ $fmt($totales['debito']) }}</td>
                                        <td class="bg-[#0d2d5e] px-3 py-3 text-right text-sm font-bold tabular-nums {{ $cuadra ? '' : 'text-red-300' }}">{{ $fmt($totales['credito']) }}</td>
                                        <td class="bg-[#005293] px-3 py-3 text-right text-sm font-bold tabular-nums">{{ $fmt($totales['corriente']) }}</td>
                                        <td class="bg-[#005293] px-3 py-3 text-right text-sm font-bold tabular-nums">{{ $fmt($totales['final']) }}</td>
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

    <style>
        @media print {
            aside, header { display: none !important; }
        }
    </style>
</x-app-layout>
