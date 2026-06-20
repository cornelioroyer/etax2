<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo documento por pagar</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.cxp.facturas.store') }}"
                      x-data="facturaCxp({{ old('lineas') ? collect(old('lineas'))->values()->toJson() : '[]' }}, {{ (int) ($cuentaGastoId ?? 0) }}, {{ $proveedores->pluck('cuenta_gasto_id', 'id')->toJson() }}, '{{ old('forma_pago', 'CREDITO') }}', '{{ old('cuenta_pago_id', $cuentaPagoId) }}', '{{ old('tipo_documento', 'FACTURA') }}')"
                      x-init="$nextTick(() => onProveedor(document.getElementById('proveedor_id').value))">
                    @csrf

                    <div class="mb-4 max-w-xs">
                        <x-input-label for="tipo_documento" value="Tipo de documento *" />
                        <select id="tipo_documento" name="tipo_documento" x-model="tipo"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="FACTURA">Factura de compra</option>
                            <option value="IMPORTACION">Factura de importación</option>
                            <option value="REEMBOLSO">Reembolso de compra</option>
                            <option value="NOTA_DEBITO">Nota de débito</option>
                            <option value="NOTA_CREDITO">Nota de crédito</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500"
                           x-text="{ NOTA_CREDITO: 'Reduce lo que debes al proveedor (abono).', NOTA_DEBITO: 'Aumenta lo que debes al proveedor (cargo).', IMPORTACION: 'Compra de importación; genera deuda al proveedor como una factura.', REEMBOLSO: 'Gasto reembolsable facturado por el proveedor (cargo).' }[tipo] ?? 'Compra normal al proveedor.'"></p>
                        <x-input-error :messages="$errors->get('tipo_documento')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="proveedor_id" value="Proveedor *" />
                            <select id="proveedor_id" name="proveedor_id" required
                                    @change="onProveedor($event.target.value)"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Proveedor —</option>
                                @foreach ($proveedores as $proveedor)
                                    <option value="{{ $proveedor->id }}" @selected(old('proveedor_id') == $proveedor->id)>{{ $proveedor->codigo }} — {{ $proveedor->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('proveedor_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="numero">
                                <span x-text="tipo === 'NOTA_CREDITO' ? 'Número de nota de crédito *' : (tipo === 'NOTA_DEBITO' ? 'Número de nota de débito *' : 'Número de factura *')">Número de factura *</span>
                            </x-input-label>
                            <x-text-input id="numero" name="numero" type="text" class="mt-1 block w-full" required
                                          :value="old('numero')" placeholder="La del proveedor" />
                            <x-input-error :messages="$errors->get('numero')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="fecha" value="Fecha *" />
                            <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                          :value="old('fecha', now()->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                        </div>
                        <div x-show="!pagoInmediato()">
                            <x-input-label for="fecha_vencimiento" value="Vence" />
                            <x-text-input id="fecha_vencimiento" name="fecha_vencimiento" type="text" class="js-date mt-1 block w-full"
                                          :value="old('fecha_vencimiento')" />
                            <x-input-error :messages="$errors->get('fecha_vencimiento')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Forma de pago (solo documentos tipo factura; las notas van a crédito) --}}
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4" x-show="esContadoPosible()" x-cloak>
                        <div>
                            <x-input-label for="forma_pago" value="Forma de pago *" />
                            <select id="forma_pago" name="forma_pago" x-model="formaPago"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="CREDITO">Crédito — Cuenta por pagar</option>
                                <option value="CONTADO">Contado — Banco / Caja</option>
                                <option value="TARJETA">Tarjeta de crédito</option>
                            </select>
                            <x-input-error :messages="$errors->get('forma_pago')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2" x-show="pagoInmediato()" x-cloak>
                            <x-input-label for="cuenta_pago_id">
                                <span x-text="formaPago === 'TARJETA' ? 'Cuenta de tarjeta (pasivo) *' : 'Cuenta de banco / caja *'">Cuenta de banco / caja *</span>
                            </x-input-label>
                            <select id="cuenta_pago_id" name="cuenta_pago_id" x-model="cuentaPago"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Cuenta —</option>
                                @foreach ($cuentasPago as $cuenta)
                                    <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('cuenta_pago_id')" class="mt-1" />
                        </div>
                        <div class="flex items-end" x-show="pagoInmediato()" x-cloak>
                            <p class="text-xs text-gray-500"
                               x-text="formaPago === 'TARJETA'
                                   ? 'Se contabiliza al instante: gasto/inventario/activo + ITBMS contra la cuenta de tarjeta (pasivo). Alimenta el libro de compras.'
                                   : 'Se contabiliza al instante: gasto/inventario/activo + ITBMS contra banco/caja. Alimenta el libro de compras.'">
                            </p>
                        </div>
                    </div>

                    {{-- Líneas --}}
                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Detalle del documento</h3>
                            <button type="button" @click="agregar()" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">+ Agregar línea</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="py-2 pr-2 min-w-[14rem]">Descripción</th>
                                        <th class="w-24 py-2 pr-2 text-right">Cant.</th>
                                        <th class="w-32 py-2 pr-2 text-right">Precio</th>
                                        <th class="w-24 py-2 pr-2">ITBMS</th>
                                        <th class="py-2 pr-2 min-w-[14rem]">Cuenta contable (gasto / inventario / activo)</th>
                                        <th class="w-28 py-2 pr-2 text-right">Total</th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(linea, idx) in lineas" :key="idx">
                                        <tr class="border-t border-gray-100 align-top">
                                            <td class="py-2 pr-2">
                                                <input type="text" :name="`lineas[${idx}][descripcion]`" x-model="linea.descripcion" required
                                                       class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="number" step="0.0001" min="0.0001" :name="`lineas[${idx}][cantidad]`" x-model.number="linea.cantidad" required
                                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="number" step="0.01" min="0" :name="`lineas[${idx}][precio_unitario]`" x-model.number="linea.precio_unitario" required
                                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="py-2 pr-2">
                                                <select :name="`lineas[${idx}][tasa_itbms]`" x-model.number="linea.tasa_itbms"
                                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="0">Exento</option>
                                                    <option value="7">7%</option>
                                                    <option value="10">10%</option>
                                                    <option value="15">15%</option>
                                                </select>
                                            </td>
                                            <td class="py-2 pr-2 min-w-[16rem]">
                                                <input type="text" class="cuenta-buscar block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mb-1"
                                                       placeholder="Buscar cuenta..." autocomplete="off" style="padding:.25rem .5rem">
                                                <select :name="`lineas[${idx}][cuenta_id]`" x-model="linea.cuenta_id" required
                                                        class="cuenta-select block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">— Cuenta —</option>
                                                    @foreach ($cuentas as $cuenta)
                                                        <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="py-2 pr-2 text-right whitespace-nowrap" x-text="fmt(totalLinea(linea))"></td>
                                            <td class="py-2 text-right">
                                                <button type="button" @click="lineas.splice(idx, 1)" x-show="lineas.length > 1"
                                                        class="mt-2 text-red-500 hover:text-red-700">✕</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="border-t-2 border-gray-200 text-sm">
                                    <tr>
                                        <td colspan="5" class="py-1 pr-2 text-right text-gray-600">Subtotal</td>
                                        <td class="py-1 pr-2 text-right" x-text="fmt(subtotal())"></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="py-1 pr-2 text-right text-gray-600">ITBMS</td>
                                        <td class="py-1 pr-2 text-right" x-text="fmt(itbms())"></td>
                                        <td></td>
                                    </tr>
                                    <tr class="font-semibold">
                                        <td colspan="5" class="py-2 pr-2 text-right text-gray-700">Total</td>
                                        <td class="py-2 pr-2 text-right" x-text="fmt(subtotal() + itbms())"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('lineas')" class="mt-1" />
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500"
                                x-text="esContadoPosible() && formaPago === 'TARJETA' ? 'Registrar compra con tarjeta'
                                       : (esContadoPosible() && formaPago === 'CONTADO' ? 'Registrar compra al contado'
                                       : 'Guardar borrador')">
                            Guardar borrador
                        </button>
                        <a href="{{ route('admin.cxp.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto"
                           x-text="esContadoPosible() && pagoInmediato() ? 'La compra se contabiliza de inmediato y queda pagada.' : 'Se guarda como borrador editable; el asiento contable se genera al contabilizarlo.'">Se guarda como borrador editable; el asiento contable se genera al contabilizarlo.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        document.addEventListener('input', function (e) {
            if (!e.target.classList.contains('cuenta-buscar')) return;
            var q = e.target.value.trim().toLowerCase();
            var sel = e.target.closest('td').querySelector('.cuenta-select');
            var vis = 0;
            Array.from(sel.options).forEach(function (o) {
                var show = !q || !o.value || o.text.toLowerCase().indexOf(q) !== -1;
                o.hidden = !show;
                if (show) vis++;
            });
            sel.size = q ? Math.min(8, vis) : 1;
        });
        document.addEventListener('change', function (e) {
            if (!e.target.classList.contains('cuenta-select')) return;
            var td = e.target.closest('td');
            var inp = td.querySelector('.cuenta-buscar');
            if (inp) inp.value = '';
            e.target.size = 1;
            Array.from(e.target.options).forEach(function (o) { o.hidden = false; });
        });
        document.addEventListener('blur', function (e) {
            if (!e.target.classList.contains('cuenta-select')) return;
            e.target.size = 1;
            var inp = e.target.closest('td').querySelector('.cuenta-buscar');
            if (inp) inp.value = '';
            Array.from(e.target.options).forEach(function (o) { o.hidden = false; });
        }, true);
    })();
    </script>

    <script>
        function facturaCxp(lineasIniciales, cuentaGastoId, provCuentas, formaPagoInicial, cuentaPagoInicial, tipoInicial) {
            return {
                cuentaGlobal: cuentaGastoId || '',
                provCuentas: provCuentas || {},
                cuentaActual: cuentaGastoId || '',
                tipo: tipoInicial || 'FACTURA',
                formaPago: formaPagoInicial || 'CREDITO',
                cuentaPago: cuentaPagoInicial || '',
                esContadoPosible() { return ['FACTURA', 'REEMBOLSO', 'IMPORTACION'].includes(this.tipo); },
                pagoInmediato() { return this.esContadoPosible() && ['CONTADO', 'TARJETA'].includes(this.formaPago); },
                nueva() { return { descripcion: '', cantidad: 1, precio_unitario: 0, tasa_itbms: 7, cuenta_id: this.cuentaActual || '' }; },
                lineas: lineasIniciales.length
                    ? lineasIniciales.map(l => ({
                        descripcion: l.descripcion ?? '',
                        cantidad: parseFloat(l.cantidad) || 1,
                        precio_unitario: parseFloat(l.precio_unitario) || 0,
                        tasa_itbms: parseInt(l.tasa_itbms) || 0,
                        cuenta_id: l.cuenta_id ?? '',
                    }))
                    : [],
                onProveedor(provId) {
                    const nuevaCuenta = String((this.provCuentas[provId] ?? '') || this.cuentaGlobal || '');
                    const anterior = String(this.cuentaActual || '');
                    // Actualiza solo líneas vacías o que aún tenían la cuenta por defecto anterior (no las editadas a mano)
                    this.lineas.forEach(l => {
                        if (l.cuenta_id === '' || String(l.cuenta_id) === anterior) l.cuenta_id = nuevaCuenta;
                    });
                    this.cuentaActual = nuevaCuenta;
                    if (this.lineas.length === 0) this.lineas.push(this.nueva());
                },
                agregar() { this.lineas.push(this.nueva()); },
                base(l) { return Math.round((parseFloat(l.cantidad) || 0) * (parseFloat(l.precio_unitario) || 0) * 100) / 100; },
                itbmsLinea(l) { return Math.round(this.base(l) * (parseInt(l.tasa_itbms) || 0)) / 100; },
                totalLinea(l) { return this.base(l) + this.itbmsLinea(l); },
                subtotal() { return this.lineas.reduce((s, l) => s + this.base(l), 0); },
                itbms() { return this.lineas.reduce((s, l) => s + this.itbmsLinea(l), 0); },
                fmt(v) { return 'B/. ' + (Math.round(v * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
