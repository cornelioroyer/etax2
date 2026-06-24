<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar pago a proveedor</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Paso 1: elegir proveedor (recarga con sus facturas pendientes) --}}
            <form method="GET" action="{{ route('admin.cxp.pagos.create') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
                        <x-buscador-contacto name="proveedor_id" label="Proveedor *" submit-on-select
                            placeholder="— Selecciona el proveedor —"
                            :opciones="$proveedores" :selected="$proveedorId" />
                    </div>
                    <p class="pb-2 text-xs text-gray-500">Al elegir el proveedor se cargan sus facturas con saldo.</p>
                </div>
            </form>

            @if ($proveedorId)
                @if ($facturas->isEmpty())
                    <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                        El proveedor no tiene facturas con saldo pendiente.
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.cxp.pagos.store') }}"
                          x-data="pagoCxp({{ $facturas->map(fn ($f) => ['id' => $f->id, 'numero' => $f->numero, 'fecha' => $f->fecha->format('d/m/Y'), 'total' => (float) $f->total, 'saldo' => (float) $f->saldo, 'monto' => 0])->toJson() }}, {{ (float) old('retencion', 0) }})"
                          class="bg-white p-6 shadow-sm sm:rounded-lg">
                        @csrf
                        <input type="hidden" name="proveedor_id" value="{{ $proveedorId }}">

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="fecha" value="Fecha del pago *" />
                                <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                              :value="old('fecha', now()->format('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
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
                        </div>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="py-2 pr-2">Factura</th>
                                        <th class="py-2 pr-2">Fecha</th>
                                        <th class="py-2 pr-2 text-right">Total</th>
                                        <th class="py-2 pr-2 text-right">Saldo</th>
                                        <th class="w-40 py-2 pr-2 text-right">Monto a pagar</th>
                                        <th class="w-20 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(factura, idx) in facturas" :key="factura.id">
                                        <tr class="border-t border-gray-100">
                                            <td class="py-2 pr-2 font-medium" x-text="factura.numero"></td>
                                            <td class="py-2 pr-2" x-text="factura.fecha"></td>
                                            <td class="py-2 pr-2 text-right" x-text="fmt(factura.total)"></td>
                                            <td class="py-2 pr-2 text-right" x-text="fmt(factura.saldo)"></td>
                                            <td class="py-2 pr-2">
                                                <input type="hidden" :name="`aplicaciones[${idx}][documento_id]`" :value="factura.id">
                                                <input type="number" step="0.01" min="0" :max="factura.saldo"
                                                       :name="`aplicaciones[${idx}][monto]`" x-model.number="factura.monto"
                                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="py-2 text-right">
                                                <button type="button" class="text-xs text-blue-700 hover:underline" @click="factura.monto = factura.saldo">Todo</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="border-t-2 border-gray-200 font-semibold">
                                    <tr>
                                        <td colspan="4" class="py-2 pr-2 text-right text-gray-700">Total liquidado</td>
                                        <td class="py-2 pr-2 text-right" x-text="fmt(total())"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('aplicaciones')" class="mt-1" />

                        {{-- Retención (ITBMS/ISR) descontada del efectivo y trasladada a la DGI --}}
                        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3 rounded-md bg-gray-50 p-4">
                            <div>
                                <x-input-label for="retencion" value="Retención (ITBMS/ISR)" />
                                <input id="retencion" name="retencion" type="number" step="0.01" min="0" :max="total()"
                                       x-model.number="retencion"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('retencion')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2" x-show="retencion > 0" x-cloak>
                                <x-input-label for="retencion_cuenta_id" value="Cuenta de retención por pagar *" />
                                <select id="retencion_cuenta_id" name="retencion_cuenta_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Cuenta —</option>
                                    @foreach ($cuentasPago as $cuenta)
                                        <option value="{{ $cuenta->id }}" @selected(old('retencion_cuenta_id', $cuentaRetencionId) == $cuenta->id)>{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('retencion_cuenta_id')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-500">Lo retenido no se paga al proveedor: queda como pasivo por enterar a la DGI.</p>
                            </div>
                            <div class="sm:col-span-3 flex items-center justify-end gap-6 border-t border-gray-200 pt-3 text-sm">
                                <span class="text-gray-600">Efectivo a pagar al proveedor:</span>
                                <span class="font-semibold text-gray-900" x-text="fmt(efectivo())"></span>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                            <button type="submit" :disabled="total() <= 0"
                                    class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                                Registrar pago
                            </button>
                            <a href="{{ route('admin.cxp.pagos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                            <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto">Asiento: débito a Cuentas por Pagar, crédito a la cuenta de pago (efectivo) y, si hay retención, crédito a la cuenta de retención por pagar.</p>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <script>
        function pagoCxp(facturas, retencionInicial) {
            return {
                facturas,
                retencion: retencionInicial || 0,
                total() { return this.facturas.reduce((s, f) => s + (parseFloat(f.monto) || 0), 0); },
                efectivo() { return Math.max(0, Math.round((this.total() - (parseFloat(this.retencion) || 0)) * 100) / 100); },
                fmt(v) { return 'B/. ' + (Math.round(v * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
