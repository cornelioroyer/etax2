{{-- Requiere: $proveedores, $cuentas, $frecuencias, $tasasItbms; opcional: $plantilla (edición). El form padre define action/método. --}}
@php
    $lineasIniciales = old('lineas')
        ? collect(old('lineas'))->values()->map(fn ($l) => [
            'descripcion' => $l['descripcion'] ?? '',
            'cantidad' => (float) ($l['cantidad'] ?? 1),
            'precio_unitario' => (float) ($l['precio_unitario'] ?? 0),
            'tasa_itbms' => (int) ($l['tasa_itbms'] ?? 0),
            'cuenta_id' => $l['cuenta_id'] ?? '',
        ])
        : (isset($plantilla)
            ? $plantilla->detalle->map(fn ($l) => [
                'descripcion' => $l->descripcion ?? '',
                'cantidad' => (float) $l->cantidad,
                'precio_unitario' => (float) $l->precio_unitario,
                'tasa_itbms' => (int) $l->tasa_itbms,
                'cuenta_id' => $l->cuenta_id,
            ])->values()
            : collect([
                ['descripcion' => '', 'cantidad' => 1, 'precio_unitario' => 0, 'tasa_itbms' => 0, 'cuenta_id' => ''],
            ]));
@endphp

<div x-data="cxpRecurrenteForm({{ $lineasIniciales->toJson() }})" class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <x-buscador-contacto name="proveedor_id" label="Proveedor *" required
                :opciones="$proveedores" :selected="old('proveedor_id', $plantilla->proveedor_id ?? null)"
                placeholder="— Proveedor — código o nombre" mostrar-ruc />
            <x-input-error :messages="$errors->get('proveedor_id')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="nombre" value="Nombre de la plantilla *" />
            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" required
                          :value="old('nombre', $plantilla->nombre ?? '')" placeholder="Alquiler local, Mantenimiento, Seguro…" />
            <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="referencia" value="Referencia" />
            <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                          :value="old('referencia', $plantilla->referencia ?? '')" placeholder="Contrato, póliza…" />
        </div>
    </div>

    {{-- Periodicidad --}}
    <div class="rounded-md bg-gray-50 p-4">
        <h3 class="mb-3 text-sm font-semibold text-gray-700">Periodicidad</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-5">
            <div>
                <x-input-label for="frecuencia" value="Frecuencia *" />
                <select id="frecuencia" name="frecuencia" required
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($frecuencias as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('frecuencia', $plantilla->frecuencia ?? 'MENSUAL') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('frecuencia')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="fecha_inicio" value="Primer vencimiento *" />
                <x-text-input id="fecha_inicio" name="fecha_inicio" type="text" class="js-date mt-1 block w-full" required
                              :value="old('fecha_inicio', isset($plantilla) ? $plantilla->fecha_inicio->format('Y-m-d') : now()->format('Y-m-d'))" />
                <x-input-error :messages="$errors->get('fecha_inicio')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="dias_credito" value="Días de crédito" />
                <x-text-input id="dias_credito" name="dias_credito" type="number" min="0" step="1" class="mt-1 block w-full"
                              :value="old('dias_credito', $plantilla->dias_credito ?? 0)" placeholder="0" />
                <x-input-error :messages="$errors->get('dias_credito')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="fecha_fin" value="Hasta (opcional)" />
                <x-text-input id="fecha_fin" name="fecha_fin" type="text" class="js-date mt-1 block w-full"
                              :value="old('fecha_fin', isset($plantilla) && $plantilla->fecha_fin ? $plantilla->fecha_fin->format('Y-m-d') : '')" />
                <x-input-error :messages="$errors->get('fecha_fin')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="ocurrencias_max" value="Nº máx. de veces" />
                <x-text-input id="ocurrencias_max" name="ocurrencias_max" type="number" min="1" step="1" class="mt-1 block w-full"
                              :value="old('ocurrencias_max', $plantilla->ocurrencias_max ?? '')" placeholder="Ej. 12" />
                <x-input-error :messages="$errors->get('ocurrencias_max')" class="mt-1" />
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">En cada vencimiento se genera una <strong>factura de compra en borrador</strong> (a diario, automáticamente). El vencimiento de cada factura = fecha + días de crédito. La revisas en <strong>Facturas de Compras</strong> y la contabilizas (Dr gasto + Dr ITBMS / Cr Cuentas por Pagar).</p>
    </div>

    {{-- Líneas --}}
    <div>
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Líneas de la factura</h3>
            <button type="button" @click="agregar()" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">+ Agregar línea</button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2 pr-2">Descripción</th>
                        <th class="w-24 py-2 pr-2 text-right">Cantidad</th>
                        <th class="w-32 py-2 pr-2 text-right">Precio</th>
                        <th class="w-24 py-2 pr-2 text-right">ITBMS %</th>
                        <th class="py-2 pr-2 min-w-[14rem]">Cuenta de gasto/costo</th>
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
                                <input type="number" step="0.0001" min="0" :name="`lineas[${idx}][cantidad]`" x-model.number="linea.cantidad"
                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="py-2 pr-2">
                                <input type="number" step="0.0001" min="0" :name="`lineas[${idx}][precio_unitario]`" x-model.number="linea.precio_unitario"
                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="py-2 pr-2">
                                <select :name="`lineas[${idx}][tasa_itbms]`" x-model.number="linea.tasa_itbms"
                                        class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($tasasItbms as $t)
                                        <option value="{{ $t }}">{{ $t }}%</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pr-2">
                                @include('admin.partials.cuenta-combo', [
                                    'cuentas' => $cuentas,
                                    'nameExpr' => '`lineas[${idx}][cuenta_id]`',
                                    'required' => true,
                                    'emptyLabel' => '— Cuenta —',
                                ])
                            </td>
                            <td class="py-2 pr-2 text-right text-gray-700" x-text="fmt(totalLinea(linea))"></td>
                            <td class="py-2 text-right">
                                <button type="button" @click="lineas.splice(idx, 1)" x-show="lineas.length > 1"
                                        class="mt-2 text-red-500 hover:text-red-700">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="border-t-2 border-gray-200 text-sm font-semibold">
                    <tr>
                        <td colspan="5" class="py-2 pr-2 text-right text-gray-600">Subtotal</td>
                        <td class="py-2 pr-2 text-right" x-text="fmt(subtotal())"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="py-1 pr-2 text-right text-gray-600">ITBMS</td>
                        <td class="py-1 pr-2 text-right" x-text="fmt(impuesto())"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="py-1 pr-2 text-right text-gray-700">Total</td>
                        <td class="py-1 pr-2 text-right text-gray-900" x-text="fmt(total())"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <x-input-error :messages="$errors->get('lineas')" class="mt-1" />
    </div>

    <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
        <button type="submit"
                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
                :disabled="total() <= 0">
            {{ isset($plantilla) ? 'Guardar cambios' : 'Crear plantilla' }}
        </button>
        <a href="{{ isset($plantilla) ? route('admin.cxp.recurrentes.show', $plantilla) : route('admin.cxp.recurrentes.index') }}"
           class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto" x-show="total() <= 0">El total de la plantilla debe ser mayor que cero.</p>
    </div>
</div>

<script>
    function cxpRecurrenteForm(lineasIniciales) {
        const nueva = () => ({ descripcion: '', cantidad: 1, precio_unitario: 0, tasa_itbms: 0, cuenta_id: '' });
        return {
            lineas: lineasIniciales.length ? lineasIniciales : [nueva()],
            agregar() { this.lineas.push(nueva()); },
            base(l) { return (parseFloat(l.cantidad) || 0) * (parseFloat(l.precio_unitario) || 0); },
            totalLinea(l) { const b = this.base(l); return b + b * (parseInt(l.tasa_itbms) || 0) / 100; },
            subtotal() { return this.lineas.reduce((s, l) => s + this.base(l), 0); },
            impuesto() { return this.lineas.reduce((s, l) => s + this.base(l) * (parseInt(l.tasa_itbms) || 0) / 100, 0); },
            total() { return this.subtotal() + this.impuesto(); },
            fmt(v) { return 'B/. ' + (Math.round(v * 100) / 100).toFixed(2); },
        };
    }
</script>
