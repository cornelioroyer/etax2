<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturas de venta</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.ventas.facturas.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.ventas.facturas.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                @can('ventas.gestionar')
                <button type="button" onclick="document.getElementById('modal-importar').classList.remove('hidden')" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Importar Ventas DGI</button>
                <button type="button" onclick="document.getElementById('modal-importar-generico').classList.remove('hidden')" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Importar Excel</button>
                <a href="{{ route('admin.ventas.facturas.create') }}" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500">+ Nueva factura</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if (session('import_ventas_errores') && count(session('import_ventas_errores')))
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-semibold mb-1">Avisos de la importación:</p>
                    @foreach (session('import_ventas_errores') as $aviso)<div>• {{ $aviso }}</div>@endforeach
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="rounded-lg bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">Saldo total por cobrar</p>
                <p class="text-2xl font-bold text-[#0d2d5e]">B/. {{ number_format($saldoTotal, 2) }}</p>
            </div>

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div>
                        <x-input-label for="tipo" value="Tipo" />
                        <select id="tipo" name="tipo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            <option value="FACTURA" @selected(($filtros['tipo'] ?? '') === 'FACTURA')>Factura</option>
                            <option value="NOTA_CREDITO" @selected(($filtros['tipo'] ?? '') === 'NOTA_CREDITO')>Nota crédito</option>
                            <option value="NOTA_DEBITO" @selected(($filtros['tipo'] ?? '') === 'NOTA_DEBITO')>Nota débito</option>
                            <option value="REEMBOLSO" @selected(($filtros['tipo'] ?? '') === 'REEMBOLSO')>Reembolso</option>
                        </select>
                    </div>
                    <x-buscador-contacto name="cliente_id" label="Cliente" :opciones="$clientes" :selected="$filtros['cliente_id'] ?? null" />
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach (['BORRADOR', 'EMITIDA', 'PARCIAL', 'PAGADA', 'APLICADA', 'ANULADA'] as $est)
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
            @php
                $sortUrl = fn(string $col) => route('admin.ventas.facturas.index', array_merge(
                    request()->except(['sort','dir','page']),
                    ['sort' => $col, 'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc']
                ));
                $sortIcon = function(string $col) use ($sort, $dir): string {
                    if ($sort !== $col) return '<svg class="inline w-3 h-3 ml-0.5 text-gray-300" viewBox="0 0 16 16" fill="currentColor"><path d="M5 8l3-4 3 4H5zm0 0l3 4 3-4H5z"/></svg>';
                    return $dir === 'asc'
                        ? '<svg class="inline w-3 h-3 ml-0.5 text-blue-500" viewBox="0 0 16 16" fill="currentColor"><path d="M5 10l3-5 3 5H5z"/></svg>'
                        : '<svg class="inline w-3 h-3 ml-0.5 text-blue-500" viewBox="0 0 16 16" fill="currentColor"><path d="M11 6l-3 5-3-5h6z"/></svg>';
                };
            @endphp
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3"><a href="{{ $sortUrl('numero') }}" class="hover:text-gray-700">Número{!! $sortIcon('numero') !!}</a></th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3"><a href="{{ $sortUrl('fecha') }}" class="hover:text-gray-700">Fecha{!! $sortIcon('fecha') !!}</a></th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3 hidden md:table-cell"><a href="{{ $sortUrl('fecha_vencimiento') }}" class="hover:text-gray-700">Vence{!! $sortIcon('fecha_vencimiento') !!}</a></th>
                                <th class="px-4 py-3 text-right"><a href="{{ $sortUrl('total') }}" class="hover:text-gray-700">Total{!! $sortIcon('total') !!}</a></th>
                                <th class="px-4 py-3 text-right"><a href="{{ $sortUrl('saldo') }}" class="hover:text-gray-700">Saldo{!! $sortIcon('saldo') !!}</a></th>
                                <th class="px-4 py-3"><a href="{{ $sortUrl('estado') }}" class="hover:text-gray-700">Estado{!! $sortIcon('estado') !!}</a></th>
                                <th class="px-4 py-3 hidden md:table-cell">FEL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($facturas as $factura)
                                @php
                                    $esNc = $factura->tipo_documento === 'NOTA_CREDITO';
                                    $esNd = $factura->tipo_documento === 'NOTA_DEBITO';
                                    $esRe = $factura->tipo_documento === 'REEMBOLSO';
                                    $signo = $esNc ? -1 : 1;
                                    $showUrl = match ($factura->tipo_documento) {
                                        'NOTA_CREDITO' => route('admin.ventas.notas-credito.show', $factura->id),
                                        'NOTA_DEBITO'  => route('admin.ventas.notas-debito.show', $factura->id),
                                        'REEMBOLSO'    => route('admin.ventas.reembolsos.show', $factura->id),
                                        default        => route('admin.ventas.facturas.show', $factura->id),
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ $showUrl }}" class="text-blue-700 hover:underline">{{ $factura->numero }}</a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($esNc)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Nota crédito</span>
                                        @elseif ($esNd)
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Nota débito</span>
                                        @elseif ($esRe)
                                            <span class="inline-flex rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800">Reembolso</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">Factura</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $factura->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 max-w-xs truncate">{{ $factura->cliente->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap hidden md:table-cell">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap {{ $esNc ? 'text-red-600' : '' }}">B/. {{ number_format($signo * (float) $factura->total, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap font-medium">{{ $esNc ? '—' : 'B/. '.number_format((float) $factura->saldo, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @include('admin.ventas.facturas._estado', ['estado' => $factura->estado])
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell text-xs">
                                        @if ($esNc || $esNd)
                                            <span class="text-gray-300">—</span>
                                        @elseif ($factura->fel_documento_id)
                                            <span class="text-green-600 font-medium">Emitida</span>
                                        @else
                                            <span class="text-gray-400">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                        No hay documentos.
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

@if($errors->has('archivo') || $errors->has('importar'))
<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('modal-importar').classList.remove('hidden'));</script>
@endif

{{-- Modal importar Excel --}}
<div id="modal-importar" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,0.5)">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold text-gray-900">Importar facturas de venta</h3>

            @error('importar')
                <div class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('admin.ventas.facturas.importar') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Archivo Excel</label>
                    <input type="file" name="archivo" accept=".xlsx,.xls" required
                        class="block w-full text-sm text-gray-500 file:mr-4 file:rounded file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
                    @error('archivo')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Reporte DGI "Documentos Electrónicos Emitidos" (.xlsx). Se importan Facturas de Operación Interna y Notas de Crédito Genéricas. Por cada factura se consulta la DGI por su CUFE para traer el número real y el detalle de líneas tal como aparece allí; el código de artículo de cada línea se empareja con el catálogo (y se crea el artículo si no existe). Si el cliente no existe se crea usando su RUC como código. Los duplicados se detectan por CUFE y se omiten.</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modal-importar').classList.add('hidden')"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal importar Ventas desde Excel propio (no DGI) --}}
<div id="modal-importar-generico" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Importar Ventas (Excel propio)</h3>
            <button type="button" onclick="document.getElementById('modal-importar-generico').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-3">
            Para ventas que <em>no</em> vienen del portal de la DGI (p. ej. históricas o de otro sistema). Sube un Excel con tus documentos.
            Cada fila es una línea; varias filas con el mismo cliente y número forman un solo documento.
            Cada factura se crea <strong>emitida y contabilizada</strong> (Dr CxC / Cr Ventas / Cr ITBMS) con su número del Excel; si el cliente no existe, se crea automáticamente.
        </p>
        <p class="text-sm mb-4">
            <a href="{{ route('admin.ventas.facturas.importar-generico.plantilla') }}" class="text-blue-600 hover:underline font-medium">
                ↓ Descargar plantilla de ejemplo
            </a>
        </p>
        <p class="text-xs text-gray-500 mb-4">
            Columnas: <code>cliente</code>, <code>ruc</code>, <code>numero</code>, <code>fecha</code>,
            <code>concepto</code>, <code>cuenta</code> (código de ingreso), <code>subtotal</code>, <code>itbms</code>, <code>tasa</code> (%), <code>vencimiento</code>.
        </p>
        @error('archivo_generico')
            <div class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('admin.ventas.facturas.importar-generico') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Excel/CSV</label>
                <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-importar-generico').classList.add('hidden')"
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
@if($errors->has('archivo_generico'))
<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('modal-importar-generico').classList.remove('hidden'));</script>
@endif
