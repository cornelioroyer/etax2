<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo recibo de cobro</h2>
            <a href="{{ route('admin.ventas.recibos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Paso 1: seleccionar cliente --}}
            @if (! $clienteId)
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Seleccionar cliente</h3>
                    <form method="GET" class="flex gap-3 items-end">
                        <div class="flex-1">
                            <x-buscador-contacto name="cliente_id" label="Cliente" required
                                placeholder="Seleccionar cliente…"
                                :opciones="$clientes" :selected="null" />
                        </div>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Cargar facturas</button>
                    </form>
                </div>
            @else
                @php $cliente = $clientes->firstWhere('id', $clienteId); @endphp
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Cliente</p>
                            <p class="font-semibold">{{ $cliente?->nombre }}</p>
                        </div>
                        <a href="{{ route('admin.ventas.recibos.create') }}" class="text-xs text-blue-600 hover:underline">Cambiar cliente</a>
                    </div>
                </div>

                @if ($facturasPendientes->isEmpty())
                    <div class="bg-yellow-50 rounded-md p-4 text-sm text-yellow-800">Este cliente no tiene facturas pendientes de cobro.</div>
                @else
                    <form method="POST" action="{{ route('admin.ventas.recibos.store') }}" x-data="reciboForm()">
                        @csrf
                        <input type="hidden" name="cliente_id" value="{{ $clienteId }}">

                        <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Fecha <span class="text-red-500">*</span></label>
                                    <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Método de pago</label>
                                    <select name="metodo_pago" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                        <option value="">—</option>
                                        <option value="EFECTIVO" @selected(old('metodo_pago') === 'EFECTIVO')>Efectivo</option>
                                        <option value="TRANSFERENCIA" @selected(old('metodo_pago') === 'TRANSFERENCIA')>Transferencia</option>
                                        <option value="CHEQUE" @selected(old('metodo_pago') === 'CHEQUE')>Cheque</option>
                                        <option value="TARJETA" @selected(old('metodo_pago') === 'TARJETA')>Tarjeta</option>
                                        <option value="YAPPY" @selected(old('metodo_pago') === 'YAPPY')>Yappy</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Referencia</label>
                                    <input type="text" name="referencia" value="{{ old('referencia') }}" placeholder="No. transferencia, cheque…"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                </div>
                            </div>
                            <div>
                                <x-buscador-contacto name="cuenta_cobro_id" label="Cuenta a acreditar *" required
                                    :opciones="$cuentasCobro" :selected="old('cuenta_cobro_id', $cuentaBancoId)"
                                    placeholder="Buscar cuenta por código o nombre" />
                            </div>
                        </div>

                        <div class="bg-white p-6 shadow-sm sm:rounded-lg mt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Facturas pendientes</h3>
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Factura</th>
                                        <th class="px-3 py-2 text-left">Fecha</th>
                                        <th class="px-3 py-2 text-right">Total</th>
                                        <th class="px-3 py-2 text-right">Saldo</th>
                                        <th class="px-3 py-2 text-right w-36">Monto a cobrar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($facturasPendientes as $i => $f)
                                        <input type="hidden" name="facturas[{{ $i }}][id]" value="{{ $f->id }}">
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-xs">{{ $f->numero }}</td>
                                            <td class="px-3 py-2">{{ $f->fecha->format('d/m/Y') }}</td>
                                            <td class="px-3 py-2 text-right">B/. {{ number_format((float) $f->total, 2) }}</td>
                                            <td class="px-3 py-2 text-right font-medium text-blue-700">B/. {{ number_format((float) $f->saldo, 2) }}</td>
                                            <td class="px-3 py-2">
                                                <input type="number"
                                                    name="facturas[{{ $i }}][monto]"
                                                    value="{{ old("facturas.{$i}.monto", 0) }}"
                                                    step="0.01" min="0" max="{{ $f->saldo }}"
                                                    @input="calcularTotal()"
                                                    class="w-full rounded border-gray-300 text-sm text-right focus:ring-blue-500 cobro-monto">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t-2 border-gray-200 font-semibold">
                                    <tr>
                                        <td colspan="4" class="px-3 py-2 text-right text-gray-700">Total a cobrar</td>
                                        <td class="px-3 py-2 text-right">B/. <span x-text="total.toFixed(2)">0.00</span></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="flex gap-3 mt-4">
                            <button type="submit" class="rounded-md bg-[#0d2d5e] px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800">Registrar cobro</button>
                            <a href="{{ route('admin.ventas.recibos.index') }}" class="rounded-md border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>

<script>
function reciboForm() {
    return {
        total: 0,
        calcularTotal() {
            const inputs = document.querySelectorAll('.cobro-monto');
            this.total = Array.from(inputs).reduce((s, i) => s + Math.round(parseFloat(i.value || 0) * 100) / 100, 0);
        },
    };
}
</script>
