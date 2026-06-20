<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo movimiento de inventario</h2>
            <a href="{{ route('admin.inventario.movimientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.inventario.movimientos.store') }}" x-data="invForm()">
                @csrf
                <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Almacén <span class="text-red-500">*</span></label>
                            <select name="almacen_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                                <option value="">Seleccionar…</option>
                                @foreach ($almacenes as $alm)
                                    <option value="{{ $alm->id }}" @selected(old('almacen_id') == $alm->id)>{{ $alm->codigo }} — {{ $alm->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha <span class="text-red-500">*</span></label>
                            <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo <span class="text-red-500">*</span></label>
                            <select name="tipo_movimiento" x-model="tipo"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                                <option value="ENTRADA" @selected(old('tipo_movimiento', 'ENTRADA') === 'ENTRADA')>Entrada</option>
                                <option value="SALIDA" @selected(old('tipo_movimiento') === 'SALIDA')>Salida</option>
                                <option value="AJUSTE" @selected(old('tipo_movimiento') === 'AJUSTE')>Ajuste de inventario</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descripción</label>
                        <input type="text" name="descripcion" value="{{ old('descripcion') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                    </div>

                    {{-- Cuenta contrapartida: solo para ENTRADA (proveedor, banco, etc.) --}}
                    <div x-show="tipo === 'ENTRADA'" x-cloak>
                        <label class="block text-sm font-medium text-gray-700">Cuenta contrapartida <span class="text-red-500">*</span>
                            <span class="font-normal text-gray-400">(cuenta que se acredita al recibir el inventario)</span>
                        </label>
                        <select name="cuenta_contrapartida_id" :required="tipo === 'ENTRADA'"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                            <option value="">Seleccionar cuenta…</option>
                            @foreach ($cuentasContables as $c)
                                <option value="{{ $c->id }}" @selected(old('cuenta_contrapartida_id') == $c->id)>{{ $c->codigo }} — {{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        @error('cuenta_contrapartida_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Líneas --}}
                <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-3 mt-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Detalle</h3>
                        <button type="button" @click="agregarLinea()" class="text-sm text-blue-600 hover:underline">+ Agregar línea</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Producto</th>
                                    <th class="px-3 py-2 text-right w-28">Cantidad</th>
                                    <th class="px-3 py-2 text-right w-28">Costo unitario</th>
                                    <th class="px-3 py-2 text-right w-28">Total</th>
                                    <th class="px-3 py-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(linea, i) in lineas" :key="i">
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2">
                                            <select :name="`lineas[${i}][item_id]`" x-model="linea.item_id" @change="cargarCosto(linea)" class="w-full rounded border-gray-300 text-sm focus:ring-blue-500" required>
                                                <option value="">Seleccionar…</option>
                                                @foreach ($items as $it)
                                                    <option value="{{ $it->id }}" data-costo="{{ $it->costo }}">{{ $it->codigo }} — {{ $it->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" :name="`lineas[${i}][cantidad]`" x-model.number="linea.cantidad"
                                                @input="calcularLinea(linea)" step="0.0001" min="0.0001"
                                                class="w-full rounded border-gray-300 text-sm text-right focus:ring-blue-500" required>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" :name="`lineas[${i}][costo_unitario]`" x-model.number="linea.costo"
                                                @input="calcularLinea(linea)" step="0.0001" min="0"
                                                class="w-full rounded border-gray-300 text-sm text-right focus:ring-blue-500" required>
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium">B/. <span x-text="linea.total.toFixed(2)"></span></td>
                                        <td class="px-3 py-2 text-center">
                                            <button type="button" @click="lineas.splice(i, 1)" class="text-red-400 hover:text-red-600 text-xs" :disabled="lineas.length === 1">✕</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="border-t-2 border-gray-200 font-semibold text-sm">
                                <tr>
                                    <td colspan="3" class="px-3 py-2 text-right text-gray-700">Total</td>
                                    <td class="px-3 py-2 text-right">B/. <span x-text="totalGeneral.toFixed(2)"></span></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800">Registrar movimiento</button>
                    <a href="{{ route('admin.inventario.movimientos.index') }}" class="rounded-md border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<script>
const costosPorItem = {
    @foreach ($items as $it)
    {{ $it->id }}: {{ (float) $it->costo }},
    @endforeach
};

function invForm() {
    return {
        tipo: '{{ old('tipo_movimiento', 'ENTRADA') }}',
        lineas: [{ item_id: '', cantidad: 1, costo: 0, total: 0 }],
        get totalGeneral() { return this.lineas.reduce((s, l) => s + l.total, 0); },
        agregarLinea() { this.lineas.push({ item_id: '', cantidad: 1, costo: 0, total: 0 }); },
        cargarCosto(linea) {
            linea.costo = costosPorItem[linea.item_id] || 0;
            this.calcularLinea(linea);
        },
        calcularLinea(linea) {
            linea.total = Math.round(linea.cantidad * linea.costo * 100) / 100;
        },
    };
}
</script>
