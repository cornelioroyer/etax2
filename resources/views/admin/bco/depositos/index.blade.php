<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Depósitos bancarios</h2>
            @can('bancos.gestionar')
                <a href="{{ route('admin.bco.depositos.create') }}"
                   class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nuevo depósito
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Filtros --}}
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                {{-- Combobox buscable por nombre (componente genérico). --}}
                <div>
                    <x-buscador-contacto name="cuenta_id" label="Cuenta" :opciones="$cuentas"
                        :selected="$cuentaId" placeholder="Todas — buscar" empty-label="Todas"
                        width="w-56" compact />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Desde</label>
                    <input type="date" name="desde" value="{{ $desde }}" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                    <input type="date" name="hasta" value="{{ $hasta }}" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <button type="submit" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Filtrar</button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cuenta</th>
                            <th class="px-4 py-3">Referencia</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3">Asiento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($depositos as $d)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">{{ $d->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $d->cuentaBancaria?->nombre }}</td>
                                <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $d->referencia ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-green-700">B/. {{ number_format((float) $d->monto, 2) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400">{{ $d->asiento_id ? "AS-{$d->asiento_id}" : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin depósitos en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $depositos->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
