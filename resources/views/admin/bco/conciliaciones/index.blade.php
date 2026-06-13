<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Conciliaciones bancarias</h2>
            @can('bancos.gestionar')
                <a href="{{ route('admin.bco.conciliaciones.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nueva conciliación
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha corte</th>
                            <th class="px-4 py-3">Cuenta</th>
                            <th class="px-4 py-3 text-right">Saldo banco</th>
                            <th class="px-4 py-3 text-right">Saldo libros</th>
                            <th class="px-4 py-3 text-right">Diferencia</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($conciliaciones as $c)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $c->fecha_corte->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-xs">{{ $c->cuentaBancaria?->banco?->nombre }} — {{ $c->cuentaBancaria?->nombre }}</td>
                                <td class="px-4 py-3 text-right">B/. {{ number_format((float) $c->saldo_banco, 2) }}</td>
                                <td class="px-4 py-3 text-right">B/. {{ number_format((float) $c->saldo_libros, 2) }}</td>
                                <td class="px-4 py-3 text-right {{ abs((float)$c->diferencia) < 0.01 ? 'text-green-600 font-medium' : 'text-red-600 font-medium' }}">
                                    B/. {{ number_format((float) $c->diferencia, 2) }}
                                </td>
                                <td class="px-4 py-3">
                                    @php $colores = ['ABIERTA' => 'bg-blue-100 text-blue-700', 'CERRADA' => 'bg-green-100 text-green-700', 'ANULADA' => 'bg-red-100 text-red-700']; @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $colores[$c->estado] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst(strtolower($c->estado)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.bco.conciliaciones.show', $c) }}" class="text-blue-600 hover:underline text-xs">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin conciliaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($conciliaciones->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $conciliaciones->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
