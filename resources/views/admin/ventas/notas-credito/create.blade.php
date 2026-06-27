<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva nota de crédito de venta</h2>
            <a href="{{ route('admin.ventas.notas-credito.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.ventas.notas-credito.store') }}">
                @csrf
                <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-buscador-contacto name="cliente_id" label="Cliente *" submit-on-select
                                placeholder="Seleccionar cliente…"
                                :opciones="$clientes" :selected="old('cliente_id', $clienteId)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha <span class="text-red-500">*</span></label>
                            <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Motivo / Descripción <span class="text-red-500">*</span></label>
                        <textarea name="motivo" rows="2" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">{{ old('motivo') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Monto total (B/.) <span class="text-red-500">*</span></label>
                            <input type="number" name="total" value="{{ old('total') }}" step="0.01" min="0.01"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Factura a aplicar (opcional)</label>
                            <select name="factura_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                <option value="">— No aplicar a factura específica —</option>
                                @foreach ($facturas as $f)
                                    <option value="{{ $f->id }}" @selected(old('factura_id') == $f->id)>
                                        {{ $f->numero }} — B/. {{ number_format((float) $f->saldo, 2) }} saldo
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <x-buscador-contacto name="cuenta_id" label="Cuenta de ventas (contrapartida) *" required
                            :opciones="$cuentasVenta" :selected="old('cuenta_id', $cuentaVentaId)"
                            placeholder="Buscar cuenta por código o nombre" />
                    </div>
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800">Emitir nota de crédito</button>
                    <a href="{{ route('admin.ventas.notas-credito.index') }}" class="rounded-md border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
