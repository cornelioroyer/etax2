<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transferencia #{{ $transferencia->id }}</h2>
            <a href="{{ route('admin.inventario.transferencias.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-3 gap-4 text-sm mb-6">
                    <div><span class="text-gray-500">Fecha</span><p class="font-medium">{{ $transferencia->fecha->format('d/m/Y') }}</p></div>
                    <div><span class="text-gray-500">Origen</span><p class="font-medium">{{ $transferencia->almacenOrigen?->nombre }}</p></div>
                    <div><span class="text-gray-500">Destino</span><p class="font-medium">{{ $transferencia->almacenDestino?->nombre }}</p></div>
                </div>

                @php
                    $movSalida = $transferencia->movimientos->where('tipo_movimiento', 'SALIDA')->first();
                @endphp

                @if ($movSalida)
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3 text-right">Cantidad</th>
                                <th class="px-4 py-3 text-right">Costo unit.</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($movSalida->detalle as $det)
                                <tr>
                                    <td class="px-4 py-3">{{ $det->item?->codigo }} – {{ $det->item?->nombre }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float)$det->cantidad, 2) }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float)$det->costo_unitario, 4) }}</td>
                                    <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float)$det->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-gray-400">Sin detalle disponible.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
