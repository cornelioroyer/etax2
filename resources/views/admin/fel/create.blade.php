@php
    $doc       = $documento ?? null;
    $brd       = $borrador ?? [];
    $esEdicion = (bool) $doc;
    // Valores precargados (edición) con prioridad a old() tras un error de validación
    $vTipo   = old('tipo_documento', $brd['tipo_documento'] ?? '01');
    $vCliente = old('cliente_id', $doc->cliente_id ?? null);
    $vForma  = old('forma_pago', $brd['forma_pago'] ?? '02');
    $vObs    = old('informacion_interes', $brd['informacion_interes'] ?? '');
    $vItems  = old('items', $brd['items'] ?? [['descripcion' => '', 'cantidad' => 1, 'precio' => 0, 'tasa' => '01']]);
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $esEdicion ? 'Editar borrador' : 'Nueva factura electrónica' }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if (! $config || ! $config->activa)
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    FEL no está configurado para {{ $compania->nombre }}.
                    <a class="font-semibold underline" href="{{ route('admin.fel.configuracion') }}">Configurar tokens</a>
                </div>
            @elseif ($config->ambiente === 'PRUEBAS')
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    Ambiente <strong>PRUEBAS</strong> — la factura va al servidor demo del PAC, sin validez fiscal.
                </div>
            @endif

            <form method="POST" action="{{ $esEdicion ? route('admin.fel.update', $doc) : route('admin.fel.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-5"
                  x-data="facturaFel()"
                  @contacto-seleccionado="aplicarFormaPago($event.detail?.id)">
                @csrf
                @if ($esEdicion) @method('PUT') @endif

                <div>
                    <x-input-label for="tipo_documento" value="Tipo de documento" />
                    <select id="tipo_documento" name="tipo_documento" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-1/2">
                        @foreach ($tiposDocumento as $codigo => $nombre)
                            <option value="{{ $codigo }}" @selected($vTipo === $codigo)>{{ $codigo }} — {{ $nombre }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Las notas genéricas (06/07) no referencian un documento original.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-buscador-contacto
                            name="cliente_id"
                            label="Cliente"
                            :opciones="$clientes"
                            :selected="$vCliente"
                            placeholder="Buscar cliente por código, nombre o RUC…"
                            empty-label="Consumidor final (sin RUC)"
                            :mostrar-ruc="true" />
                        <p class="mt-1 text-xs text-gray-500">Los clientes con RUC se facturan como contribuyentes; sin RUC, como consumidor final.</p>
                    </div>
                    <div>
                        <x-input-label for="forma_pago" value="Forma de pago" />
                        <select id="forma_pago" name="forma_pago" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($formasPago as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected($vForma === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label for="informacion_interes" value="Observaciones (opcional)" />
                    <x-text-input id="informacion_interes" name="informacion_interes" type="text" class="mt-1 block w-full" :value="$vObs" />
                </div>

                {{-- Ítems --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Ítems</h3>
                        <button type="button" @click="agregar()" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">+ Agregar línea</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="py-2 pr-2">Descripción</th>
                                    <th class="w-24 py-2 pr-2">Cantidad</th>
                                    <th class="w-32 py-2 pr-2">Precio unit.</th>
                                    <th class="w-36 py-2 pr-2">ITBMS</th>
                                    <th class="w-28 py-2 pr-2 text-right">Total</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="idx">
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-2">
                                            <input type="text" :name="`items[${idx}][descripcion]`" x-model="item.descripcion" required
                                                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input type="number" step="0.01" min="0.01" :name="`items[${idx}][cantidad]`" x-model.number="item.cantidad" required
                                                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input type="number" step="0.01" min="0" :name="`items[${idx}][precio]`" x-model.number="item.precio" required
                                                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="py-2 pr-2">
                                            <select :name="`items[${idx}][tasa]`" x-model="item.tasa"
                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($tasas as $codigo => $nombre)
                                                    <option value="{{ $codigo }}">{{ $nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-2 pr-2 text-right font-medium" x-text="totalLinea(item)"></td>
                                        <td class="py-2 text-right">
                                            <button type="button" @click="items.splice(idx, 1)" x-show="items.length > 1" class="text-red-500 hover:text-red-700">✕</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Totales --}}
                <div class="flex justify-end">
                    <div class="w-64 space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span x-text="fmt(subtotal())"></span></div>
                        <div class="flex justify-between"><span class="text-gray-600">ITBMS</span><span x-text="fmt(itbms())"></span></div>
                        <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold"><span>Total B/.</span><span x-text="fmt(subtotal() + itbms())"></span></div>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                    <button type="submit" name="accion" value="emitir"
                            class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        Emitir factura
                    </button>
                    <button type="submit" name="accion" value="borrador"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ $esEdicion ? 'Guardar cambios' : 'Guardar borrador' }}
                    </button>
                    <a href="{{ route('admin.fel.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function facturaFel() {
            const tasas = { '00': 0, '01': 0.07, '02': 0.10, '03': 0.15 };
            // id de cliente → código FEL de forma de pago (para autoseleccionar al elegir cliente)
            const formaPagoCliente = @json($clientesFormaPago);
            const precargados = @json(array_values($vItems));
            const items = (precargados && precargados.length)
                ? precargados.map(i => ({
                    descripcion: i.descripcion ?? '',
                    cantidad: Number(i.cantidad ?? 1),
                    precio: Number(i.precio ?? 0),
                    tasa: String(i.tasa ?? '01'),
                  }))
                : [{ descripcion: '', cantidad: 1, precio: 0, tasa: '01' }];
            return {
                items,
                agregar() { this.items.push({ descripcion: '', cantidad: 1, precio: 0, tasa: '01' }); },
                aplicarFormaPago(clienteId) {
                    const fp = clienteId ? formaPagoCliente[clienteId] : '';
                    if (fp) { const el = document.getElementById('forma_pago'); if (el) el.value = fp; }
                },
                neto(i) { return Math.round((i.cantidad || 0) * (i.precio || 0) * 100) / 100; },
                imp(i) { return Math.round(this.neto(i) * tasas[i.tasa] * 100) / 100; },
                totalLinea(i) { return this.fmt(this.neto(i) + this.imp(i)); },
                subtotal() { return this.items.reduce((s, i) => s + this.neto(i), 0); },
                itbms() { return this.items.reduce((s, i) => s + this.imp(i), 0); },
                fmt(v) { return (Math.round(v * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
