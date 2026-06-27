<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar cobro</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Paso 1: elegir cliente (recarga con sus facturas pendientes) --}}
            <form method="GET" action="{{ route('admin.cxc.cobros.create') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
                        <x-buscador-contacto name="cliente_id" label="Cliente *" submit-on-select
                            placeholder="— Selecciona el cliente —"
                            :opciones="$clientes" :selected="$clienteId" />
                    </div>
                    <p class="pb-2 text-xs text-gray-500">Al elegir el cliente se cargan sus facturas con saldo.</p>
                </div>
            </form>

            @if ($clienteId)
                @if ($facturas->isEmpty())
                    <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                        El cliente no tiene facturas con saldo pendiente.
                    </div>
                @else
                    @php($montosPrellenados = collect(old('aplicaciones', []))->keyBy('documento_id'))
                    <form method="POST" action="{{ route('admin.cxc.cobros.store') }}"
                          x-data="cobroCxc({{ $facturas->map(fn ($f) => ['id' => $f->id, 'numero' => $f->numero, 'fecha' => $f->fecha->format('d/m/Y'), 'total' => (float) $f->total, 'saldo' => (float) $f->saldo, 'monto' => (float) ($montosPrellenados[$f->id]['monto'] ?? 0)])->toJson() }})"
                          class="bg-white p-6 shadow-sm sm:rounded-lg">
                        @csrf
                        <input type="hidden" name="cliente_id" value="{{ $clienteId }}">

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="fecha" value="Fecha del cobro *" />
                                <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                              :value="old('fecha', now()->format('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                            </div>
                            <div>
                                <x-buscador-contacto name="cuenta_cobro_id" label="Depositar en (cuenta) *" required
                                    :opciones="$cuentasCobro" :selected="old('cuenta_cobro_id', $cuentaBancoId)"
                                    placeholder="Buscar cuenta por código o nombre" />
                                <x-input-error :messages="$errors->get('cuenta_cobro_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="referencia" value="Referencia" />
                                <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                                              :value="old('referencia')" placeholder="Transferencia, cheque, recibo…" />
                            </div>
                        </div>

                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="py-2 pr-2">Factura</th>
                                        <th class="py-2 pr-2">Fecha</th>
                                        <th class="py-2 pr-2 text-right">Total</th>
                                        <th class="py-2 pr-2 text-right">Saldo</th>
                                        <th class="w-40 py-2 pr-2 text-right">Monto a cobrar</th>
                                        <th class="w-20 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(factura, idx) in facturas" :key="factura.id">
                                        <tr class="border-t border-gray-100">
                                            <td class="py-2 pr-2 font-medium" x-text="factura.numero"></td>
                                            <td class="py-2 pr-2" x-text="factura.fecha"></td>
                                            <td class="py-2 pr-2 text-right" x-text="fmt(factura.total)"></td>
                                            <td class="py-2 pr-2 text-right" x-text="fmt(factura.saldo)"></td>
                                            <td class="py-2 pr-2">
                                                <input type="hidden" :name="`aplicaciones[${idx}][documento_id]`" :value="factura.id">
                                                <input type="number" step="0.01" min="0" :max="factura.saldo"
                                                       :name="`aplicaciones[${idx}][monto]`" x-model.number="factura.monto"
                                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                            <td class="py-2 text-right">
                                                <button type="button" class="text-xs text-blue-700 hover:underline" @click="factura.monto = factura.saldo">Todo</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="border-t-2 border-gray-200 font-semibold">
                                    <tr>
                                        <td colspan="4" class="py-2 pr-2 text-right text-gray-700">Total a cobrar</td>
                                        <td class="py-2 pr-2 text-right" x-text="fmt(total())"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('aplicaciones')" class="mt-1" />

                        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                            <button type="submit" :disabled="total() <= 0"
                                    class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                                Registrar cobro
                            </button>
                            <a href="{{ route('admin.cxc.cobros.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                            <p class="w-full text-xs text-gray-500 sm:w-auto sm:ml-auto">Se creará el asiento: débito a la cuenta de depósito, crédito a Cuentas por Cobrar.</p>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <script>
        function cobroCxc(facturas) {
            return {
                facturas,
                total() { return this.facturas.reduce((s, f) => s + (parseFloat(f.monto) || 0), 0); },
                fmt(v) { return 'B/. ' + (Math.round(v * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
