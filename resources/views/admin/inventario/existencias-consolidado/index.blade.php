<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Existencias por almacén (consolidado)</h2>
            <a href="{{ route('admin.inventario.almacenes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Almacenes</a>
        </div>
    </x-slot>

    @php
        $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 4), '0'), '.');
        $money = fn ($n) => 'B/. '.number_format((float) $n, 2);
        $exportBase = array_filter([
            'almacen_id'    => $filtros['almacen_id'],
            'incluir_ceros' => $filtros['incluir_ceros'] ? 1 : null,
            'q'             => $filtros['q'] !== '' ? $filtros['q'] : null,
        ], fn ($v) => $v !== null);
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Filtros --}}
            <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                <form method="GET" action="{{ route('admin.inventario.existencias.consolidado') }}"
                      class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Almacén</label>
                        <select name="almacen_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                            <option value="">Todos</option>
                            @foreach ($almacenes as $alm)
                                <option value="{{ $alm->id }}" @selected($filtros['almacen_id'] === $alm->id)>
                                    {{ $alm->codigo }} — {{ $alm->nombre }}{{ $alm->activo ? '' : ' (inactivo)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
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
                    <a href="{{ route('admin.inventario.existencias.consolidado') }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Limpiar</a>

                    <div class="ml-auto flex gap-2">
                        <a href="{{ route('admin.inventario.existencias.consolidado', array_merge($exportBase, ['export' => 'xlsx'])) }}"
                           class="rounded-md border border-green-600 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-50">Excel</a>
                        <a href="{{ route('admin.inventario.existencias.consolidado', array_merge($exportBase, ['export' => 'pdf'])) }}"
                           class="rounded-md border border-red-600 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">PDF</a>
                    </div>
                </form>
            </div>

            {{-- Tabla --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Código</th>
                            <th class="px-4 py-3 text-left">Producto</th>
                            <th class="px-4 py-3 text-left">UM</th>
                            @foreach ($columnas as $col)
                                <th class="px-4 py-3 text-right whitespace-nowrap" title="{{ $col->nombre }}">{{ $col->codigo }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Costo prom.</th>
                            <th class="px-4 py-3 text-right">Valor total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($filas as $f)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600 whitespace-nowrap">{{ $f['codigo'] }}</td>
                                <td class="px-4 py-3 font-medium">{{ $f['nombre'] }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $f['um'] }}</td>
                                @foreach ($columnas as $col)
                                    @php $c = $f['porAlmacen'][$col->id] ?? null; @endphp
                                    <td class="px-4 py-3 text-right {{ $c !== null && $c < 0 ? 'text-red-600 font-medium' : '' }}">
                                        {{ $c === null ? '—' : $cant($c) }}
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-right font-semibold {{ $f['totalCantidad'] < 0 ? 'text-red-600' : '' }}">{{ $cant($f['totalCantidad']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ number_format((float) $f['costoProm'], 4) }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ $money($f['valor']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 6 + $columnas->count() }}" class="px-4 py-8 text-center text-gray-400">Sin existencias para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($filas))
                        <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right text-gray-700">Totales</td>
                                @foreach ($columnas as $col)
                                    <td class="px-4 py-2 text-right">{{ $cant($totalPorAlmacen[$col->id] ?? 0) }}</td>
                                @endforeach
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-right">{{ $money($totalValor) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="text-xs text-gray-400 px-1">
                {{ count($filas) }} ítem(s) — generado {{ $generado->format('d/m/Y H:i') }}. Cifras en B/.
                Las cantidades negativas (sobreventa) se muestran en rojo.
            </p>
        </div>
    </div>
</x-app-layout>
