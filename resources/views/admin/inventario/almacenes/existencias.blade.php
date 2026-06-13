<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Existencias — {{ $almacen->nombre }}</h2>
            <a href="{{ route('admin.inventario.almacenes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Almacenes</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Costo prom.</th>
                            <th class="px-4 py-3 text-right">Valor total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($existencias as $ex)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $ex->item?->codigo }}</td>
                                <td class="px-4 py-3 font-medium">{{ $ex->item?->nombre }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $ex->cantidad, 4), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">B/. {{ number_format((float) $ex->costo_promedio, 4) }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $ex->cantidad * (float) $ex->costo_promedio, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin existencias en este almacén.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($existencias->count())
                        <tfoot class="border-t-2 border-gray-200 font-semibold text-sm">
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right text-gray-700">Total inventario</td>
                                <td class="px-4 py-2 text-right">B/. {{ number_format($existencias->sum(fn($e) => (float)$e->cantidad * (float)$e->costo_promedio), 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
