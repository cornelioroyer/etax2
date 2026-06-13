<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $cuenta->nombre }}</h2>
                <p class="text-sm text-gray-500">{{ $cuenta->banco?->nombre }} · {{ $cuenta->numero_cuenta }}</p>
            </div>
            <div class="flex gap-3">
                @can('bancos.gestionar')
                    <a href="{{ route('admin.bco.movimientos.create', ['cuenta_id' => $cuenta->id]) }}"
                        class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">+ Movimiento</a>
                @endcan
                <a href="{{ route('admin.bco.cuentas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Cuentas</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Resumen --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500">Saldo inicial</p>
                    <p class="text-xl font-bold text-gray-900">B/. {{ number_format((float) $cuenta->saldo_inicial, 2) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500">Saldo actual</p>
                    <p class="text-2xl font-bold text-blue-700">B/. {{ number_format($cuenta->saldo_actual, 2) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <p class="text-xs text-gray-500">Cuenta contable</p>
                    <p class="text-sm font-medium text-gray-900">{{ $cuenta->cuentaContable?->codigo }}</p>
                    <p class="text-xs text-gray-500">{{ $cuenta->cuentaContable?->nombre ?? '— sin mapeo —' }}</p>
                </div>
            </div>

            {{-- Movimientos --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Movimientos</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3">Referencia</th>
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
                                <td class="px-4 py-3 text-gray-500">{{ \App\Models\BcoMovimiento::TIPOS[$mov->tipo_movimiento] ?? $mov->tipo_movimiento }}</td>
                                <td class="px-4 py-3">{{ $mov->descripcion }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400">{{ $mov->referencia }}</td>
                                <td class="px-4 py-3 text-right {{ $mov->debito > 0 ? 'text-red-600 font-medium' : 'text-gray-300' }}">
                                    {{ $mov->debito > 0 ? 'B/. ' . number_format((float) $mov->debito, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right {{ $mov->credito > 0 ? 'text-green-600 font-medium' : 'text-gray-300' }}">
                                    {{ $mov->credito > 0 ? 'B/. ' . number_format((float) $mov->credito, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    {{ $mov->conciliado ? '✓' : '' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.bco.movimientos.show', $mov) }}" class="text-blue-600 hover:underline text-xs">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Sin movimientos registrados.</td></tr>
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
