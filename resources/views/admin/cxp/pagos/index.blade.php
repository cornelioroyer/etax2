<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pagos a proveedores</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cxp.pagos.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.cxp.pagos.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                @can('cxp.gestionar')
                    <button type="button" onclick="document.getElementById('modal-importar-pagos').classList.remove('hidden')"
                            class="inline-flex items-center rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                        Importar Excel
                    </button>
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
            @if (session('import_pagos_errores') && count(session('import_pagos_errores')))
                <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                    <div class="font-semibold mb-1">Avisos de la importación:</div>
                    @foreach (session('import_pagos_errores') as $aviso)<div>• {{ $aviso }}</div>@endforeach
                </div>
            @endif

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <x-buscador-contacto name="proveedor_id" label="Proveedor" :opciones="$proveedores" :selected="$filtros['proveedor_id'] ?? null" />
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

    @can('cxp.gestionar')
    <div id="modal-importar-pagos" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Importar pagos (Excel)</h3>
                <button type="button" onclick="document.getElementById('modal-importar-pagos').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <p class="text-sm text-gray-600 mb-3">
                Sube un Excel con tus pagos a proveedores. El proveedor y la factura deben <strong>existir</strong>
                (no se crean). Cada fila aplica un monto a una factura por su número; varias filas con el mismo
                proveedor, fecha, cuenta y referencia forman <strong>un solo pago</strong>.
            </p>
            <p class="text-sm mb-4">
                <a href="{{ route('admin.cxp.pagos.importar.plantilla') }}" class="text-blue-600 hover:underline font-medium">
                    ↓ Descargar plantilla de ejemplo
                </a>
            </p>
            <p class="text-xs text-gray-500 mb-4">
                Columnas: <code>proveedor</code>, <code>ruc</code>, <code>numero</code> (de la factura),
                <code>fecha</code>, <code>monto</code>, <code>cuenta</code> (código de banco/caja),
                <code>referencia</code> (cheque/transferencia). El pago se registra en efectivo (Dr CxP / Cr banco);
                para retención o descuento, usa luego “Corregir pago”.
            </p>
            <form method="POST" action="{{ route('admin.cxp.pagos.importar') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Excel/CSV</label>
                    <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                           class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modal-importar-pagos').classList.add('hidden')"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-layout>
