<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva nota de crédito de venta</h2>
            <a href="{{ route('admin.ventas.notas-credito.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.ventas.notas-credito.store') }}">
                @csrf
                <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-buscador-contacto name="cliente_id" label="Cliente *" submit-on-select
                                placeholder="Seleccionar cliente…"
                                :opciones="$clientes" :selected="old('cliente_id', $clienteId)" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha <span class="text-red-500">*</span></label>
                            <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Motivo / Descripción <span class="text-red-500">*</span></label>
                        <textarea name="motivo" rows="2" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">{{ old('motivo') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Monto total (B/.) <span class="text-red-500">*</span></label>
                            <input type="number" name="total" value="{{ old('total') }}" step="0.01" min="0.01"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Factura a aplicar (opcional)</label>
                            {{-- Al elegir factura se recarga por GET (no envía el form) para
                                 cargar sus productos devolvibles con su costo de salida. --}}
                            <select name="factura_id"
                                onchange="window.location.href='{{ route('admin.ventas.notas-credito.create') }}?cliente_id={{ $clienteId }}&factura_id='+this.value"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                <option value="">— No aplicar a factura específica —</option>
                                @foreach ($facturas as $f)
                                    <option value="{{ $f->id }}" @selected(old('factura_id', $facturaId) == $f->id)>
                                        {{ $f->numero }} — B/. {{ number_format((float) $f->saldo, 2) }} saldo
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Devolución de mercancía a inventario (reingresa al costo de salida) --}}
                    @if (! empty($devolvibles))
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4 space-y-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">Devolución de mercancía a inventario</h3>
                                <p class="text-xs text-gray-500">Indica cuánto devolver por producto. Se reingresa al inventario al <strong>mismo costo con que salió</strong>; el asiento agrega Dr Inventario / Cr Costo de Ventas y el lado de ingreso usa la cuenta de Devoluciones. Déjalo en 0 si la NC no es por devolución de mercancía.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                            <th class="py-1 pr-4">Producto</th>
                                            <th class="py-1 pr-4 text-right">Vendido</th>
                                            <th class="py-1 pr-4 text-right">Ya devuelto</th>
                                            <th class="py-1 pr-4 text-right">Disponible</th>
                                            <th class="py-1 pr-4 text-right">Costo salida</th>
                                            <th class="py-1 pr-4 text-right">Devolver</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($devolvibles as $d)
                                            <tr class="border-t border-gray-200">
                                                <td class="py-1.5 pr-4 text-gray-800">{{ $d['codigo'] ? $d['codigo'].' — ' : '' }}{{ $d['nombre'] }}</td>
                                                <td class="py-1.5 pr-4 text-right text-gray-600">{{ rtrim(rtrim(number_format($d['vendida'], 4), '0'), '.') }}</td>
                                                <td class="py-1.5 pr-4 text-right text-gray-600">{{ rtrim(rtrim(number_format($d['devuelta'], 4), '0'), '.') }}</td>
                                                <td class="py-1.5 pr-4 text-right font-medium text-gray-800">{{ rtrim(rtrim(number_format($d['disponible'], 4), '0'), '.') }}</td>
                                                <td class="py-1.5 pr-4 text-right text-gray-600">B/. {{ number_format($d['costo_unitario'], 2) }}</td>
                                                <td class="py-1.5 pr-4 text-right">
                                                    <input type="number" name="devolucion[{{ $d['item_id'] }}]" step="0.0001" min="0"
                                                           max="{{ $d['disponible'] }}" value="{{ old('devolucion.'.$d['item_id'], 0) }}"
                                                           class="w-24 rounded-md border-gray-300 text-right text-sm">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif ($facturaId)
                        <p class="text-xs text-gray-500">La factura seleccionada no tiene mercancía pendiente de devolver a inventario.</p>
                    @endif

                    <div>
                        <x-buscador-contacto name="cuenta_id" label="Cuenta de ventas (contrapartida) *" required
                            :opciones="$cuentasVenta" :selected="old('cuenta_id', $cuentaVentaId)"
                            placeholder="Buscar cuenta por código o nombre" />
                    </div>
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800">Emitir nota de crédito</button>
                    <a href="{{ route('admin.ventas.notas-credito.index') }}" class="rounded-md border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
