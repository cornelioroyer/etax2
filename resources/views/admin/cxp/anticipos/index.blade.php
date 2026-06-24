<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Anticipos a proveedores</h2>
            @can('cxp.gestionar')
                <a href="{{ route('admin.cxp.anticipos.create') }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    + Nuevo anticipo
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <x-buscador-contacto name="proveedor_id" label="Proveedor" :opciones="$proveedores" :selected="$filtros['proveedor_id'] ?? null" />
                    <div>
                        <x-input-label for="desde" value="Desde" />
                        <x-text-input id="desde" name="desde" type="date" class="mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                    </div>
                    <div>
                        <x-input-label for="hasta" value="Hasta" />
                        <x-text-input id="hasta" name="hasta" type="date" class="mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-600">Filtrar</button>
                        <a href="{{ route('admin.cxp.anticipos.index') }}" class="px-2 py-2 text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                    </div>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Número</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Proveedor</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3 text-right">Disponible</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($anticipos as $anticipo)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('admin.cxp.anticipos.show', $anticipo) }}" class="text-blue-700 hover:underline">{{ $anticipo->numero }}</a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $anticipo->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 max-w-xs truncate">{{ $anticipo->proveedor->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $anticipo->total, 2) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-medium">B/. {{ number_format((float) $anticipo->saldo, 2) }}</td>
                                <td class="px-4 py-3">@include('admin.cxc._estado', ['estado' => $anticipo->estado])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No hay anticipos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $anticipos->links() }}
        </div>
    </div>
</x-app-layout>
