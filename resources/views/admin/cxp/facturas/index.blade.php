<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturas por pagar</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cxp.facturas.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.cxp.facturas.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                @can('cxp.gestionar')
                    <button type="button" onclick="document.getElementById('modal-importar').classList.remove('hidden')"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Importar Compras
                    </button>
                    <a href="{{ route('admin.cxp.facturas.create') }}"
                       class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        + Nueva factura
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

            <div class="rounded-lg bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">Saldo neto por pagar (facturas + notas débito − notas crédito)</p>
                <p class="text-2xl font-bold text-[#0d2d5e]">B/. {{ number_format($saldoTotal, 2) }}</p>
            </div>

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-7">
                    <div class="col-span-2">
                        <x-input-label for="q" value="Buscar" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$filtros['q'] ?? ''" placeholder="Número o proveedor" />
                    </div>
                    <div>
                        <x-input-label for="tipo" value="Tipo" />
                        <select id="tipo" name="tipo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach ([\App\Models\CxpDocumento::TIPO_FACTURA => 'Factura', \App\Models\CxpDocumento::TIPO_IMPORTACION => 'Importación', \App\Models\CxpDocumento::TIPO_REEMBOLSO => 'Reembolso', \App\Models\CxpDocumento::TIPO_NOTA_DEBITO => 'Nota débito', \App\Models\CxpDocumento::TIPO_NOTA_CREDITO => 'Nota crédito'] as $val => $lbl)
                                <option value="{{ $val }}" @selected(($filtros['tipo'] ?? '') === $val)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
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
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach (['BORRADOR', 'PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'] as $estado)
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
                    <a href="{{ route('admin.cxp.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                </div>
            </form>

            {{-- Tabla --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Número</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Proveedor</th>
                                <th class="px-4 py-3 hidden md:table-cell">Vence</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                                <th class="px-4 py-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($facturas as $factura)
                                @php
                                    $esNc = $factura->tipo_documento === \App\Models\CxpDocumento::TIPO_NOTA_CREDITO;
                                    $esNd = $factura->tipo_documento === \App\Models\CxpDocumento::TIPO_NOTA_DEBITO;
                                    $esReembolso = $factura->tipo_documento === \App\Models\CxpDocumento::TIPO_REEMBOLSO;
                                    $esImportacion = $factura->tipo_documento === \App\Models\CxpDocumento::TIPO_IMPORTACION;
                                    $signo = $esNc ? -1 : 1;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('admin.cxp.facturas.show', $factura) }}" class="text-blue-700 hover:underline">{{ $factura->numero }}</a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($esNc)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Nota crédito</span>
                                        @elseif ($esNd)
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Nota débito</span>
                                        @elseif ($esImportacion)
                                            <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-800">Importación</span>
                                        @elseif ($esReembolso)
                                            <span class="inline-flex rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-800">Reembolso</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">Factura</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $factura->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 max-w-xs truncate">{{ $factura->proveedor->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap hidden md:table-cell">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap {{ $esNc ? 'text-red-600' : '' }}">B/. {{ number_format($signo * (float) $factura->total, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap font-medium {{ $esNc ? 'text-red-600' : '' }}">B/. {{ number_format($signo * (float) $factura->saldo, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @include('admin.cxc._estado', ['estado' => $factura->estado])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                        No hay facturas que coincidan con el filtro.
                                        @can('cxp.gestionar')
                                            <a href="{{ route('admin.cxp.facturas.create') }}" class="text-blue-700 underline">Crear la primera</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($facturas->isNotEmpty())
                            <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold text-gray-800">
                                <tr>
                                    <td class="px-4 py-3" colspan="4">Total ({{ $facturas->total() }} {{ \Illuminate\Support\Str::plural('documento', $facturas->total()) }})</td>
                                    <td class="px-4 py-3 hidden md:table-cell"></td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $totales->total, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $totales->saldo, 2) }}</td>
                                    <td class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @if ($facturas->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $facturas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@can('cxp.gestionar')
<div id="modal-importar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Importar Compras</h3>
            <button type="button" onclick="document.getElementById('modal-importar').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-4">
            Selecciona el Excel de <em>Documentos Electrónicos Recibidos</em> descargado del portal de la DGI.
            Facturas y notas de crédito/débito se crearán como borrador (clasificadas por su tipo); si el proveedor no existe, se crea automáticamente con su RUC.
        </p>
        <form method="POST" action="{{ route('admin.cxp.facturas.importar') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Excel (.xlsx)</label>
                <input type="file" name="archivo" accept=".xlsx,.xls" required
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-importar').classList.add('hidden')"
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
