{{-- Requiere: $cuentas, $frecuencias; opcional: $plantilla (edición). El form padre define action/método. --}}
@php
    $lineasIniciales = old('lineas')
        ? collect(old('lineas'))->values()->map(fn ($l) => [
            'cuenta_id' => $l['cuenta_id'] ?? '',
            'descripcion' => $l['descripcion'] ?? '',
            'debito' => (float) ($l['debito'] ?? 0),
            'credito' => (float) ($l['credito'] ?? 0),
        ])
        : (isset($plantilla)
            ? $plantilla->detalle->map(fn ($l) => [
                'cuenta_id' => $l->cuenta_id,
                'descripcion' => $l->descripcion ?? '',
                'debito' => (float) $l->debito,
                'credito' => (float) $l->credito,
            ])->values()
            : collect([
                ['cuenta_id' => '', 'descripcion' => '', 'debito' => 0, 'credito' => 0],
                ['cuenta_id' => '', 'descripcion' => '', 'debito' => 0, 'credito' => 0],
            ]));
@endphp

<div x-data="recurrenteForm({{ $lineasIniciales->toJson() }})" class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <x-input-label for="nombre" value="Nombre de la plantilla *" />
            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" required
                          :value="old('nombre', $plantilla->nombre ?? '')" placeholder="Alquiler local, Depreciación equipo, Seguro…" />
            <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="referencia" value="Referencia" />
            <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                          :value="old('referencia', $plantilla->referencia ?? '')" placeholder="Contrato, póliza…" />
        </div>
        <div class="sm:col-span-3">
            <x-input-label for="descripcion" value="Descripción (será el concepto de cada asiento)" />
            <x-text-input id="descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                          :value="old('descripcion', $plantilla->descripcion ?? '')" placeholder="Concepto del asiento generado" />
        </div>
    </div>

    {{-- Periodicidad --}}
    <div class="rounded-md bg-gray-50 p-4">
        <h3 class="mb-3 text-sm font-semibold text-gray-700">Periodicidad</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
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
                <x-input-label for="fecha_fin" value="Hasta (opcional)" />
                <x-text-input id="fecha_fin" name="fecha_fin" type="text" class="js-date mt-1 block w-full"
                              :value="old('fecha_fin', isset($plantilla) && $plantilla->fecha_fin ? $plantilla->fecha_fin->format('Y-m-d') : '')" />
                <x-input-error :messages="$errors->get('fecha_fin')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="ocurrencias_max" value="Nº máx. de veces (opcional)" />
                <x-text-input id="ocurrencias_max" name="ocurrencias_max" type="number" min="1" step="1" class="mt-1 block w-full"
                              :value="old('ocurrencias_max', $plantilla->ocurrencias_max ?? '')" placeholder="Ej. 12" />
                <x-input-error :messages="$errors->get('ocurrencias_max')" class="mt-1" />
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">Se generan asientos en <strong>borrador</strong> en cada vencimiento (a diario, automáticamente) para que los revises y postees. Termina al llegar a la fecha tope o al número de veces.</p>
    </div>

    {{-- Líneas --}}
    <div>
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Líneas de la plantilla</h3>
            <button type="button" @click="agregar()" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">+ Agregar línea</button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2 pr-2 min-w-[16rem]">Cuenta</th>
                        <th class="py-2 pr-2">Descripción</th>
                        <th class="w-36 py-2 pr-2 text-right">Débito</th>
                        <th class="w-36 py-2 pr-2 text-right">Crédito</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(linea, idx) in lineas" :key="idx">
                        <tr class="border-t border-gray-100 align-top">
                            <td class="py-2 pr-2">
                                <select :name="`lineas[${idx}][cuenta_id]`" x-model="linea.cuenta_id" required
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Cuenta —</option>
                                    @foreach ($cuentas as $cuenta)
                                        <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pr-2">
                                <input type="text" :name="`lineas[${idx}][descripcion]`" x-model="linea.descripcion"
                                       class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="py-2 pr-2">
                                <input type="number" step="0.01" min="0" :name="`lineas[${idx}][debito]`" x-model.number="linea.debito"
                                       @input="if (linea.debito > 0) linea.credito = 0"
                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="py-2 pr-2">
                                <input type="number" step="0.01" min="0" :name="`lineas[${idx}][credito]`" x-model.number="linea.credito"
                                       @input="if (linea.credito > 0) linea.debito = 0"
                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="py-2 text-right">
                                <button type="button" @click="lineas.splice(idx, 1)" x-show="lineas.length > 2"
                                        class="mt-2 text-red-500 hover:text-red-700">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="border-t-2 border-gray-200 text-sm font-semibold">
                    <tr>
                        <td colspan="2" class="py-2 pr-2 text-right text-gray-600">Totales</td>
                        <td class="py-2 pr-2 text-right" x-text="fmt(totalDebito())"></td>
                        <td class="py-2 pr-2 text-right" x-text="fmt(totalCredito())"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="py-1 pr-2 text-right text-gray-600">Diferencia</td>
                        <td colspan="2" class="py-1 pr-2 text-right"
                            :class="cuadrado() ? 'text-green-600' : 'text-red-600'"
                            x-text="cuadrado() ? 'Cuadrado ✓' : fmt(totalDebito() - totalCredito())"></td>
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
                :disabled="! cuadrado()">
            {{ isset($plantilla) ? 'Guardar cambios' : 'Crear plantilla' }}
        </button>
        <a href="{{ isset($plantilla) ? route('admin.asientos-recurrentes.show', $plantilla) : route('admin.asientos-recurrentes.index') }}"
           class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto" x-show="! cuadrado()">La plantilla debe cuadrar (débito = crédito).</p>
    </div>
</div>

<script>
    function recurrenteForm(lineasIniciales) {
        return {
            lineas: lineasIniciales.length ? lineasIniciales : [
                { cuenta_id: '', descripcion: '', debito: 0, credito: 0 },
                { cuenta_id: '', descripcion: '', debito: 0, credito: 0 },
            ],
            agregar() { this.lineas.push({ cuenta_id: '', descripcion: '', debito: 0, credito: 0 }); },
            totalDebito() { return this.lineas.reduce((s, l) => s + (parseFloat(l.debito) || 0), 0); },
            totalCredito() { return this.lineas.reduce((s, l) => s + (parseFloat(l.credito) || 0), 0); },
            cuadrado() {
                const d = Math.round(this.totalDebito() * 100);
                const c = Math.round(this.totalCredito() * 100);
                return d === c && d > 0;
            },
            fmt(v) { return 'B/. ' + (Math.round(v * 100) / 100).toFixed(2); },
        };
    }
</script>
