<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gastos directos</h2>
            @can('compras.gestionar')
                <a href="{{ route('admin.compras.gastos.create') }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    + Registrar gasto
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="col-span-2">
                        <x-input-label for="q" value="Descripción" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$filtros['q'] ?? ''" placeholder="Buscar..." />
                    </div>
                    <div>
                        <x-input-label for="desde" value="Desde" />
                        <x-text-input id="desde" name="desde" type="text" class="js-date mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                    </div>
                    <div>
                        <x-input-label for="hasta" value="Hasta" />
                        <x-text-input id="hasta" name="hasta" type="text" class="js-date mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    </div>
                </div>
                <div class="mt-3 flex gap-3">
                    <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Filtrar</button>
                    <a href="{{ route('admin.compras.gastos.index') }}" class="text-sm text-gray-600 hover:text-gray-900 self-center">Limpiar</a>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Número</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($gastos as $gasto)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono font-medium">
                                    <a href="{{ route('admin.asientos.show', $gasto) }}" class="text-blue-700 hover:underline">{{ $gasto->numero }}</a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $gasto->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 max-w-xs truncate">{{ $gasto->descripcion }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium">
                                    B/. {{ number_format((float) $gasto->detalle->sum('debito'), 2) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($gasto->estado === 'POSTEADO')
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Posteado</span>
                                    @elseif ($gasto->estado === 'ANULADO')
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Anulado</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $gasto->estado }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                    No hay gastos registrados.
                                    @can('compras.gestionar')
                                        <a href="{{ route('admin.compras.gastos.create') }}" class="text-blue-700 underline">Registrar el primero</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($gastos->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $gastos->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
