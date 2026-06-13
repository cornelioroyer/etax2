<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transferencia bancaria</h2>
            <a href="{{ route('admin.bco.transferencias.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800 mb-4">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-6">
                <div class="flex items-center justify-between">
                    <div class="text-3xl font-bold text-gray-900">B/. {{ number_format((float) $transferencia->monto, 2) }}</div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $transferencia->estado === 'APLICADA' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ ucfirst(strtolower($transferencia->estado)) }}
                    </span>
                </div>

                <div class="flex items-center gap-6 text-sm">
                    <div class="flex-1 bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-400 mb-1">Origen</p>
                        <p class="font-semibold">{{ $transferencia->cuentaOrigen?->banco?->nombre }}</p>
                        <p class="text-gray-600">{{ $transferencia->cuentaOrigen?->nombre }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $transferencia->cuentaOrigen?->numero_cuenta }}</p>
                    </div>
                    <div class="text-gray-400 text-2xl">→</div>
                    <div class="flex-1 bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-400 mb-1">Destino</p>
                        <p class="font-semibold">{{ $transferencia->cuentaDestino?->banco?->nombre }}</p>
                        <p class="text-gray-600">{{ $transferencia->cuentaDestino?->nombre }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $transferencia->cuentaDestino?->numero_cuenta }}</p>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div><dt class="text-gray-500">Fecha</dt><dd class="font-medium">{{ $transferencia->fecha->format('d/m/Y') }}</dd></div>
                    @if ($transferencia->referencia)
                        <div><dt class="text-gray-500">Referencia</dt><dd class="font-medium font-mono">{{ $transferencia->referencia }}</dd></div>
                    @endif
                    @if ($transferencia->asiento)
                        <div><dt class="text-gray-500">Asiento</dt><dd><a href="{{ route('admin.asientos.show', $transferencia->asiento) }}" class="text-blue-600 hover:underline text-xs">{{ $transferencia->asiento->numero }}</a></dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
