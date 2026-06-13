<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.compras.gastos.index') }}" class="text-gray-500 hover:text-gray-700">← Gastos</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar gasto directo</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-6">
                    Registra un gasto pagado al contado (banco, caja, tarjeta). Se crea un asiento contable directo sin pasar por Cuentas por Pagar.
                    Para facturas a crédito usa <a href="{{ route('admin.cxp.facturas.index') }}" class="text-blue-700 underline">Cuentas por Pagar</a>.
                </p>

                <form method="POST" action="{{ route('admin.compras.gastos.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="fecha" value="Fecha *" />
                            <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full"
                                          :value="old('fecha', now()->format('d/m/Y'))" required />
                        </div>
                        <div>
                            <x-input-label for="monto" value="Monto (B/.) *" />
                            <x-text-input id="monto" name="monto" type="number" step="0.01" min="0.01"
                                          class="mt-1 block w-full" :value="old('monto')" required placeholder="0.00" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="descripcion" value="Descripción *" />
                        <x-text-input id="descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                                      :value="old('descripcion')" required maxlength="500"
                                      placeholder="Ej: Pago de servicio de internet, factura #123" />
                    </div>

                    <div>
                        <x-input-label for="cuenta_gasto_id" value="Cuenta de gasto *" />
                        <select id="cuenta_gasto_id" name="cuenta_gasto_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Seleccionar —</option>
                            @foreach ($cuentasGasto as $cc)
                                <option value="{{ $cc->id }}" @selected(old('cuenta_gasto_id', $cuentaGastoId) == $cc->id)>
                                    {{ $cc->codigo }} — {{ $cc->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="cuenta_pago_id" value="Pagado con (banco/caja) *" />
                        <select id="cuenta_pago_id" name="cuenta_pago_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Seleccionar —</option>
                            @foreach ($cuentasPago as $cc)
                                <option value="{{ $cc->id }}" @selected(old('cuenta_pago_id', $cuentaPagoId) == $cc->id)>
                                    {{ $cc->codigo }} — {{ $cc->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @if ($cuentasPago->isEmpty())
                            <p class="mt-1 text-xs text-amber-600">No hay cuentas de efectivo/banco (códigos 11xxx). Verifica el plan de cuentas.</p>
                        @endif
                    </div>

                    <div>
                        <x-input-label for="referencia" value="Referencia / N° documento" />
                        <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                                      :value="old('referencia')" maxlength="100"
                                      placeholder="Ej: Factura N° 456, Cheque N° 789" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <a href="{{ route('admin.compras.gastos.index') }}"
                           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                        <button type="submit"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                            Registrar gasto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
