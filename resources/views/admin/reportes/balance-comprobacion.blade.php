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

                    <h2 class="text-xl font-extrabold uppercase tracking-wider text-[#0d2d5e]">Balance de Comprobación</h2>
                    <p class="mt-1 text-lg font-bold text-[#005293]">{{ $compania->nombre ?? '' }}</p>
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
