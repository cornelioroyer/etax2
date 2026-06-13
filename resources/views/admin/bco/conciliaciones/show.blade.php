<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Conciliación — {{ $conciliacion->cuentaBancaria?->nombre }}
                </h2>
                <p class="text-sm text-gray-500">Corte: {{ $conciliacion->fecha_corte->format('d/m/Y') }}</p>
            </div>
            <div class="flex gap-3">
                @can('bancos.gestionar')
                    @if (! $conciliacion->esCerrada())
                        <form method="POST" action="{{ route('admin.bco.conciliaciones.cerrar', $conciliacion) }}"
                            onsubmit="return confirm('¿Cerrar la conciliación? No se podrán agregar más movimientos.')">
                            @csrf
                            <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Cerrar conciliación</button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('admin.bco.conciliaciones.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Resumen --}}
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Saldo banco</p>
                    <p class="text-xl font-bold text-gray-900">B/. {{ number_format((float) $conciliacion->saldo_banco, 2) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Saldo libros</p>
                    <p class="text-xl font-bold text-gray-900">B/. {{ number_format((float) $conciliacion->saldo_libros, 2) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Diferencia</p>
                    <p class="text-xl font-bold {{ abs((float)$conciliacion->diferencia) < 0.01 ? 'text-green-600' : 'text-red-600' }}">
                        B/. {{ number_format((float) $conciliacion->diferencia, 2) }}
                    </p>
                    @if (abs((float)$conciliacion->diferencia) < 0.01)
                        <p class="text-xs text-green-600 mt-1">✓ Cuadrado</p>
                    @endif
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Estado</p>
                    @php $colores = ['ABIERTA' => 'bg-blue-100 text-blue-700', 'CERRADA' => 'bg-green-100 text-green-700', 'ANULADA' => 'bg-red-100 text-red-700']; @endphp
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $colores[$conciliacion->estado] ?? 'bg-gray-100' }}">
                        {{ ucfirst(strtolower($conciliacion->estado)) }}
                    </span>
                </div>
            </div>

            {{-- Movimientos para conciliar --}}
            @can('bancos.gestionar')
            @if (! $conciliacion->esCerrada())
            <form method="POST" action="{{ route('admin.bco.conciliaciones.marcar', $conciliacion) }}">
                @csrf
            @endif
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Movimientos hasta {{ $conciliacion->fecha_corte->format('d/m/Y') }}</h3>
                    @can('bancos.gestionar')
                    @if (! $conciliacion->esCerrada())
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Guardar marcas</button>
                    @endif
                    @endcan
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            @if (! $conciliacion->esCerrada())
                                <th class="px-4 py-3 w-8">✓</th>
                            @endif
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3">Referencia</th>
                            <th class="px-4 py-3 text-right">Débito</th>
                            <th class="px-4 py-3 text-right">Crédito</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($movimientosNoConciliados as $mov)
                            @php $marcado = in_array($mov->id, $conciliadosIds); @endphp
                            <tr class="{{ $marcado ? 'bg-green-50' : '' }}">
                                @if (! $conciliacion->esCerrada())
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="movimiento_ids[]" value="{{ $mov->id }}" {{ $marcado ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600">
                                    </td>
                                @endif
                                <td class="px-4 py-3">{{ $mov->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ Str::limit($mov->descripcion, 60) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $mov->referencia ?? '—' }}</td>
                                <td class="px-4 py-3 text-right {{ $mov->debito > 0 ? 'text-red-600 font-medium' : 'text-gray-300' }}">
                                    {{ $mov->debito > 0 ? 'B/. ' . number_format((float) $mov->debito, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right {{ $mov->credito > 0 ? 'text-green-600 font-medium' : 'text-gray-300' }}">
                                    {{ $mov->credito > 0 ? 'B/. ' . number_format((float) $mov->credito, 2) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $conciliacion->esCerrada() ? 5 : 6 }}" class="px-4 py-8 text-center text-gray-400">Sin movimientos pendientes de conciliar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('bancos.gestionar')
            @if (! $conciliacion->esCerrada())
            </form>
            @endif
            @endcan
        </div>
    </div>
</x-app-layout>
