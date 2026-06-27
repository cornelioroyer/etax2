<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Recibos de cobro</h2>
            @can('ventas.gestionar')
                <div class="flex items-center gap-2">
                    <button type="button" onclick="document.getElementById('modal-importar-cobros').classList.remove('hidden')"
                            class="inline-flex items-center rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                        Importar Excel
                    </button>
                    <a href="{{ route('admin.ventas.recibos.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        + Nuevo recibo
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
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @if (session('import_cobros_errores') && count(session('import_cobros_errores')))
                <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                    <div class="font-semibold mb-1">Avisos de la importación:</div>
                    @foreach (session('import_cobros_errores') as $aviso)<div>• {{ $aviso }}</div>@endforeach
                </div>
            @endif

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg flex flex-wrap gap-3 items-end">
                <x-buscador-contacto name="cliente_id" label="Cliente" compact width="w-56" :opciones="$clientes" :selected="$filtros['cliente_id'] ?? null" />
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estado</label>
                    <select name="estado" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        <option value="APLICADO" @selected(($filtros['estado'] ?? '') === 'APLICADO')>Aplicado</option>
                        <option value="ANULADO" @selected(($filtros['estado'] ?? '') === 'ANULADO')>Anulado</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Desde</label>
                    <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                    <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button>
                @if (array_filter($filtros))
                    <a href="{{ route('admin.ventas.recibos.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Número</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Método pago</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recibos as $r)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs font-medium">{{ $r->numero }}</td>
                                <td class="px-4 py-3">{{ $r->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $r->cliente?->nombre }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $r->metodo_pago ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $r->total, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if ($r->estado === 'APLICADO')
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Aplicado</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Anulado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.ventas.recibos.show', $r) }}" class="text-blue-600 hover:underline text-xs">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No hay recibos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($recibos->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $recibos->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    @can('ventas.gestionar')
    <div id="modal-importar-cobros" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Importar cobros (Excel)</h3>
                <button type="button" onclick="document.getElementById('modal-importar-cobros').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <p class="text-sm text-gray-600 mb-3">
                Sube un Excel con tus cobros de clientes. El cliente y la factura deben <strong>existir</strong>
                (no se crean). Cada fila aplica un monto a una factura por su número; varias filas con el mismo
                cliente, fecha, cuenta y referencia forman <strong>un solo recibo de cobro</strong>.
            </p>
            <p class="text-sm mb-4">
                <a href="{{ route('admin.ventas.recibos.importar.plantilla') }}" class="text-blue-600 hover:underline font-medium">
                    ↓ Descargar plantilla de ejemplo
                </a>
            </p>
            <p class="text-xs text-gray-500 mb-4">
                Columnas: <code>cliente</code>, <code>ruc</code>, <code>numero</code> (de la factura),
                <code>fecha</code>, <code>monto</code>, <code>cuenta</code> (código de banco/caja donde se deposita),
                <code>referencia</code> (depósito/transferencia). El cobro se registra Dr banco / Cr CxC.
            </p>
            <form method="POST" action="{{ route('admin.ventas.recibos.importar') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Archivo Excel/CSV</label>
                    <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                           class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modal-importar-cobros').classList.add('hidden')"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                        Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-layout>
