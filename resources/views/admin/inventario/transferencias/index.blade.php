<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transferencias de inventario</h2>
            @can('inventario.gestionar')
                <a href="{{ route('admin.inventario.transferencias.create') }}"
                   class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nueva transferencia
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="flex gap-3 items-end">
                <div><label class="block text-xs text-gray-500 mb-1">Desde</label>
                    <input type="date" name="desde" value="{{ $desde }}" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                <div><label class="block text-xs text-gray-500 mb-1">Hasta</label>
                    <input type="date" name="hasta" value="{{ $hasta }}" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                <button type="submit" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Filtrar</button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Origen</th>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3">Destino</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($transferencias as $t)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $t->id }}</td>
                                <td class="px-4 py-3">{{ $t->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-medium">{{ $t->almacenOrigen?->nombre }}</td>
                                <td class="px-4 py-3 text-gray-400">→</td>
                                <td class="px-4 py-3 font-medium">{{ $t->almacenDestino?->nombre }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-medium {{ $t->estado === 'APLICADA' ? 'text-green-700' : 'text-red-600' }}">
                                        {{ $t->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.inventario.transferencias.show', $t) }}" class="text-xs text-blue-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin transferencias en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $transferencias->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
