<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Movimientos de inventario</h2>
            @can('inventario.gestionar')
                <a href="{{ route('admin.inventario.movimientos.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nuevo movimiento
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Almacén</label>
                    <select name="almacen_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        @foreach ($almacenes as $alm)
                            <option value="{{ $alm->id }}" @selected(($filtros['almacen_id'] ?? '') == $alm->id)>{{ $alm->codigo }} — {{ $alm->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                    <select name="tipo" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        <option value="ENTRADA" @selected(($filtros['tipo'] ?? '') === 'ENTRADA')>Entrada</option>
                        <option value="SALIDA" @selected(($filtros['tipo'] ?? '') === 'SALIDA')>Salida</option>
                        <option value="AJUSTE" @selected(($filtros['tipo'] ?? '') === 'AJUSTE')>Ajuste</option>
                        <option value="TRANSFERENCIA" @selected(($filtros['tipo'] ?? '') === 'TRANSFERENCIA')>Transferencia</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Desde</label>
                    <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                    <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button>
                @if (array_filter($filtros))
                    <a href="{{ route('admin.inventario.movimientos.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Almacén</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($movimientos as $mov)
                            @php
                                $colores = ['ENTRADA' => 'bg-green-100 text-green-700', 'SALIDA' => 'bg-red-100 text-red-700', 'AJUSTE' => 'bg-yellow-100 text-yellow-700', 'TRANSFERENCIA' => 'bg-blue-100 text-blue-700'];
                            @endphp
                            <tr>
                                <td class="px-4 py-3">{{ $mov->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $colores[$mov->tipo_movimiento] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst(strtolower($mov->tipo_movimiento)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $mov->almacen->codigo ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $mov->descripcion ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">{{ $mov->estado }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.inventario.movimientos.show', $mov) }}" class="text-blue-600 hover:underline text-xs">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No hay movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($movimientos->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $movimientos->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
