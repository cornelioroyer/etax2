<x-app-layout>
    @php $fmt = fn($n) => number_format($n, 2); @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Liquidación de ITBMS</h1>
                <p class="text-sm text-slate-500">ITBMS cobrado en ventas menos ITBMS crédito en compras. Panamá.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-center gap-2">
                    <select name="anio" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                        @foreach ($anios as $a)
                            <option value="{{ $a }}" @selected($a == $anio)>{{ $a }}</option>
                        @endforeach
                    </select>
                </form>
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Imprimir</button>
            </div>
        </div>

        {{-- Membrete --}}
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 text-center shadow-sm print:border-0 print:shadow-none">
            <h2 class="text-lg font-bold uppercase text-slate-900">{{ $companiaActiva->nombre ?? '' }}</h2>
            @if (!empty($companiaActiva?->ruc))
                <p class="text-xs text-slate-500">RUC {{ $companiaActiva->ruc }}{{ $companiaActiva->dv ? ' DV '.$companiaActiva->dv : '' }}</p>
            @endif
            <p class="mt-1 text-sm font-semibold text-slate-700">Liquidación de ITBMS — Año {{ $anio }}</p>
            <p class="text-xs text-slate-400">Cifras en Balboas (B/.)</p>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3 text-left">Mes</th>
                            <th class="px-4 py-3 text-right">Ventas gravadas</th>
                            <th class="px-4 py-3 text-right bg-blue-50/50">ITBMS cobrado</th>
                            <th class="px-4 py-3 text-right">Compras gravadas</th>
                            <th class="px-4 py-3 text-right bg-green-50/50">ITBMS crédito</th>
                            <th class="px-4 py-3 text-right font-bold">ITBMS a pagar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($periodos as $p)
                            <tr class="{{ $p['tiene_datos'] ? 'hover:bg-slate-50' : 'text-slate-400' }}">
                                <td class="px-4 py-3 font-medium {{ $p['tiene_datos'] ? 'text-slate-800' : '' }}">{{ $p['nombre'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $p['ventas_base'] > 0 ? $fmt($p['ventas_base']) : '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums bg-blue-50/30 font-medium {{ $p['itbms_cobrado'] > 0 ? 'text-blue-800' : '' }}">
                                    {{ $p['itbms_cobrado'] != 0 ? $fmt($p['itbms_cobrado']) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $p['compras_base'] > 0 ? $fmt($p['compras_base']) : '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums bg-green-50/30 font-medium {{ $p['itbms_credito'] > 0 ? 'text-green-800' : '' }}">
                                    {{ $p['itbms_credito'] != 0 ? $fmt($p['itbms_credito']) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold {{ $p['neto'] > 0 ? 'text-red-700' : ($p['neto'] < 0 ? 'text-green-700' : 'text-slate-400') }}">
                                    @if ($p['neto'] != 0)
                                        {{ $fmt(abs($p['neto'])) }}
                                        @if ($p['neto'] < 0) <span class="text-xs font-normal">(favor)</span> @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 text-sm font-bold">
                            <td class="px-4 py-3 text-slate-700">TOTAL {{ $anio }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $fmt($totales['ventas_base']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-blue-900">{{ $fmt($totales['itbms_cobrado']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $fmt($totales['compras_base']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-green-900">{{ $fmt($totales['itbms_credito']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $totales['neto'] > 0 ? 'text-red-700' : 'text-green-700' }}">
                                {{ $fmt(abs($totales['neto'])) }}
                                @if ($totales['neto'] < 0) <span class="text-xs">(favor)</span> @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-slate-500 print:hidden">
            Incluye facturas y notas de débito vigentes; excluye documentos anulados y notas de crédito (que reducen la base).
            Para declaración oficial use el formulario 430 de la DGI.
        </p>
    </div>

    <style>
        @media print { aside, header { display: none !important; } }
    </style>
</x-app-layout>
