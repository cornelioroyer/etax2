{{-- Requiere: $cuentas; opcional: $asiento (edición). El form padre define action. --}}
@php
    $lineasIniciales = old('lineas')
        ? collect(old('lineas'))->values()->map(fn ($l) => [
            'cuenta_id' => $l['cuenta_id'] ?? '',
            'descripcion' => $l['descripcion'] ?? '',
            'debito' => (float) ($l['debito'] ?? 0),
            'credito' => (float) ($l['credito'] ?? 0),
        ])
        : (isset($asiento)
            ? $asiento->detalle->map(fn ($l) => [
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

<div x-data="asientoForm({{ $lineasIniciales->toJson() }})" class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <x-input-label for="fecha" value="Fecha *" />
            <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                          :value="old('fecha', isset($asiento) ? $asiento->fecha->format('Y-m-d') : now()->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
        </div>
        <div class="sm:col-span-2">
            <x-input-label for="descripcion" value="Descripción" />
            <x-text-input id="descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                          :value="old('descripcion', $asiento->descripcion ?? '')" placeholder="Concepto del asiento" />
        </div>
        <div>
            <x-input-label for="referencia" value="Referencia" />
            <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                          :value="old('referencia', $asiento->referencia ?? '')" placeholder="Documento, cheque, factura…" />
        </div>
    </div>

    {{-- Líneas --}}
    <div>
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Líneas del asiento</h3>
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

    @if ($errors->has('confirmar_control'))
        <label class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <input type="checkbox" name="confirmar_control" value="1" class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
            <span>Confirmo afectar cuentas controladas por auxiliar (CxC / CxP / Inventario). Entiendo que postear directo puede descuadrar el auxiliar; lo correcto suele ser registrarlo desde su módulo.</span>
        </label>
    @endif

    <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
        <button type="submit" name="accion" value="postear"
                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
                :disabled="! cuadrado()">
            Guardar y postear
        </button>
        <button type="submit" name="accion" value="borrador"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Guardar borrador
        </button>
        <a href="{{ route('admin.asientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto" x-show="! cuadrado()">El asiento debe cuadrar (débito = crédito) para postear.</p>
    </div>
</div>

<script>
    function asientoForm(lineasIniciales) {
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
