<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cheque #{{ $cheque->numero_cheque }}</h2>
            <a href="{{ route('admin.bco.cheques.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @php $colores = ['EMITIDO' => 'bg-blue-100 text-blue-700', 'COBRADO' => 'bg-green-100 text-green-700', 'ANULADO' => 'bg-red-100 text-red-700', 'CADUCADO' => 'bg-gray-100 text-gray-600']; @endphp
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">No. Cheque</span><p class="font-mono font-bold text-lg">{{ $cheque->numero_cheque }}</p></div>
                    <div><span class="text-gray-500">Estado</span>
                        <p><span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $colores[$cheque->estado] ?? 'bg-gray-100' }}">
                            {{ \App\Models\BcoCheque::ESTADOS[$cheque->estado] ?? $cheque->estado }}
                        </span></p>
                    </div>
                    <div><span class="text-gray-500">Cuenta</span><p class="font-medium">{{ $cheque->cuentaBancaria?->nombre }}</p></div>
                    <div><span class="text-gray-500">Fecha</span><p class="font-medium">{{ $cheque->fecha->format('d/m/Y') }}</p></div>
                    <div><span class="text-gray-500">Beneficiario</span><p>{{ $cheque->beneficiario?->nombre ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Monto</span><p class="font-bold text-red-700 text-lg">B/. {{ number_format((float) $cheque->monto, 2) }}</p></div>
                    <div><span class="text-gray-500">Asiento</span>
                        <p class="font-medium">
                            @if ($cheque->asiento)
                                <a href="{{ route('admin.asientos.show', $cheque->asiento) }}" class="text-blue-700 hover:underline">{{ $cheque->asiento->numero }}</a>
                            @else
                                <span class="text-gray-400">Sin asiento</span>
                            @endif
                        </p>
                    </div>
                </div>

                @can('bancos.gestionar')
                    @if ($cheque->estado === 'EMITIDO')
                        <div class="border-t pt-4">
                            <p class="text-xs text-gray-500 mb-3">Cambiar estado:</p>
                            <div class="flex gap-2 flex-wrap">
                                @foreach (['COBRADO' => 'bg-green-600 hover:bg-green-700', 'ANULADO' => 'bg-red-600 hover:bg-red-700', 'CADUCADO' => 'bg-gray-500 hover:bg-gray-600'] as $est => $cls)
                                    <form method="POST" action="{{ route('admin.bco.cheques.estado', $cheque) }}"
                                          onsubmit="return confirm('¿Marcar cheque como {{ \App\Models\BcoCheque::ESTADOS[$est] }}?')">
                                        @csrf
                                        <input type="hidden" name="estado" value="{{ $est }}">
                                        <button type="submit" class="rounded-md px-3 py-1.5 text-xs font-semibold text-white {{ $cls }}">
                                            {{ \App\Models\BcoCheque::ESTADOS[$est] }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
