<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notas de crédito / débito — CxP</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cxp.notas.index', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.cxp.notas.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                @can('cxp.gestionar')
                    <a href="{{ route('admin.cxp.notas.create', ['tipo' => 'credito']) }}" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">+ Nota de crédito</a>
                    <a href="{{ route('admin.cxp.notas.create', ['tipo' => 'debito']) }}" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">+ Nota de débito</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <x-input-label for="tipo" value="Tipo" />
                        <select id="tipo" name="tipo" class="mt-1 block w-44 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todas</option>
                            <option value="credito" @selected(($filtros['tipo'] ?? '') === 'credito')>Crédito</option>
                            <option value="debito" @selected(($filtros['tipo'] ?? '') === 'debito')>Débito</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="proveedor_id" value="Proveedor" />
                        <select id="proveedor_id" name="proveedor_id" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach ($proveedores as $p)
                                <option value="{{ $p->id }}" @selected(($filtros['proveedor_id'] ?? '') == $p->id)>{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Filtrar</button>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Número</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Proveedor</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                                <th class="px-4 py-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($notas as $nota)
                                @php $esCredito = $nota->tipo_documento === \App\Models\CxpDocumento::TIPO_NOTA_CREDITO; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('admin.cxp.notas.show', $nota) }}" class="text-blue-700 hover:underline">{{ $nota->numero }}</a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $esCredito ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $esCredito ? 'Crédito' : 'Débito' }}</span>
                                    </td>
                                    <td class="px-4 py-3">{{ $nota->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3">{{ $nota->proveedor->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float) $nota->total, 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ (float) $nota->saldo > 0 ? 'B/. '.number_format((float) $nota->saldo, 2) : '—' }}</td>
                                    <td class="px-4 py-3">@include('admin.cxc._estado', ['estado' => $nota->estado])</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No hay notas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $notas->links() }}
        </div>
    </div>
</x-app-layout>
