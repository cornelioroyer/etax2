<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pagos a proveedores</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cxp.pagos.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.cxp.pagos.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                @can('cxp.gestionar')
                    <a href="{{ route('admin.cxp.pagos.create') }}"
                       class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        + Registrar pago
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
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <x-input-label for="proveedor_id" value="Proveedor" />
                        <select id="proveedor_id" name="proveedor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach ($proveedores as $proveedor)
                                <option value="{{ $proveedor->id }}" @selected(($filtros['proveedor_id'] ?? null) == $proveedor->id)>{{ $proveedor->nombre }}</option>
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
                    <div class="flex items-end gap-3">
                        <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Filtrar</button>
                        <a href="{{ route('admin.cxp.pagos.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                    </div>
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
                                <th class="px-4 py-3">Proveedor</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                                <th class="px-4 py-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($pagos as $pago)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('admin.cxp.pagos.show', $pago) }}" class="text-blue-700 hover:underline">{{ $pago->numero }}</a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $pago->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 max-w-xs truncate">{{ $pago->proveedor->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $pago->total, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($pago->esAnulado())
                                            @include('admin.cxc._estado', ['estado' => 'ANULADO'])
                                        @else
                                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Aplicado</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                        No hay pagos registrados.
                                        @can('cxp.gestionar')
                                            <a href="{{ route('admin.cxp.pagos.create') }}" class="text-blue-700 underline">Registrar el primero</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($pagos->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $pagos->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
