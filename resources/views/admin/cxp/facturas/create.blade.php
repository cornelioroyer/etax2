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
                                                <input type="hidden" :name="`lineas[${idx}][item_id]`" :value="linea.item_id ?? ''">
                                                {{-- Combobox de artículo de inventario (opcional): elígelo para subir stock --}}
                                                <div class="relative mb-1" @click.outside="linea.mostrarItems = false">
                                                    <input type="text" x-model="linea.busqueda" autocomplete="off"
                                                           @focus="linea.mostrarItems = true" @input="linea.item_id = null; linea.mostrarItems = true"
                                                           placeholder="Buscar artículo (opcional)…"
                                                           style="padding-right:1.75rem"
                                                           class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                           :class="linea.item_id ? 'text-gray-800 font-semibold' : 'text-gray-500'">
                                                    <button type="button" x-show="linea.item_id || linea.busqueda" @click="limpiarArticulo(linea)"
                                                            style="position:absolute;right:0.4rem;top:50%;transform:translateY(-50%)"
                                                            class="text-xs text-gray-400 hover:text-gray-700">✕</button>
                                                    <div x-show="linea.mostrarItems && linea.busqueda.length > 0 && !linea.item_id" x-cloak
                                                         class="absolute z-30 mt-1 w-full max-h-44 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
                                                        <template x-for="art in articulosFiltrados(linea.busqueda)" :key="art.id">
                                                            <button type="button" @click="seleccionarArticulo(linea, art)"
                                                                    class="block w-full px-3 py-1.5 text-left text-xs hover:bg-indigo-50">
                                                                <span class="font-semibold text-gray-800" x-text="art.codigo ? art.codigo + ' — ' + art.nombre : art.nombre"></span>
                                                                <span class="ml-1 text-gray-400" x-text="art.tipo === 'PRODUCTO' ? '· inventario' : '· servicio'"></span>
                                                            </button>
                                                        </template>
                                                        <div x-show="articulosFiltrados(linea.busqueda).length === 0" class="px-3 py-1.5 text-xs text-gray-400">Sin resultados</div>
                                                    </div>
                                                </div>
                                                <input type="text" :name="`lineas[${idx}][descripcion]`" x-model="linea.descripcion" required
                                                       placeholder="Descripción"
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
                                                {{-- Combobox de un solo campo: se escribe y se elige en el mismo input --}}
                                                <div class="relative" x-data="comboCuenta(linea)" @click.outside="cerrar()">
                                                    <input type="text" x-model="texto" autocomplete="off"
                                                           @focus="open = true; $event.target.select()" @input="open = true"
                                                           @keydown.escape="cerrar()" placeholder="Buscar cuenta..."
                                                           class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                           style="padding:.35rem .5rem">
                                                    <input type="hidden" :name="`lineas[${idx}][cuenta_id]`" :value="linea.cuenta_id">
                                                    <ul x-show="open" x-cloak
                                                        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                                        <template x-for="c in opciones()" :key="c.id">
                                                            <li @mousedown.prevent="elegir(c)"
                                                                class="cursor-pointer px-3 py-1.5 hover:bg-indigo-50" x-text="c.label"></li>
                                                        </template>
                                                        <li x-show="opciones().length === 0" class="px-3 py-1.5 text-gray-400">Sin resultados</li>
                                                    </ul>
                                                </div>
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
        // Catálogo de cuentas para el combobox de cada línea.
        @php
            $cuentasCxp = $cuentas->map(fn ($c) => ['id' => (string) $c->id, 'label' => $c->codigo.' — '.$c->nombre])->values();
        @endphp
        const CUENTAS_CXP = @json($cuentasCxp);

        // Combobox de un solo campo: escribe para filtrar, clic para elegir.
        function comboCuenta(linea) {
            return {
                open: false,
                texto: '',
                init() {
                    this.sync();
                    this.$watch('linea.cuenta_id', () => { if (!this.open) this.sync(); });
                },
                sync() {
                    const c = CUENTAS_CXP.find(x => x.id === String(linea.cuenta_id));
                    this.texto = c ? c.label : '';
                },
                opciones() {
                    const sel = CUENTAS_CXP.find(x => x.id === String(linea.cuenta_id));
                    const q = this.texto.trim().toLowerCase();
                    if (!q || (sel && this.texto === sel.label)) return CUENTAS_CXP.slice(0, 50);
                    return CUENTAS_CXP.filter(c => c.label.toLowerCase().includes(q)).slice(0, 50);
                },
                elegir(c) { linea.cuenta_id = c.id; this.texto = c.label; this.open = false; },
                cerrar() { this.open = false; this.sync(); },
            };
        }
    </script>

    <script>
        // Catálogo de artículos para el combobox de cada línea de compra.
        @php
            $articulosCxp = $articulos->map(fn ($a) => [
                'id' => (string) $a->id,
                'codigo' => $a->codigo,
                'nombre' => $a->nombre,
                'tipo' => $a->tipo,
                'costo' => (float) $a->costo,
                'cuenta_inventario_id' => $a->cuenta_inventario_id ? (string) $a->cuenta_inventario_id : '',
                'cuenta_gasto_id' => $a->cuenta_gasto_id ? (string) $a->cuenta_gasto_id : '',
            ])->values();
        @endphp
        const ARTICULOS_CXP = @json($articulosCxp);
        const CUENTA_INVENTARIO_DEFAULT = '{{ (int) ($cuentaInventarioId ?? 0) ?: '' }}';

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
                nueva() { return { item_id: null, busqueda: '', mostrarItems: false, descripcion: '', cantidad: 1, precio_unitario: 0, tasa_itbms: 7, cuenta_id: this.cuentaActual || '' }; },
                lineas: lineasIniciales.length
                    ? lineasIniciales.map(l => {
                        const art = l.item_id ? ARTICULOS_CXP.find(a => a.id === String(l.item_id)) : null;
                        return {
                            item_id: l.item_id ? parseInt(l.item_id) : null,
                            busqueda: art ? (art.codigo ? art.codigo + ' — ' + art.nombre : art.nombre) : '',
                            mostrarItems: false,
                            descripcion: l.descripcion ?? '',
                            cantidad: parseFloat(l.cantidad) || 1,
                            precio_unitario: parseFloat(l.precio_unitario) || 0,
                            tasa_itbms: parseInt(l.tasa_itbms) || 0,
                            cuenta_id: l.cuenta_id ?? '',
                        };
                    })
                    : [],
                articulosFiltrados(q) {
                    const t = (q || '').trim().toLowerCase();
                    const lista = !t ? ARTICULOS_CXP : ARTICULOS_CXP.filter(a =>
                        (a.codigo || '').toLowerCase().includes(t) || (a.nombre || '').toLowerCase().includes(t));
                    return lista.slice(0, 12);
                },
                seleccionarArticulo(linea, art) {
                    linea.item_id = parseInt(art.id);
                    linea.busqueda = art.codigo ? art.codigo + ' — ' + art.nombre : art.nombre;
                    if (!linea.descripcion) linea.descripcion = art.nombre;
                    if (art.costo > 0) linea.precio_unitario = art.costo;
                    // Producto → cuenta de inventario del ítem (o default); servicio → cuenta de gasto del ítem.
                    if (art.tipo === 'PRODUCTO') {
                        linea.cuenta_id = art.cuenta_inventario_id || CUENTA_INVENTARIO_DEFAULT || linea.cuenta_id;
                    } else if (art.cuenta_gasto_id) {
                        linea.cuenta_id = art.cuenta_gasto_id;
                    }
                    linea.mostrarItems = false;
                },
                limpiarArticulo(linea) {
                    linea.item_id = null;
                    linea.busqueda = '';
                    linea.mostrarItems = false;
                },
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
