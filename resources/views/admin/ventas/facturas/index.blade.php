<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturas de venta</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.ventas.facturas.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.ventas.facturas.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">Saldo total por cobrar</p>
                <p class="text-2xl font-bold text-[#0d2d5e]">B/. {{ number_format($saldoTotal, 2) }}</p>
            </div>

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-6">
                    <div class="col-span-2">
                        <x-input-label for="q" value="Buscar" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$filtros['q'] ?? ''" placeholder="Número o cliente" />
                    </div>
                    <div>
                        <x-input-label for="cliente_id" value="Cliente" />
                        <select id="cliente_id" name="cliente_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach ($clientes as $cliente)
                                <option value="{{ $cliente->id }}" @selected(($filtros['cliente_id'] ?? null) == $cliente->id)>{{ $cliente->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach (['BORRADOR', 'EMITIDA', 'PARCIAL', 'PAGADA', 'ANULADA'] as $est)
                                <option value="{{ $est }}" @selected(($filtros['estado'] ?? '') === $est)>{{ ucfirst(strtolower($est)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="desde" value="Desde" />
                        <x-text-input id="desde" name="desde" type="text" class="js-date mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                    </div>
                    <div>
                        <x-input-label for="hasta" value="Hasta" />
                        <x-text-input id="hasta" name="hasta" type="text" class="js-date mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Filtrar</button>
                    <a href="{{ route('admin.ventas.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                </div>
            </form>

            {{-- Tabla --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Número</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3 hidden md:table-cell">Vence</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 hidden md:table-cell">FEL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($facturas as $factura)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('admin.ventas.facturas.show', $factura) }}" class="text-blue-700 hover:underline">{{ $factura->numero }}</a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $factura->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 max-w-xs truncate">{{ $factura->cliente->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap hidden md:table-cell">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $factura->total, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap font-medium">B/. {{ number_format((float) $factura->saldo, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @include('admin.ventas.facturas._estado', ['estado' => $factura->estado])
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell text-xs">
                                        @if ($factura->fel_documento_id)
                                            <span class="text-green-600 font-medium">Emitida</span>
                                        @else
                                            <span class="text-gray-400">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                        No hay facturas. Las facturas se generan al convertir una cotización.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($facturas->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $facturas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
