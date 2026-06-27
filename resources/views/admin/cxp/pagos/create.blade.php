<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar pago a proveedor</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Paso 1 ya resuelto: proveedor elegido en la lista. Mostrar quién es y permitir cambiarlo. --}}
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-6 shadow-sm sm:rounded-lg">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Proveedor</p>
                    <p class="text-lg font-semibold text-gray-800">{{ $proveedor->nombre }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $proveedor->codigo }}@if ($proveedor->identificacion) · RUC {{ $proveedor->identificacion }}@endif
                    </p>
                </div>
                <a href="{{ route('admin.cxp.pagos.create') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    &larr; Cambiar proveedor
                </a>
            </div>

            @if ($proveedorId)
                @if ($facturas->isEmpty())
                    <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                        El proveedor no tiene facturas con saldo pendiente.
                    </div>
                @else
                    @php
                        // Montos previos (corrección o validación fallida) por factura.
                        $oldAplic = collect(old('aplicaciones', []))
                            ->mapWithKeys(fn ($a) => [(int) ($a['documento_id'] ?? 0) => (float) ($a['monto'] ?? 0)]);
                        $oldCred = collect(old('creditos', []))
                            ->mapWithKeys(fn ($c) => [(int) ($c['documento_id'] ?? 0) => (float) ($c['monto'] ?? 0)]);
                        $facturasJson = $facturas->map(fn ($f) => [
                            'id' => $f->id,
                            'numero' => $f->numero,
                            'fecha' => $f->fecha->format('d/m/Y'),
                            'total' => (float) $f->total,
                            'saldo' => (float) $f->saldo,
                            'monto' => round((float) $oldAplic->get($f->id, 0), 2),
                        ]);
                        $creditosJson = $creditos->map(fn ($c) => [
                            'id' => $c->id,
                            'numero' => $c->numero,
                            'tipo' => $c->tipo_documento === \App\Models\CxpDocumento::TIPO_ANTICIPO ? 'Anticipo' : 'Nota de crédito',
                            'fecha' => $c->fecha->format('d/m/Y'),
                            'saldo' => (float) $c->saldo,
                            'monto' => round((float) $oldCred->get($c->id, 0), 2),
                        ]);
                    @endphp
                    <form method="POST" action="{{ route('admin.cxp.pagos.store') }}"
                          x-data="pagoCxp({{ $facturasJson->toJson() }}, {{ $creditosJson->toJson() }}, {{ (float) old('retencion', 0) }}, {{ (float) old('retencion_isr', 0) }}, {{ (float) old('descuento', 0) }})"
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
                                <x-buscador-contacto name="cuenta_pago_id" label="Pagar desde (cuenta) *" required
                                    :opciones="$cuentasPago" :selected="old('cuenta_pago_id', $cuentaBancoId)"
                                    placeholder="Buscar cuenta por código o nombre" />
                                <x-input-error :messages="$errors->get('cuenta_pago_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="referencia" value="Referencia" />
                                <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                                              :value="old('referencia')" placeholder="Transferencia, cheque…" />
                                <x-input-error :messages="$errors->get('referencia')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Facturas a pagar --}}
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="py-2 pr-2">Factura</th>
                                        <th class="py-2 pr-2">Fecha</th>
                                        <th class="py-2 pr-2 text-right">Total</th>
                                        <th class="py-2 pr-2 text-right">Saldo</th>
                                        <th class="w-40 py-2 pr-2 text-right">Monto a liquidar</th>
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
                                        <td colspan="4" class="py-2 pr-2 text-right text-gray-700">Total a liquidar</td>
                                        <td class="py-2 pr-2 text-right" x-text="fmt(totalLiquidado())"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('aplicaciones')" class="mt-1" />

                        {{-- Créditos a favor del proveedor (anticipos / notas de crédito) --}}
                        @if ($creditos->isNotEmpty())
                            <div class="mt-6 rounded-md border border-emerald-200 bg-emerald-50 p-4">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Aplicar créditos a favor</h3>
                                <p class="mt-1 text-xs text-emerald-700">Anticipos y notas de crédito disponibles. Lo aplicado reduce el efectivo a pagar.</p>
                                <table class="mt-3 min-w-full text-sm">
                                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                        <tr>
                                            <th class="py-1 pr-2">Documento</th>
                                            <th class="py-1 pr-2">Tipo</th>
                                            <th class="py-1 pr-2">Fecha</th>
                                            <th class="py-1 pr-2 text-right">Disponible</th>
                                            <th class="w-40 py-1 pr-2 text-right">Aplicar</th>
                                            <th class="w-20 py-1"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(credito, idx) in creditos" :key="credito.id">
                                            <tr class="border-t border-emerald-100">
                                                <td class="py-1.5 pr-2 font-medium" x-text="credito.numero"></td>
                                                <td class="py-1.5 pr-2" x-text="credito.tipo"></td>
                                                <td class="py-1.5 pr-2" x-text="credito.fecha"></td>
                                                <td class="py-1.5 pr-2 text-right" x-text="fmt(credito.saldo)"></td>
                                                <td class="py-1.5 pr-2">
                                                    <input type="hidden" :name="`creditos[${idx}][documento_id]`" :value="credito.id">
                                                    <input type="number" step="0.01" min="0" :max="credito.saldo"
                                                           :name="`creditos[${idx}][monto]`" x-model.number="credito.monto"
                                                           class="block w-full rounded-md border-emerald-300 text-right text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                </td>
                                                <td class="py-1.5 text-right">
                                                    <button type="button" class="text-xs text-emerald-700 hover:underline" @click="credito.monto = Math.min(credito.saldo, restanteParaCreditos(credito))">Usar</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="border-t-2 border-emerald-200 font-semibold text-emerald-800">
                                        <tr>
                                            <td colspan="4" class="py-1.5 pr-2 text-right">Total créditos aplicados</td>
                                            <td class="py-1.5 pr-2 text-right" x-text="fmt(totalCreditos())"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                                <p class="mt-1 text-xs text-red-600" x-show="totalCreditos() > totalLiquidado() + 0.004" x-cloak>
                                    Los créditos aplicados no pueden exceder el total a liquidar.
                                </p>
                            </div>
                            <x-input-error :messages="$errors->get('creditos')" class="mt-1" />
                        @endif

                        {{-- Retenciones (ITBMS / ISR) y descuento por pronto pago --}}
                        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 rounded-md bg-gray-50 p-4">
                            {{-- Retención ITBMS --}}
                            <div>
                                <x-input-label for="retencion" value="Retención ITBMS" />
                                <input id="retencion" name="retencion" type="number" step="0.01" min="0"
                                       x-model.number="retencion"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('retencion')" class="mt-1" />
                                <div x-show="retencion > 0" x-cloak class="mt-2">
                                    <select name="retencion_cuenta_id"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Cuenta de retención ITBMS por pagar —</option>
                                        @foreach ($cuentasPago as $cuenta)
                                            <option value="{{ $cuenta->id }}" @selected(old('retencion_cuenta_id', $cuentaRetencionItbmsId) == $cuenta->id)>{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('retencion_cuenta_id')" class="mt-1" />
                                </div>
                            </div>
                            {{-- Retención ISR --}}
                            <div>
                                <x-input-label for="retencion_isr" value="Retención ISR" />
                                <input id="retencion_isr" name="retencion_isr" type="number" step="0.01" min="0"
                                       x-model.number="retencionIsr"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('retencion_isr')" class="mt-1" />
                                <div x-show="retencionIsr > 0" x-cloak class="mt-2">
                                    <select name="retencion_isr_cuenta_id"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Cuenta de retención ISR por pagar —</option>
                                        @foreach ($cuentasPago as $cuenta)
                                            <option value="{{ $cuenta->id }}" @selected(old('retencion_isr_cuenta_id', $cuentaRetencionIsrId) == $cuenta->id)>{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('retencion_isr_cuenta_id')" class="mt-1" />
                                </div>
                            </div>
                            {{-- Descuento por pronto pago --}}
                            <div>
                                <x-input-label for="descuento" value="Descuento por pronto pago" />
                                <input id="descuento" name="descuento" type="number" step="0.01" min="0"
                                       x-model.number="descuento"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-right shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('descuento')" class="mt-1" />
                                <div x-show="descuento > 0" x-cloak class="mt-2">
                                    <select name="descuento_cuenta_id"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Cuenta de ingreso por descuento —</option>
                                        @foreach ($cuentasPago as $cuenta)
                                            <option value="{{ $cuenta->id }}" @selected(old('descuento_cuenta_id', $cuentaDescuentoId) == $cuenta->id)>{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('descuento_cuenta_id')" class="mt-1" />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Liquida la factura completa pagando menos; el descuento se registra como ingreso.</p>
                            </div>
                            {{-- Resumen --}}
                            <div class="rounded-md bg-white p-3 text-sm shadow-sm">
                                <div class="flex items-center justify-between"><span class="text-gray-600">Total a liquidar</span><span class="font-medium" x-text="fmt(totalLiquidado())"></span></div>
                                <div class="flex items-center justify-between" x-show="totalCreditos() > 0" x-cloak><span class="text-gray-600">− Créditos a favor</span><span class="font-medium text-emerald-700" x-text="fmt(totalCreditos())"></span></div>
                                <div class="flex items-center justify-between" x-show="retencionTotal() > 0" x-cloak><span class="text-gray-600">− Retenciones</span><span class="font-medium text-amber-700" x-text="fmt(retencionTotal())"></span></div>
                                <div class="flex items-center justify-between" x-show="descuento > 0" x-cloak><span class="text-gray-600">− Descuento</span><span class="font-medium text-amber-700" x-text="fmt(descuento)"></span></div>
                                <div class="mt-2 flex items-center justify-between border-t border-gray-200 pt-2"><span class="font-semibold text-gray-800">Efectivo a pagar</span><span class="text-lg font-bold text-[#0d2d5e]" x-text="fmt(efectivo())"></span></div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                            <button type="submit" :disabled="totalLiquidado() <= 0 || totalCreditos() > totalLiquidado() + 0.004"
                                    class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                                Registrar pago
                            </button>
                            <a href="{{ route('admin.cxp.pagos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                            <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto">Asiento: débito a Cuentas por Pagar, crédito a la cuenta de pago (efectivo) y, si aplica, a retenciones y descuento.</p>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <script>
        function pagoCxp(facturas, creditos, retencionInicial, retencionIsrInicial, descuentoInicial) {
            return {
                facturas,
                creditos,
                retencion: retencionInicial || 0,
                retencionIsr: retencionIsrInicial || 0,
                descuento: descuentoInicial || 0,
                totalLiquidado() { return this.facturas.reduce((s, f) => s + (parseFloat(f.monto) || 0), 0); },
                totalCreditos() { return this.creditos.reduce((s, c) => s + (parseFloat(c.monto) || 0), 0); },
                retencionTotal() { return (parseFloat(this.retencion) || 0) + (parseFloat(this.retencionIsr) || 0); },
                // Lo que aún se puede cubrir con créditos sin pasar del total a liquidar.
                restanteParaCreditos(credito) {
                    const otros = this.totalCreditos() - (parseFloat(credito.monto) || 0);
                    return Math.max(0, this.totalLiquidado() - otros);
                },
                efectivo() {
                    const v = this.totalLiquidado() - this.totalCreditos() - this.retencionTotal() - (parseFloat(this.descuento) || 0);
                    return Math.max(0, Math.round(v * 100) / 100);
                },
                fmt(v) { return 'B/. ' + (Math.round((parseFloat(v) || 0) * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
