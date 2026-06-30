<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kardex de inventario</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="flex flex-wrap gap-3 items-end"
                @can('inventario.gestionar') x-data="recalcCostos()" @endcan>
                {{-- Combobox buscable por código o nombre. Reusa el combobox genérico
                     Alpine de <x-buscador-contacto> (lee id/codigo/nombre); funciona sin
                     recompilar el bundle. El botón "Filtrar" mantiene el flujo de submit. --}}
                <div>
                    <x-buscador-contacto
                        name="item_id"
                        label="Producto"
                        :opciones="$items"
                        :selected="$itemId"
                        placeholder="Todos — código o nombre"
                        empty-label="Todos"
                        width="w-72"
                        compact
                    />
                </div>
                {{-- Combobox buscable por código o nombre (mismo componente genérico). --}}
                <div>
                    <x-buscador-contacto
                        name="almacen_id"
                        label="Almacén"
                        :opciones="$almacenes"
                        :selected="$almacenId"
                        placeholder="Todos — código o nombre"
                        empty-label="Todos"
                        width="w-56"
                        compact
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Desde</label>
                    <input type="text" name="desde" value="{{ $desde }}" class="js-date rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                    <input type="text" name="hasta" value="{{ $hasta }}" class="js-date rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <button type="submit" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Filtrar</button>

                @can('inventario.gestionar')
                    {{-- Recalcula los costos de salida por promedio ponderado en orden de
                         FECHA para el filtro vigente y postea el asiento de ajuste por la
                         diferencia. Útil para limpiar costos viejos de documentos
                         back-dated previos al auto-reconcile. --}}
                    <button type="button" @click="abrir()"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Recalcular costos
                    </button>

                    {{-- Modal de previsualización / aplicación --}}
                    <div x-show="open" x-cloak @keydown.escape.window="open=false"
                         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto py-10"
                         style="background:rgba(0,0,0,.5)">
                        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-4" @click.outside="open=false">
                            <div class="flex items-center justify-between px-5 py-3 border-b">
                                <h3 class="font-semibold text-gray-800">Recalcular costos de inventario</h3>
                                <button type="button" @click="open=false" class="text-gray-400 hover:text-gray-600">&times;</button>
                            </div>

                            <div class="px-5 py-4 text-sm">
                                <template x-if="cargando">
                                    <p class="text-gray-500 py-6 text-center">Analizando…</p>
                                </template>

                                <template x-if="error">
                                    <p class="text-red-600 py-4" x-text="'Error: ' + error"></p>
                                </template>

                                {{-- Resultado de aplicar --}}
                                <template x-if="hecho">
                                    <p class="text-green-700 py-4" x-text="hecho.mensaje"></p>
                                </template>

                                {{-- Sin cambios --}}
                                <template x-if="data && data.sinCambios && !hecho">
                                    <p class="text-gray-600 py-4" x-text="data.mensaje"></p>
                                </template>

                                {{-- Plan con cambios --}}
                                <template x-if="data && !data.sinCambios && !hecho">
                                    <div class="space-y-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase text-gray-500 mb-1">Salidas a corregir</p>
                                            <table class="min-w-full text-xs border">
                                                <thead class="bg-gray-50 text-gray-500">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left">Fecha</th>
                                                        <th class="px-2 py-1 text-left">Documento</th>
                                                        <th class="px-2 py-1 text-left">Ítem</th>
                                                        <th class="px-2 py-1 text-right">Cant</th>
                                                        <th class="px-2 py-1 text-right">Costo viejo</th>
                                                        <th class="px-2 py-1 text-right">Costo nuevo</th>
                                                        <th class="px-2 py-1 text-right">Δ valor</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="(c,i) in data.cambios" :key="i">
                                                        <tr class="border-t">
                                                            <td class="px-2 py-1" x-text="c.fecha"></td>
                                                            <td class="px-2 py-1" x-text="c.documento"></td>
                                                            <td class="px-2 py-1" x-text="c.item"></td>
                                                            <td class="px-2 py-1 text-right" x-text="fmt(c.cantidad)"></td>
                                                            <td class="px-2 py-1 text-right" x-text="fmt(c.costo_viejo)"></td>
                                                            <td class="px-2 py-1 text-right font-medium" x-text="fmt(c.costo_nuevo)"></td>
                                                            <td class="px-2 py-1 text-right" :class="c.delta>=0?'text-green-700':'text-red-600'" x-text="fmt(c.delta)"></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div>
                                            <p class="text-xs font-semibold uppercase text-gray-500 mb-1">Asiento de ajuste (fecha <span x-text="data.fecha"></span>)</p>
                                            <table class="min-w-full text-xs border">
                                                <thead class="bg-gray-50 text-gray-500">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left">Descripción</th>
                                                        <th class="px-2 py-1 text-right">Débito</th>
                                                        <th class="px-2 py-1 text-right">Crédito</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="(l,i) in data.asiento" :key="i">
                                                        <tr class="border-t">
                                                            <td class="px-2 py-1" x-text="l.descripcion"></td>
                                                            <td class="px-2 py-1 text-right" x-text="fmt(l.debito)"></td>
                                                            <td class="px-2 py-1 text-right" x-text="fmt(l.credito)"></td>
                                                        </tr>
                                                    </template>
                                                    <tr x-show="!data.asiento.length"><td colspan="3" class="px-2 py-2 text-center text-gray-400">Sin asiento (neto cero)</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </template>

                                {{-- Ítems con historial de movimientos incompleto: la salvaguarda de
                                     RecalculadorCostosInventario los excluyó del plan (no se tocan) en
                                     vez de pisar su saldo a ciegas. Se muestra sin importar sinCambios. --}}
                                <template x-if="data && data.noReconciliables && data.noReconciliables.length">
                                    <div class="mt-4 rounded-md p-3 text-xs" style="background-color:#fef3c7;color:#92400e">
                                        <p class="font-semibold mb-1">Atención: ítems con historial de movimientos incompleto (no se tocaron, requieren revisión manual)</p>
                                        <ul class="list-disc list-inside">
                                            <template x-for="(n,i) in data.noReconciliables" :key="i">
                                                <li>
                                                    <span x-text="n.item"></span>:
                                                    existencia actual <span x-text="fmt(n.cantidad_actual)"></span>,
                                                    según movimientos <span x-text="fmt(n.cantidad_calculada)"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </div>

                            <div class="flex justify-end gap-2 px-5 py-3 border-t bg-gray-50">
                                <button type="button" @click="open=false"
                                        class="rounded-md bg-white border px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cerrar</button>
                                <button type="button" @click="aplicar()" x-show="data && !data.sinCambios && !hecho" :disabled="aplicando"
                                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                    <span x-text="aplicando ? 'Aplicando…' : 'Aplicar'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endcan
            </form>

            @can('inventario.gestionar')
                <script>
                    function recalcCostos() {
                        return {
                            open: false, cargando: false, aplicando: false,
                            data: null, hecho: null, error: null,
                            itemId: @json($itemId), almacenId: @json($almacenId),
                            previewUrl: @json(route('admin.inventario.kardex.recalcular.preview')),
                            aplicarUrl: @json(route('admin.inventario.kardex.recalcular.aplicar')),
                            token: document.querySelector('meta[name=csrf-token]').content,
                            async _post(url) {
                                const r = await fetch(url, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token },
                                    body: JSON.stringify({ item_id: this.itemId, almacen_id: this.almacenId }),
                                });
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                                return r.json();
                            },
                            async abrir() {
                                this.open = true; this.error = null; this.hecho = null; this.data = null; this.cargando = true;
                                try { this.data = await this._post(this.previewUrl); }
                                catch (e) { this.error = e.message; }
                                finally { this.cargando = false; }
                            },
                            async aplicar() {
                                this.aplicando = true; this.error = null;
                                try { this.hecho = await this._post(this.aplicarUrl); setTimeout(() => window.location.reload(), 1500); }
                                catch (e) { this.error = e.message; }
                                finally { this.aplicando = false; }
                            },
                            fmt(n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 }); },
                        };
                    }
                </script>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-3">Fecha</th>
                            <th class="px-3 py-3">Producto</th>
                            <th class="px-3 py-3">Almacén</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Doc. origen</th>
                            <th class="px-3 py-3">Descripción</th>
                            <th class="px-3 py-3 text-right">Entrada Qty</th>
                            <th class="px-3 py-3 text-right">Costo entrada</th>
                            <th class="px-3 py-3 text-right">Salida Qty</th>
                            <th class="px-3 py-3 text-right">Costo salida</th>
                            <th class="px-3 py-3 text-right">Saldo Qty</th>
                            <th class="px-3 py-3 text-right">Saldo costo</th>
                            <th class="px-3 py-3 text-right">Costo prom.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($kardex as $k)
                            <tr class="hover:bg-gray-50" @if($k->es_inicial ?? false) style="background-color:#fffbeb;font-style:italic" @endif>
                                <td class="px-3 py-2">{{ $k->fecha->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-medium">{{ $k->item?->codigo }}</p>
                                    <p class="text-xs text-gray-400">{{ Str::limit($k->item?->nombre, 30) }}</p>
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $k->almacen?->nombre }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-xs font-mono px-1.5 py-0.5 rounded {{ ($k->es_inicial ?? false) ? '' : 'bg-gray-100' }}"
                                          @if($k->es_inicial ?? false) style="background-color:#fef3c7;color:#92400e;font-style:normal" @endif>{{ $k->tipo_movimiento }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-400">{{ $k->documento_origen ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $k->descripcion ?? '—' }}</td>
                                <td class="px-3 py-2 text-right {{ $k->entrada_cantidad > 0 ? 'text-green-700 font-medium' : 'text-gray-300' }}">
                                    {{ $k->entrada_cantidad > 0 ? number_format((float)$k->entrada_cantidad, 2) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right {{ $k->entrada_cantidad > 0 ? 'text-gray-600' : 'text-gray-300' }}">
                                    {{ $k->entrada_cantidad > 0 ? number_format((float)$k->costo_entrada, 4) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right {{ $k->salida_cantidad > 0 ? 'text-red-600 font-medium' : 'text-gray-300' }}">
                                    {{ $k->salida_cantidad > 0 ? number_format((float)$k->salida_cantidad, 2) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right {{ $k->salida_cantidad > 0 ? 'text-gray-600' : 'text-gray-300' }}">
                                    {{ $k->salida_cantidad > 0 ? number_format((float)$k->costo_salida, 4) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right font-bold">{{ number_format((float)$k->saldo_cantidad, 2) }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-gray-700">{{ number_format((float)$k->saldo_costo, 2) }}</td>
                                <td class="px-3 py-2 text-right text-gray-500">{{ number_format((float)$k->costo_promedio, 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-4 py-8 text-center text-gray-400">Sin movimientos en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $kardex->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
