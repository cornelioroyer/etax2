<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Productos y Servicios</h2>
            @can('inventario.gestionar')
                <div class="flex items-center gap-2">
                    <button type="button" onclick="document.getElementById('modal-importar-items').classList.remove('hidden')"
                            class="inline-flex items-center gap-1 rounded-md border border-[#0d2d5e] px-4 py-2 text-sm font-semibold text-[#0d2d5e] hover:bg-blue-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 7.5 12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Importar
                    </button>
                    <a href="{{ route('admin.items.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        + Nuevo
                    </a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                    <select name="tipo" class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="PRODUCTO" @selected(($filtros['tipo'] ?? '') === 'PRODUCTO')>Producto</option>
                        <option value="SERVICIO" @selected(($filtros['tipo'] ?? '') === 'SERVICIO')>Servicio</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Categoría</label>
                    <select name="categoria_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                        <option value="">Todas</option>
                        @foreach ($categorias as $cat)
                            <option value="{{ $cat->id }}" @selected(($filtros['categoria_id'] ?? '') == $cat->id)>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estado</label>
                    <select name="activo" class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filtros['activo'] ?? '') === '1')>Activos</option>
                        <option value="0" @selected(($filtros['activo'] ?? '') === '0')>Inactivos</option>
                    </select>
                </div>
                <div class="flex-1 min-w-40">
                    <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Código o nombre…"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500">
                </div>
                <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button>
                @if (array_filter($filtros))
                    <a href="{{ route('admin.items.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Código</th>
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Categoría</th>
                                <th class="px-4 py-3">Unidad</th>
                                <th class="px-4 py-3 text-right">Precio venta</th>
                                <th class="px-4 py-3">ITBMS</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                <tr class="{{ $item->activo ? '' : 'bg-gray-50 opacity-60' }}">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $item->codigo }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $item->nombre }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->tipo === 'PRODUCTO' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                            {{ ucfirst(strtolower($item->tipo)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item->categoria?->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item->unidadMedida?->codigo ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float) $item->precio_venta, 2) }}</td>
                                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $item->impuesto?->nombre ?? 'Exento' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $item->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $item->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right flex justify-end gap-2">
                                        @can('inventario.gestionar')
                                            <a href="{{ route('admin.items.edit', $item) }}" class="text-blue-600 hover:underline text-xs">Editar</a>
                                            <form method="POST" action="{{ route('admin.items.toggle', $item) }}">
                                                @csrf
                                                <button class="text-xs {{ $item->activo ? 'text-red-500 hover:underline' : 'text-green-600 hover:underline' }}">
                                                    {{ $item->activo ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No hay productos/servicios.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $items->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    @can('inventario.gestionar')
    <div id="modal-importar-items" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Importar productos y servicios</h3>
                <button type="button" onclick="document.getElementById('modal-importar-items').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>

            <div class="mb-4 rounded-md bg-slate-50 p-3 text-xs text-slate-600 space-y-1">
                <p class="font-semibold text-slate-700">Formato del archivo (Excel o CSV):</p>
                <p>Fila 1 = encabezados (se omite). Columnas en orden:</p>
                <ol class="list-decimal list-inside space-y-0.5">
                    <li><strong>codigo</strong> — opcional (vacío = se autogenera PROD-001 / SERV-001)</li>
                    <li><strong>nombre</strong> — requerido</li>
                    <li>tipo — PRODUCTO / SERVICIO (default PRODUCTO)</li>
                    <li>descripcion — opcional</li>
                    <li>categoria — nombre de categoría existente (opcional)</li>
                    <li>unidad — código (ej. UND) o nombre de la unidad (opcional)</li>
                    <li>precio_venta — opcional (default 0)</li>
                    <li>costo — opcional (default 0)</li>
                    <li>cuenta_ingreso — código de cuenta contable (opcional)</li>
                    <li>cuenta_gasto — código de cuenta contable (opcional)</li>
                    <li>itbms — 0 / 7 / 10 / 15 (opcional; default 7)</li>
                </ol>
                <p class="mt-1">Solo se crean ítems nuevos. Si el <strong>código ya existe</strong>, la fila se omite. Las categorías, unidades y cuentas se buscan dentro de la compañía activa.</p>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-4">
                <a href="{{ route('admin.items.importar.plantilla-xlsx') }}"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 hover:underline">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Descargar plantilla Excel (con ejemplos)
                </a>
                <a href="{{ route('admin.items.importar.plantilla') }}"
                   class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Plantilla CSV
                </a>
            </div>

            <form method="POST" action="{{ route('admin.items.importar') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Archivo (.xlsx, .xls, .csv)</label>
                    <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                           class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modal-importar-items').classList.add('hidden')"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">
                        Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-layout>
