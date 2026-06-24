<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cotizaciones de venta</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.ventas.cotizaciones.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.ventas.cotizaciones.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                @can('ventas.gestionar')
                    <a href="{{ route('admin.ventas.cotizaciones.create') }}"
                       class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        + Nueva cotización
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-6">
                    <x-buscador-contacto name="cliente_id" label="Cliente" :opciones="$clientes" :selected="$filtros['cliente_id'] ?? null" class="col-span-2" />
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach (['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'FACTURADA', 'ANULADA'] as $estado)
                                <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ ucfirst(strtolower($estado)) }}</option>
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
                    <a href="{{ route('admin.ventas.cotizaciones.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
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
                                <th class="px-4 py-3 hidden md:table-cell">Válida hasta</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 hidden md:table-cell">Factura</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($cotizaciones as $cotizacion)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('admin.ventas.cotizaciones.show', $cotizacion) }}" class="text-blue-700 hover:underline">{{ $cotizacion->numero }}</a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $cotizacion->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 max-w-xs truncate">{{ $cotizacion->cliente->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap hidden md:table-cell">{{ $cotizacion->valida_hasta?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap font-medium">B/. {{ number_format((float) $cotizacion->total, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @include('admin.ventas.cotizaciones._estado', ['estado' => $cotizacion->estado])
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell">
                                        @if ($cotizacion->factura_id)
                                            <a href="{{ route('admin.cxc.facturas.show', $cotizacion->factura_id) }}" class="text-blue-700 hover:underline">{{ $cotizacion->factura->numero ?? 'Ver' }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                        No hay cotizaciones que coincidan con el filtro.
                                        @can('ventas.gestionar')
                                            <a href="{{ route('admin.ventas.cotizaciones.create') }}" class="text-blue-700 underline">Crear la primera</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($cotizaciones->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $cotizaciones->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
