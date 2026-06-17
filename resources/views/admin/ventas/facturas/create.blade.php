<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ isset($factura) ? 'Editar borrador' : 'Nueva factura de venta' }}
            </h2>
            @if (isset($factura))
                <a href="{{ route('admin.ventas.facturas.show', $factura) }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al detalle</a>
            @else
                <a href="{{ route('admin.ventas.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif

                @php
                    $lineasIniciales = isset($factura)
                        ? $factura->detalle->map(fn($d) => ['item_id'=>$d->item_id,'descripcion'=>$d->descripcion,'cantidad'=>$d->cantidad,'precio_unitario'=>$d->precio_unitario,'impuesto_id'=>$d->impuesto_id,'cuenta_ingreso_id'=>$d->cuenta_ingreso_id])->values()->toJson()
                        : (old('lineas') ? collect(old('lineas'))->values()->toJson() : '[]');
                    $numeroManualGuardado = isset($factura) ? (string) data_get($factura->extra, 'numero_manual', '') : '';
                    $valorInicial  = old('numero_manual', $numeroManualGuardado !== '' ? $numeroManualGuardado : $numeroPreview);
                @endphp
                <form method="POST"
                      action="{{ isset($factura) ? route('admin.ventas.facturas.update', $factura) : route('admin.ventas.facturas.store') }}"
                      x-data="facturaForm({{ $lineasIniciales }}, {{ $impuestos->toJson() }}, {{ $cuentasIngreso->toJson() }}, {{ $cuentaVentasId ?? 'null' }}, {{ $items->toJson() }})">
                    @csrf
                    @isset($factura) @method('PUT') @endisset

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div x-data="{
                                auto: @js($numeroPreview),
                                valor: @js($valorInicial),
                                get esAuto() { return this.valor.trim() === '' || this.valor.trim() === this.auto; },
                             }">
                            <x-input-label for="numero_manual" value="Número" />
                            <input type="hidden" name="numeracion" :value="esAuto ? 'auto' : 'manual'">
                            <x-text-input id="numero_manual" name="numero_manual" type="text" class="mt-1 block w-full"
                                          x-model="valor" autocomplete="off" />
                            <p class="mt-1 text-xs" :class="esAuto ? 'text-gray-400' : 'text-indigo-600'"
                               x-text="esAuto ? 'Se asignará automáticamente al emitir. Puedes escribir tu propio número.' : 'Número manual: ' + valor.trim()"></p>
                            <x-input-error :messages="$errors->get('numero_manual')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="cliente_id" value="Cliente *" />
                            <select id="cliente_id" name="cliente_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Cliente —</option>
                                @foreach ($clientes as $cliente)
                                    <option value="{{ $cliente->id }}" @selected(old('cliente_id', $factura->cliente_id ?? null) == $cliente->id)>{{ $cliente->codigo }} — {{ $cliente->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('cliente_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="fecha" value="Fecha *" />
                            <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                          :value="old('fecha', isset($factura) ? $factura->fecha->format('Y-m-d') : now()->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="fecha_vencimiento" value="Vence" />
                            <x-text-input id="fecha_vencimiento" name="fecha_vencimiento" type="text" class="js-date mt-1 block w-full"
                                          :value="old('fecha_vencimiento', isset($factura) ? $factura->fecha_vencimiento?->format('Y-m-d') : '')" />
                            <x-input-error :messages="$errors->get('fecha_vencimiento')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Líneas --}}
                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Detalle</h3>
                            <button type="button" @click="agregar()" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">+ Agregar línea</button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="py-2 pr-2 min-w-[16rem]">Descripción</th>
                                        <th class="w-24 py-2 pr-2 text-right">Cant.</th>
                                        <th class="w-32 py-2 pr-2 text-right">Precio</th>
                                        <th class="w-32 py-2 pr-2">ITBMS</th>
                                        <th class="w-28 py-2 pr-2 text-right">Total</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(linea, idx) in lineas" :key="idx">
                                        <tr class="border-t border-gray-100 align-top">
                                            <td class="py-2 pr-2">
                                                <input type="hidden" :name="`lineas[${idx}][item_id]`" :value="linea.item_id ?? ''">
                                                {{-- Buscador de artículo --}}
                                                <div class="relative mb-1">
                                                    <input type="text"
                                                           x-model="linea.busqueda"
                                                           @input="linea.mostrarItems = true"
                                                           @focus="linea.mostrarItems = true"
                                                           placeholder="Buscar artículo…"
                                                           autocomplete="off"
                                                           class="block w-full rounded-md border-gray-300 text-xs text-gray-500 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <div x-show="linea.mostrarItems && linea.busqueda.length > 0"
                                                         class="absolute z-20 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-44 overflow-y-auto">
                                                        <template x-for="item in itemsFiltrados(linea.busqueda)" :key="item.id">
                                                            <button type="button"
                                                                    @mousedown.prevent
                                                                    @click="seleccionarItem(linea, item)"
                                                                    class="block w-full px-3 py-2 text-left text-xs hover:bg-indigo-50 border-b border-gray-100 last:border-0">
                                                                <span class="font-mono text-gray-400" x-text="item.codigo + ' '"></span>
                                                                <span x-text="item.nombre"></span>
                                                            </button>
                                                        </template>
                                                        <div x-show="itemsFiltrados(linea.busqueda).length === 0"
                                                             class="px-3 py-2 text-xs text-gray-400">Sin resultados</div>
                                                    </div>
                                                </div>
                                                <input type="hidden" :name="`lineas[${idx}][cuenta_ingreso_id]`" :value="linea.cuenta_ingreso_id ?? ''">
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
                                                <select :name="`lineas[${idx}][impuesto_id]`" x-model.number="linea.impuesto_id"
                                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <template x-for="imp in impuestos" :key="imp.id">
                                                        <option :value="imp.id" x-text="imp.nombre" :selected="linea.impuesto_id == imp.id"></option>
                                                    </template>
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
                                        <td colspan="4" class="py-1 pr-2 text-right text-gray-600">Subtotal</td>
                                        <td class="py-1 pr-2 text-right" x-text="fmt(subtotal())"></td><td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="py-1 pr-2 text-right text-gray-600">ITBMS</td>
                                        <td class="py-1 pr-2 text-right" x-text="fmt(totalItbms())"></td><td></td>
                                    </tr>
                                    <tr class="font-semibold">
                                        <td colspan="4" class="py-2 pr-2 text-right text-gray-700">Total</td>
                                        <td class="py-2 pr-2 text-right" x-text="fmt(subtotal() + totalItbms())"></td><td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('lineas')" class="mt-1" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="notas" value="Notas" />
                        <textarea id="notas" name="notas" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notas', $factura->notas ?? '') }}</textarea>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                        <input type="hidden" name="accion" id="accion-input" value="emitir">
                        <button type="submit" onclick="document.getElementById('accion-input').value='emitir'"
                                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                            Emitir factura
                        </button>
                        <button type="submit" onclick="document.getElementById('accion-input').value='borrador'"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Guardar borrador
                        </button>
                        <a href="{{ route('admin.ventas.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function facturaForm(lineasIniciales, impuestos, cuentasIngreso, cuentaVentasDefault, items) {
            const impuestoDefault = impuestos.find(i => i.porcentaje == 7)?.id ?? impuestos[0]?.id ?? null;
            const cuentaDefault = cuentaVentasDefault ?? null;
            const nueva = () => ({ item_id: null, descripcion: '', cantidad: 1, precio_unitario: 0, impuesto_id: impuestoDefault, cuenta_ingreso_id: cuentaDefault, busqueda: '', mostrarItems: false });
            const tasaMap = Object.fromEntries(impuestos.map(i => [i.id, parseFloat(i.porcentaje)]));
            return {
                impuestos,
                items,
                lineas: lineasIniciales.length
                    ? lineasIniciales.map(l => ({
                        item_id: parseInt(l.item_id) || null,
                        descripcion: l.descripcion ?? '',
                        cantidad: parseFloat(l.cantidad) || 1,
                        precio_unitario: parseFloat(l.precio_unitario) || 0,
                        impuesto_id: parseInt(l.impuesto_id) || impuestoDefault,
                        cuenta_ingreso_id: parseInt(l.cuenta_ingreso_id) || cuentaDefault,
                        busqueda: '',
                        mostrarItems: false,
                    }))
                    : [nueva()],
                agregar() { this.lineas.push(nueva()); },
                itemsFiltrados(busqueda) {
                    if (!busqueda) return [];
                    const b = busqueda.toLowerCase();
                    return this.items.filter(i =>
                        i.nombre.toLowerCase().includes(b) ||
                        (i.codigo && i.codigo.toLowerCase().includes(b))
                    ).slice(0, 12);
                },
                seleccionarItem(linea, item) {
                    linea.item_id           = item.id;
                    linea.descripcion       = item.nombre;
                    linea.precio_unitario   = parseFloat(item.precio_venta) || 0;
                    if (item.impuesto_id)       linea.impuesto_id       = item.impuesto_id;
                    if (item.cuenta_ingreso_id) linea.cuenta_ingreso_id = item.cuenta_ingreso_id;
                    linea.busqueda     = '';
                    linea.mostrarItems = false;
                },
                base(l) { return Math.round((parseFloat(l.cantidad)||0)*(parseFloat(l.precio_unitario)||0)*100)/100; },
                itbmsLinea(l) { const tasa = tasaMap[parseInt(l.impuesto_id)] ?? 0; return Math.round(this.base(l)*tasa)/100; },
                totalLinea(l) { return this.base(l) + this.itbmsLinea(l); },
                subtotal() { return this.lineas.reduce((s,l) => s + this.base(l), 0); },
                totalItbms() { return this.lineas.reduce((s,l) => s + this.itbmsLinea(l), 0); },
                fmt(v) { return 'B/. '+(Math.round(v*100)/100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
