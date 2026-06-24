<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notas de crédito de ventas</h2>
            @can('ventas.gestionar')
                <a href="{{ route('admin.ventas.notas-credito.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nueva nota
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
                <x-buscador-contacto name="cliente_id" label="Cliente" compact width="w-56" :opciones="$clientes" :selected="$filtros['cliente_id'] ?? null" />
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estado</label>
                    <select name="estado" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        <option value="EMITIDA" @selected(($filtros['estado'] ?? '') === 'EMITIDA')>Emitida</option>
                        <option value="APLICADA" @selected(($filtros['estado'] ?? '') === 'APLICADA')>Aplicada</option>
                        <option value="ANULADA" @selected(($filtros['estado'] ?? '') === 'ANULADA')>Anulada</option>
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
                    <a href="{{ route('admin.ventas.notas-credito.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Número</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Motivo</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($notas as $n)
                            @php
                                $colores = ['EMITIDA' => 'bg-blue-100 text-blue-700', 'APLICADA' => 'bg-green-100 text-green-700', 'ANULADA' => 'bg-red-100 text-red-700', 'BORRADOR' => 'bg-gray-100 text-gray-600'];
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs font-medium">{{ $n->numero }}</td>
                                <td class="px-4 py-3">{{ $n->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $n->cliente?->nombre }}</td>
                                <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $n->motivo }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $n->total, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $colores[$n->estado] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst(strtolower($n->estado)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.ventas.notas-credito.show', $n) }}" class="text-blue-600 hover:underline text-xs">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No hay notas de crédito.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($notas->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $notas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
