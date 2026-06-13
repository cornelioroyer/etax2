<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Depósito #{{ $deposito->id }}</h2>
            <a href="{{ route('admin.bco.depositos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Cuenta</span><p class="font-medium">{{ $deposito->cuentaBancaria?->nombre }}</p></div>
                    <div><span class="text-gray-500">Fecha</span><p class="font-medium">{{ $deposito->fecha->format('d/m/Y') }}</p></div>
                    <div><span class="text-gray-500">Monto</span><p class="font-bold text-green-700 text-lg">B/. {{ number_format((float) $deposito->monto, 2) }}</p></div>
                    <div><span class="text-gray-500">Referencia</span><p class="font-mono">{{ $deposito->referencia ?? '—' }}</p></div>
                    @if ($deposito->asiento)
                        <div><span class="text-gray-500">Asiento</span><p class="font-mono">AS-{{ $deposito->asiento_id }}</p></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
