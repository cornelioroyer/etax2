<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Movimientos bancarios</h2>
            @can('bancos.gestionar')
                <a href="{{ route('admin.bco.movimientos.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
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
                {{-- Combobox buscable; nombre mapeado a "Banco — Cuenta" para no perder el banco. --}}
                <div>
                    <x-buscador-contacto name="cuenta_id" label="Cuenta"
                        :opciones="$cuentas->map(fn ($c) => (object) ['id' => $c->id, 'nombre' => trim(($c->banco?->nombre ? $c->banco->nombre.' — ' : '').$c->nombre)])"
                        :selected="$filtros['cuenta_id'] ?? ''" placeholder="Todas — buscar" empty-label="Todas" width="w-64" compact />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                    <select name="tipo" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        @foreach (\App\Models\BcoMovimiento::TIPOS as $k => $v)
                            <option value="{{ $k }}" @selected(($filtros['tipo'] ?? '') === $k)>{{ $v }}</option>
                        @endforeach
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
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="descripción / referencia…" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button>
                @if (array_filter($filtros))
                    <a href="{{ route('admin.bco.movimientos.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cuenta</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3 text-right">Débito</th>
                            <th class="px-4 py-3 text-right">Crédito</th>
                            <th class="px-4 py-3 text-center">Conciliado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($movimientos as $mov)
                            <tr>
                                <td class="px-4 py-3">{{ $mov->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $mov->cuenta?->nombre }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ \App\Models\BcoMovimiento::TIPOS[$mov->tipo_movimiento] ?? $mov->tipo_movimiento }}</td>
                                <td class="px-4 py-3">{{ Str::limit($mov->descripcion, 50) }}</td>
                                <td class="px-4 py-3 text-right {{ $mov->debito > 0 ? 'text-red-600 font-medium' : 'text-gray-300' }}">
                                    {{ $mov->debito > 0 ? 'B/. ' . number_format((float) $mov->debito, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right {{ $mov->credito > 0 ? 'text-green-600 font-medium' : 'text-gray-300' }}">
                                    {{ $mov->credito > 0 ? 'B/. ' . number_format((float) $mov->credito, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-400">{{ $mov->conciliado ? '✓' : '' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.bco.movimientos.show', $mov) }}" class="text-blue-600 hover:underline text-xs">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Sin movimientos.</td></tr>
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
