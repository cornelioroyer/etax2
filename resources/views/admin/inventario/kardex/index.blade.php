<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kardex de inventario</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                {{-- Combobox buscable por código o nombre. Reusa el combobox genérico
                     Alpine de <x-buscador-contacto> (lee id/codigo/nombre); funciona sin
                     recompilar el bundle. El botón "Filtrar" mantiene el flujo de submit. --}}
                <div>
                    <x-buscador-contacto
                        name="item_id"
                        label="Producto"
                        :opciones="$items"
                        :selected="$itemId"
                        placeholder="Todos — código o nombre"
                        empty-label="Todos"
                        width="w-72"
                        compact
                    />
                </div>
                {{-- Combobox buscable por código o nombre (mismo componente genérico). --}}
                <div>
                    <x-buscador-contacto
                        name="almacen_id"
                        label="Almacén"
                        :opciones="$almacenes"
                        :selected="$almacenId"
                        placeholder="Todos — código o nombre"
                        empty-label="Todos"
                        width="w-56"
                        compact
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Desde</label>
                    <input type="text" name="desde" value="{{ $desde }}" class="js-date rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                    <input type="text" name="hasta" value="{{ $hasta }}" class="js-date rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <button type="submit" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Filtrar</button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-3">Fecha</th>
                            <th class="px-3 py-3">Producto</th>
                            <th class="px-3 py-3">Almacén</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Doc. origen</th>
                            <th class="px-3 py-3">Descripción</th>
                            <th class="px-3 py-3 text-right">Entrada Qty</th>
                            <th class="px-3 py-3 text-right">Costo entrada</th>
                            <th class="px-3 py-3 text-right">Salida Qty</th>
                            <th class="px-3 py-3 text-right">Costo salida</th>
                            <th class="px-3 py-3 text-right">Saldo Qty</th>
                            <th class="px-3 py-3 text-right">Saldo costo</th>
                            <th class="px-3 py-3 text-right">Costo prom.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($kardex as $k)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2">{{ $k->fecha->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-medium">{{ $k->item?->codigo }}</p>
                                    <p class="text-xs text-gray-400">{{ Str::limit($k->item?->nombre, 30) }}</p>
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $k->almacen?->nombre }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $k->tipo_movimiento }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-400">{{ $k->documento_origen ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $k->descripcion ?? '—' }}</td>
                                <td class="px-3 py-2 text-right {{ $k->entrada_cantidad > 0 ? 'text-green-700 font-medium' : 'text-gray-300' }}">
                                    {{ $k->entrada_cantidad > 0 ? number_format((float)$k->entrada_cantidad, 2) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right {{ $k->entrada_cantidad > 0 ? 'text-gray-600' : 'text-gray-300' }}">
                                    {{ $k->entrada_cantidad > 0 ? number_format((float)$k->costo_entrada, 4) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right {{ $k->salida_cantidad > 0 ? 'text-red-600 font-medium' : 'text-gray-300' }}">
                                    {{ $k->salida_cantidad > 0 ? number_format((float)$k->salida_cantidad, 2) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right {{ $k->salida_cantidad > 0 ? 'text-gray-600' : 'text-gray-300' }}">
                                    {{ $k->salida_cantidad > 0 ? number_format((float)$k->costo_salida, 4) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right font-bold">{{ number_format((float)$k->saldo_cantidad, 2) }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-gray-700">{{ number_format((float)$k->saldo_costo, 2) }}</td>
                                <td class="px-3 py-2 text-right text-gray-500">{{ number_format((float)$k->costo_promedio, 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-4 py-8 text-center text-gray-400">Sin movimientos en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $kardex->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
