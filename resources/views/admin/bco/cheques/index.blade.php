<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cheques emitidos</h2>
            @can('bancos.gestionar')
                <a href="{{ route('admin.bco.cheques.create') }}"
                   class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nuevo cheque
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="flex flex-wrap gap-3 items-end">
                {{-- Combobox buscable por nombre (componente genérico). --}}
                <div>
                    <x-buscador-contacto name="cuenta_id" label="Cuenta" :opciones="$cuentas"
                        :selected="$cuentaId" placeholder="Todas — buscar" empty-label="Todas"
                        width="w-56" compact />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estado</label>
                    <select name="estado" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        @foreach ($estados as $k => $v)
                            <option value="{{ $k }}" @selected($estado === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
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
                            <th class="px-4 py-3">No. Cheque</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cuenta</th>
                            <th class="px-4 py-3">Beneficiario</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php $colores = ['EMITIDO' => 'bg-blue-100 text-blue-700', 'COBRADO' => 'bg-green-100 text-green-700', 'ANULADO' => 'bg-red-100 text-red-700', 'CADUCADO' => 'bg-gray-100 text-gray-600']; @endphp
                        @forelse ($cheques as $ch)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono font-medium">{{ $ch->numero_cheque }}</td>
                                <td class="px-4 py-3">{{ $ch->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $ch->cuentaBancaria?->nombre }}</td>
                                <td class="px-4 py-3">{{ $ch->beneficiario?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-red-700">B/. {{ number_format((float) $ch->monto, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $colores[$ch->estado] ?? 'bg-gray-100' }}">
                                        {{ $estados[$ch->estado] ?? $ch->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.bco.cheques.show', $ch) }}" class="text-xs text-blue-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin cheques en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $cheques->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
