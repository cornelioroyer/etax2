<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva cotización</h2>
            <a href="{{ route('admin.ventas.cotizaciones.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
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

                <form method="POST" action="{{ route('admin.ventas.cotizaciones.store') }}"
                      x-data="cotizacionForm({{ old('lineas') ? collect(old('lineas'))->values()->toJson() : '[]' }}, {{ $impuestos->toJson() }}, {{ $items->toJson() }})">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-buscador-contacto name="cliente_id" label="Cliente *" required
                                placeholder="— Selecciona el cliente —"
                                :opciones="$clientes" :selected="old('cliente_id')" />
                            <x-input-error :messages="$errors->get('cliente_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="fecha" value="Fecha *" />
                            <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                          :value="old('fecha', now()->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="fecha_validez" value="Válida hasta" />
                            <x-text-input id="fecha_validez" name="fecha_validez" type="text" class="js-date mt-1 block w-full"
                                          :value="old('fecha_validez')" />
                            <x-input-error :messages="$errors->get('fecha_validez')" class="mt-1" />
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
                                        <th class="py-2 pr-2 min-w-[16rem]">Artículo / Descripción</th>
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
                                                {{-- Combobox de artículo de inventario (opcional) --}}
                                                <div class="relative mb-1" @click.away="linea.mostrarItems = false">
                                                    <input type="text"
                                                           x-model="linea.busqueda"
                                                           @input="linea.item_id = null; linea.mostrarItems = true"
                                                           @focus="linea.mostrarItems = true"
                                                           placeholder="Buscar artículo por código o nombre…"
                                                           autocomplete="off"
                                                           style="padding-right:1.75rem"
                                                           class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                           :class="linea.item_id ? 'text-gray-700 font-semibold' : 'text-gray-500'">
                                                    <button type="button" x-show="linea.item_id || linea.busqueda"
                                                            @click="limpiarItem(linea)"
                                                            style="position:absolute;right:0.4rem;top:50%;transform:translateY(-50%)"
                                                            class="text-xs text-gray-400 hover:text-gray-700">✕</button>
                                                    <div x-show="linea.mostrarItems && linea.busqueda.length > 0 && !linea.item_id"
                                                         class="absolute z-20 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-44 overflow-y-auto">
                                                        <template x-for="item in itemsFiltrados(linea.busqueda)" :key="item.id">
                                                            <button type="button"
                                                                    @mousedown.prevent
                                                                    @click="seleccionarItem(linea, item)"
                                                                    class="block w-full px-3 py-2 text-left text-xs hover:bg-indigo-50 border-b border-gray-100 last:border-0">
                                                                <span class="font-mono text-gray-400" x-text="item.codigo + ' '"></span>
                                                                <span class="text-gray-700" x-text="item.nombre"></span>
                                                                <span class="block text-gray-400 truncate" x-show="item.descripcion" x-text="item.descripcion"></span>
                                                            </button>
                                                        </template>
                                                        <div x-show="itemsFiltrados(linea.busqueda).length === 0"
                                                             class="px-3 py-2 text-xs text-gray-400">Sin resultados</div>
                                                    </div>
                                                </div>
                                                <input type="text" :name="`lineas[${idx}][descripcion]`" x-model="linea.descripcion" required
                                                       placeholder="Descripción o breve explicación de la línea"
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
                        <x-input-label for="notas" value="Notas / términos" />
                        <textarea id="notas" name="notas" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notas') }}</textarea>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                            Guardar cotización
                        </button>
                        <a href="{{ route('admin.ventas.cotizaciones.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function cotizacionForm(lineasIniciales, impuestos, items) {
            const impuestoDefault = impuestos.find(i => i.porcentaje == 7)?.id ?? impuestos[0]?.id ?? null;
            const itemsById = Object.fromEntries(items.map(i => [i.id, i]));
            const etiquetaItem = (item) => item ? (item.codigo ? `${item.codigo} — ${item.nombre}` : item.nombre) : '';
            const nueva = () => ({ item_id: null, descripcion: '', cantidad: 1, precio_unitario: 0, impuesto_id: impuestoDefault, busqueda: '', mostrarItems: false });
            const tasaMap = Object.fromEntries(impuestos.map(i => [i.id, parseFloat(i.porcentaje)]));
            return {
                impuestos,
                items,
                lineas: lineasIniciales.length
                    ? lineasIniciales.map(l => {
                        const itemId = parseInt(l.item_id) || null;
                        return {
                            item_id: itemId,
                            descripcion: l.descripcion ?? '',
                            cantidad: parseFloat(l.cantidad) || 1,
                            precio_unitario: parseFloat(l.precio_unitario) || 0,
                            impuesto_id: parseInt(l.impuesto_id) || impuestoDefault,
                            busqueda: itemId ? etiquetaItem(itemsById[itemId]) : '',
                            mostrarItems: false,
                        };
                    })
                    : [nueva()],
                agregar() { this.lineas.push(nueva()); },
                itemsFiltrados(busqueda) {
                    if (!busqueda) return [];
                    const b = busqueda.toLowerCase();
                    return this.items.filter(i =>
                        (i.nombre && i.nombre.toLowerCase().includes(b)) ||
                        (i.codigo && i.codigo.toLowerCase().includes(b)) ||
                        (i.descripcion && i.descripcion.toLowerCase().includes(b))
                    ).slice(0, 12);
                },
                seleccionarItem(linea, item) {
                    linea.item_id         = item.id;
                    linea.precio_unitario = parseFloat(item.precio_venta) || 0;
                    if (item.impuesto_id) linea.impuesto_id = item.impuesto_id;
                    // No pisar la descripción si el usuario ya escribió una explicación
                    if (!linea.descripcion || !linea.descripcion.trim()) {
                        linea.descripcion = item.descripcion || item.nombre;
                    }
                    linea.busqueda     = etiquetaItem(item);
                    linea.mostrarItems = false;
                },
                limpiarItem(linea) {
                    linea.item_id      = null;
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
