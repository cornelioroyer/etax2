<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo anticipo a proveedor</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.cxp.anticipos.store') }}">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-buscador-contacto name="proveedor_id" label="Proveedor *" required
                                placeholder="— Selecciona el proveedor —"
                                :opciones="$proveedores" :selected="old('proveedor_id', $proveedorId)" />
                            <x-input-error :messages="$errors->get('proveedor_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="fecha" value="Fecha *" />
                            <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                          :value="old('fecha', now()->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="monto" value="Monto del anticipo *" />
                            <x-text-input id="monto" name="monto" type="number" step="0.01" min="0.01" class="mt-1 block w-full text-right" required
                                          :value="old('monto')" />
                            <x-input-error :messages="$errors->get('monto')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="cuenta_pago_id" value="Pagar desde (cuenta) *" />
                            <select id="cuenta_pago_id" name="cuenta_pago_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($cuentasPago as $cuenta)
                                    <option value="{{ $cuenta->id }}" @selected(old('cuenta_pago_id', $cuentaBancoId) == $cuenta->id)>{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('cuenta_pago_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="referencia" value="Referencia" />
                            <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                                          :value="old('referencia')" placeholder="Transferencia, cheque…" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="concepto" value="Concepto" />
                            <x-text-input id="concepto" name="concepto" type="text" class="mt-1 block w-full"
                                          :value="old('concepto')" placeholder="Anticipo por pedido…" />
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                            Registrar anticipo
                        </button>
                        <a href="{{ route('admin.cxp.anticipos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto">Asiento: débito a Anticipos a proveedores, crédito a la cuenta de pago. Queda disponible para aplicar a facturas futuras.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
