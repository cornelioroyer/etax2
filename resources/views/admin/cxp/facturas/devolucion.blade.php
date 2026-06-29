<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Devolver al proveedor — factura {{ $factura->numero }}
            </h2>
            <a href="{{ route('admin.cxp.facturas.show', $factura) }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg text-sm text-gray-600">
                <p>
                    Proveedor: <span class="font-medium text-gray-800">{{ $factura->proveedor->nombre ?? '—' }}</span> ·
                    Saldo de la factura: <span class="font-medium text-gray-800">B/. {{ number_format((float) $factura->saldo, 2) }}</span> ·
                    Almacén: <span class="font-medium text-gray-800">{{ $almacen->nombre ?? '—' }}</span>
                </p>
                <p class="mt-2 text-gray-500">
                    Se generará una <strong>nota de crédito</strong> aplicada a esta factura (reduce el saldo por pagar) y se
                    descontará el inventario devuelto. El original queda en el historial.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.cxp.facturas.devolucion.store', $factura) }}" class="space-y-4">
                @csrf

                <div class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Fecha de la devolución</label>
                        <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" required
                               class="rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3 text-right">Comprado</th>
                                <th class="px-4 py-3 text-right">En stock</th>
                                <th class="px-4 py-3 text-right">Costo compra</th>
                                <th class="px-4 py-3 text-right">Máx. a devolver</th>
                                <th class="px-4 py-3 text-right">Devolver</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($lineas as $i => $l)
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-xs text-gray-500 mr-2">{{ $l['codigo'] }}</span>
                                        {{ $l['nombre'] }}
                                        @if ($l['descripcion'])<div class="text-xs text-gray-400">{{ $l['descripcion'] }}</div>@endif
                                        <input type="hidden" name="lineas[{{ $i }}][detalle_id]" value="{{ $l['detalle_id'] }}">
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format($l['cantidad'], 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format($l['disponible'], 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format($l['costo_compra'], 4) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ rtrim(rtrim(number_format($l['max_devolver'], 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <input type="number" step="0.0001" min="0" max="{{ $l['max_devolver'] }}"
                                               name="lineas[{{ $i }}][cantidad]" value="{{ old('lineas.'.$i.'.cantidad', 0) }}"
                                               class="w-24 rounded-md border-gray-300 text-sm text-right shadow-sm">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.cxp.facturas.show', $factura) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    <button type="submit"
                            onsubmit="return confirm('¿Registrar la devolución?');"
                            class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        Registrar devolución
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
