<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transferencias entre cuentas</h2>
            @can('bancos.gestionar')
                <a href="{{ route('admin.bco.transferencias.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nueva transferencia
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Cuenta</label>
                    <select name="cuenta_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todas</option>
                        @foreach ($cuentas as $c)
                            <option value="{{ $c->id }}" @selected(($filtros['cuenta_id'] ?? '') == $c->id)>{{ $c->banco?->nombre }} — {{ $c->nombre }}</option>
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
                <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Origen</th>
                            <th class="px-4 py-3">Destino</th>
                            <th class="px-4 py-3">Referencia</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($transferencias as $t)
                            <tr>
                                <td class="px-4 py-3">{{ $t->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-xs">{{ $t->cuentaOrigen?->banco?->nombre }} — {{ $t->cuentaOrigen?->nombre }}</td>
                                <td class="px-4 py-3 text-xs">{{ $t->cuentaDestino?->banco?->nombre }} — {{ $t->cuentaDestino?->nombre }}</td>
                                <td class="px-4 py-3 text-gray-400 text-xs font-mono">{{ $t->referencia ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $t->monto, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $t->estado === 'APLICADA' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ucfirst(strtolower($t->estado)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.bco.transferencias.show', $t) }}" class="text-blue-600 hover:underline text-xs">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin transferencias.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($transferencias->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $transferencias->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
