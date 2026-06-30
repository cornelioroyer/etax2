<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Existencia por cuenta de mayor</h2>
            <a href="{{ route('admin.inventario.existencias.consolidado') }}" class="text-sm text-gray-600 hover:text-gray-900">Existencias por almacén →</a>
        </div>
    </x-slot>

    @php
        $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 4), '0'), '.');
        $money = fn ($n) => 'B/. '.number_format((float) $n, 2);
        $exportBase = array_filter([
            'incluir_ceros' => $filtros['incluir_ceros'] ? 1 : null,
            'q'             => $filtros['q'] !== '' ? $filtros['q'] : null,
        ], fn ($v) => $v !== null);
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Filtros --}}
            <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                <form method="GET" action="{{ route('admin.reportes.existencias-por-cuenta') }}"
                      class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs text-gray-500 mb-1">Buscar producto</label>
                        <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Código o nombre"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 mb-2">
                        <input type="checkbox" name="incluir_ceros" value="1" @checked($filtros['incluir_ceros'])
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Incluir ítems en cero
                    </label>
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        Filtrar
                    </button>
                    <a href="{{ route('admin.reportes.existencias-por-cuenta') }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Limpiar</a>

                    <div class="ml-auto flex gap-2">
                        <a href="{{ route('admin.reportes.existencias-por-cuenta', array_merge($exportBase, ['export' => 'xlsx'])) }}"
                           class="rounded-md border border-green-600 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-50">Excel</a>
                        <a href="{{ route('admin.reportes.existencias-por-cuenta', array_merge($exportBase, ['export' => 'pdf'])) }}"
                           class="rounded-md border border-red-600 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">PDF</a>
                    </div>
                </form>
            </div>

            {{-- Tabla agrupada por cuenta de mayor --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Artículo</th>
                            <th class="px-4 py-3 text-left">Descripción</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Costo unitario</th>
                            <th class="px-4 py-3 text-right">Costo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    @forelse ($grupos as $g)
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-blue-50/60">
                                <td colspan="6" class="px-4 py-2 font-semibold text-[#0d2d5e]">
                                    @if ($g['cuenta_id'])
                                        <span class="font-mono text-xs text-gray-500">{{ $g['cuenta_codigo'] }}</span>
                                        — {{ $g['cuenta_nombre'] }}
                                    @else
                                        {{ $g['cuenta_nombre'] }}
                                    @endif
                                </td>
                            </tr>
                            @foreach ($g['lineas'] as $l)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 whitespace-nowrap">{{ $l['codigo'] }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $l['descripcion'] }}</td>
                                    <td class="px-4 py-3 text-right {{ $l['cantidad'] < 0 ? 'text-red-600 font-medium' : '' }}">{{ $cant($l['cantidad']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format((float) $l['costo_unitario'], 4) }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ $l['costo'] < 0 ? 'text-red-600' : '' }}">{{ $money($l['costo']) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.inventario.kardex.index', ['item_id' => $l['item_id']]) }}"
                                           class="text-sm text-indigo-600 hover:text-indigo-800 whitespace-nowrap">Movimientos →</a>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 font-semibold border-t border-gray-200">
                                <td colspan="2" class="px-4 py-2 text-right text-gray-700">Subtotal {{ $g['cuenta_codigo'] ?: '' }}</td>
                                <td class="px-4 py-2 text-right {{ $g['totalCantidad'] < 0 ? 'text-red-600' : '' }}">{{ $cant($g['totalCantidad']) }}</td>
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-right">{{ $money($g['totalCosto']) }}</td>
                                <td class="px-4 py-2"></td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Sin existencias para los filtros seleccionados.</td></tr>
                        </tbody>
                    @endforelse
                    @if (count($grupos))
                        <tfoot class="border-t-2 border-gray-300 bg-[#0d2d5e] text-white font-semibold">
                            <tr>
                                <td colspan="2" class="px-4 py-2 text-right">Total general</td>
                                <td class="px-4 py-2 text-right">{{ $cant($totalCantidad) }}</td>
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-right">{{ $money($totalCosto) }}</td>
                                <td class="px-4 py-2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-xs text-gray-400 px-1">
                {{ count($grupos) }} cuenta(s) — generado {{ $generado->format('d/m/Y H:i') }}. Cifras en B/.
                Costo unitario = costo promedio ponderado. Las cantidades negativas (sobreventa) se muestran en rojo.
            </p>
        </div>
    </div>
</x-app-layout>
