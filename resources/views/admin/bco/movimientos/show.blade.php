<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Movimiento bancario</h2>
            <a href="{{ route('admin.bco.movimientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            @can('bancos.gestionar')
                @php($esManual = ! $movimiento->conciliado && $movimiento->documento_origen !== \App\Services\BancoSync::ORIGEN)
                @if ($esManual)
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <form method="POST" action="{{ route('admin.bco.movimientos.editar', $movimiento) }}"
                              onsubmit="return confirm('Para editar este movimiento se anulará (retirándolo del ledger y anulando su asiento) y se reabrirá el formulario con sus datos para registrarlo de nuevo. ¿Continuar?');">
                            @csrf
                            <button class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">Editar</button>
                        </form>
                        <form method="POST" action="{{ route('admin.bco.movimientos.anular', $movimiento) }}"
                              onsubmit="return confirm('¿Anular este movimiento? Se retirará del ledger del banco y se anulará su asiento contable.');">
                            @csrf
                            <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Anular</button>
                        </form>
                    </div>
                @endif
            @endcan

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-gray-500">Cuenta</dt>
                        <dd class="font-medium">{{ $movimiento->cuenta?->banco?->nombre }}</dd>
                        <dd class="text-xs text-gray-400">{{ $movimiento->cuenta?->nombre }} ({{ $movimiento->cuenta?->numero_cuenta }})</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Fecha</dt>
                        <dd class="font-medium">{{ $movimiento->fecha->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tipo</dt>
                        <dd class="font-medium">{{ \App\Models\BcoMovimiento::TIPOS[$movimiento->tipo_movimiento] ?? $movimiento->tipo_movimiento }}</dd>
                    </div>
                    <div class="sm:col-span-3">
                        <dt class="text-gray-500">Descripción</dt>
                        <dd class="font-medium">{{ $movimiento->descripcion }}</dd>
                    </div>
                    @if ($movimiento->referencia)
                        <div>
                            <dt class="text-gray-500">Referencia</dt>
                            <dd class="font-medium font-mono text-sm">{{ $movimiento->referencia }}</dd>
                        </div>
                    @endif
                    @if ($movimiento->contacto)
                        <div>
                            <dt class="text-gray-500">Contacto</dt>
                            <dd class="font-medium">{{ $movimiento->contacto->nombre }}</dd>
                        </div>
                    @endif
                    @if ($movimiento->debito > 0)
                        <div>
                            <dt class="text-gray-500">Débito (salida)</dt>
                            <dd class="font-semibold text-red-600 text-base">B/. {{ number_format((float) $movimiento->debito, 2) }}</dd>
                        </div>
                    @endif
                    @if ($movimiento->credito > 0)
                        <div>
                            <dt class="text-gray-500">Crédito (entrada)</dt>
                            <dd class="font-semibold text-green-600 text-base">B/. {{ number_format((float) $movimiento->credito, 2) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500">Conciliado</dt>
                        <dd class="font-medium">{{ $movimiento->conciliado ? 'Sí' : 'No' }}</dd>
                    </div>
                    @if ($movimiento->asiento)
                        <div>
                            <dt class="text-gray-500">Asiento</dt>
                            <dd><a href="{{ route('admin.asientos.show', $movimiento->asiento) }}" class="text-blue-600 hover:underline text-xs">{{ $movimiento->asiento->numero }}</a></dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
