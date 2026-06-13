<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Productos y Servicios</h2>
            @can('inventario.gestionar')
                <a href="{{ route('admin.items.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nuevo
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                    <select name="tipo" class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="PRODUCTO" @selected(($filtros['tipo'] ?? '') === 'PRODUCTO')>Producto</option>
                        <option value="SERVICIO" @selected(($filtros['tipo'] ?? '') === 'SERVICIO')>Servicio</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Categoría</label>
                    <select name="categoria_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                        <option value="">Todas</option>
                        @foreach ($categorias as $cat)
                            <option value="{{ $cat->id }}" @selected(($filtros['categoria_id'] ?? '') == $cat->id)>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estado</label>
                    <select name="activo" class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filtros['activo'] ?? '') === '1')>Activos</option>
                        <option value="0" @selected(($filtros['activo'] ?? '') === '0')>Inactivos</option>
                    </select>
                </div>
                <div class="flex-1 min-w-40">
                    <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Código o nombre…"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                </div>
                <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button>
                @if (array_filter($filtros))
                    <a href="{{ route('admin.items.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Código</th>
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Categoría</th>
                                <th class="px-4 py-3">Unidad</th>
                                <th class="px-4 py-3 text-right">Precio venta</th>
                                <th class="px-4 py-3">ITBMS</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr class="{{ $item->activo ? '' : 'bg-gray-50 opacity-60' }}">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $item->codigo }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $item->nombre }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->tipo === 'PRODUCTO' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                            {{ ucfirst(strtolower($item->tipo)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item->categoria?->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item->unidadMedida?->codigo ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float) $item->precio_venta, 2) }}</td>
                                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $item->impuesto?->nombre ?? 'Exento' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $item->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right flex justify-end gap-2">
                                        @can('inventario.gestionar')
                                            <a href="{{ route('admin.items.edit', $item) }}" class="text-blue-600 hover:underline text-xs">Editar</a>
                                            <form method="POST" action="{{ route('admin.items.toggle', $item) }}">
                                                @csrf
                                                <button class="text-xs {{ $item->activo ? 'text-red-500 hover:underline' : 'text-green-600 hover:underline' }}">
                                                    {{ $item->activo ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No hay productos/servicios.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $items->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
