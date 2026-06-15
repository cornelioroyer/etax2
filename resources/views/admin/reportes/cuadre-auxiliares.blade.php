<x-app-layout>
    @php
        $fmt = fn ($n) => number_format((float) $n, 2);
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Encabezado y controles --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Cuadre de Auxiliares</h1>
                <p class="text-sm text-slate-500">Compara el saldo de cada auxiliar contra su cuenta de control en el mayor (a hoy).</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.reportes.cuadre-auxiliares', ['export' => 'pdf']) }}"
                   class="inline-flex items-center gap-2 rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">
                    Exportar PDF
                </a>
                <a href="{{ route('admin.reportes.cuadre-auxiliares', ['export' => 'xlsx']) }}"
                   class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Exportar Excel
                </a>
            </div>
        </div>

        {{-- Membrete --}}
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-6 text-center shadow-sm print:border-0 print:shadow-none">
            <h2 class="text-lg font-bold uppercase text-slate-900">{{ $compania->nombre ?? '' }}</h2>
            @if (!empty($compania?->ruc))
                <p class="text-xs text-slate-500">RUC {{ $compania->ruc }}{{ $compania->dv ? ' DV '.$compania->dv : '' }}</p>
            @endif
            <p class="mt-1 text-sm font-semibold text-slate-700">Cuadre de Auxiliares ↔ Mayor</p>
            <p class="text-sm text-slate-500">Saldos a {{ $generado->format('d/m/Y') }}</p>
            <p class="text-xs text-slate-400">Cifras en Balboas (B/.)</p>
        </div>

        {{-- Resumen --}}
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 text-left">Auxiliar</th>
                        <th class="px-5 py-3 text-left">Cuenta de control</th>
                        <th class="w-40 px-5 py-3 text-right">Saldo auxiliar</th>
                        <th class="w-40 px-5 py-3 text-right">Saldo mayor</th>
                        <th class="w-40 px-5 py-3 text-right">Diferencia</th>
                        <th class="w-28 px-5 py-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($secciones as $s)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-slate-800">{{ $s['titulo'] }}</td>
                            <td class="px-5 py-3 text-slate-600">
                                @if ($s['sin_cuenta'])
                                    <span class="text-amber-600">No configurada</span>
                                @elseif ($s['varias_cuentas'])
                                    <span class="text-slate-500">Varias ({{ count($s['detalle']) }})</span>
                                @elseif ($s['cuenta'])
                                    <span class="font-mono text-xs text-slate-400">{{ $s['cuenta']->codigo }}</span> {{ $s['cuenta']->nombre }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-800">{{ $fmt($s['auxiliar']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-800">{{ $fmt($s['mayor']) }}</td>
                            <td class="px-5 py-3 text-right font-semibold tabular-nums {{ $s['cuadra'] ? 'text-slate-500' : 'text-red-600' }}">{{ $fmt($s['diferencia']) }}</td>
                            <td class="px-5 py-3 text-center">
                                @if ($s['sin_cuenta'])
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Sin cuenta</span>
                                @elseif ($s['cuadra'])
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Cuadra</span>
                                @else
                                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Descuadre</span>
                                @endif
                            </td>
                        </tr>
                        {{-- Desglose por cuenta (inventario con varias cuentas) --}}
                        @if (!empty($s['detalle']))
                            @foreach ($s['detalle'] as $d)
                                <tr class="bg-slate-50/60 text-xs">
                                    <td class="px-5 py-1.5"></td>
                                    <td class="px-5 py-1.5 pl-8 text-slate-500">
                                        <span class="font-mono text-slate-400">{{ $d['codigo'] }}</span> {{ $d['nombre'] }}
                                    </td>
                                    <td class="px-5 py-1.5 text-right tabular-nums text-slate-600">{{ $fmt($d['auxiliar']) }}</td>
                                    <td class="px-5 py-1.5 text-right tabular-nums text-slate-600">{{ $fmt($d['mayor']) }}</td>
                                    <td class="px-5 py-1.5 text-right tabular-nums {{ abs($d['diferencia']) < $tolerancia ? 'text-slate-400' : 'text-red-600' }}">{{ $fmt($d['diferencia']) }}</td>
                                    <td class="px-5 py-1.5"></td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
            <p class="mb-1 font-semibold text-slate-600">Cómo leer este reporte</p>
            <ul class="list-disc space-y-0.5 pl-5">
                <li><strong>Saldo auxiliar</strong>: suma de saldos de los documentos (CxC/CxP, excluye anulados y borradores) o el valor de las existencias (inventario = cantidad × costo promedio).</li>
                <li><strong>Saldo mayor</strong>: saldo acumulado de la cuenta de control sobre los asientos posteados.</li>
                <li>Una <strong>diferencia ≠ 0</strong> suele indicar un asiento manual posteado directo contra la cuenta de control, o un documento sin contabilizar. Revisa el Balance de Comprobación de esa cuenta.</li>
            </ul>
        </div>
    </div>

    <style>
        @media print {
            aside, header { display: none !important; }
        }
    </style>
</x-app-layout>
