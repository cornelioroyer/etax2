<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturas recurrentes</h2>
            @can('cxp.gestionar')
                <a href="{{ route('admin.cxp.recurrentes.create') }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    + Nueva plantilla
                </a>
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

            @if ($pendientes > 0)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    <span>Hay <strong>{{ $pendientes }}</strong> plantilla(s) con vencimientos pendientes hasta hoy. El sistema las genera solo a diario, pero puedes generarlas ahora.</span>
                    @can('cxp.gestionar')
                        <form method="POST" action="{{ route('admin.cxp.recurrentes.generar-todos') }}">
                            @csrf
                            <button class="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-500">Generar pendientes</button>
                        </form>
                    @endcan
                </div>
            @endif

            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-md bg-white p-4 shadow-sm">
                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-500">Estado</label>
                    <select name="estado" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Todos</option>
                        <option value="ACTIVA" @selected(($filtros['estado'] ?? '') === 'ACTIVA')>Activas</option>
                        <option value="PAUSADA" @selected(($filtros['estado'] ?? '') === 'PAUSADA')>Pausadas</option>
                        <option value="FINALIZADA" @selected(($filtros['estado'] ?? '') === 'FINALIZADA')>Finalizadas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-500">Buscar</label>
                    <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre o proveedor"
                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <button class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Filtrar</button>
                @if (($filtros['estado'] ?? '') || ($filtros['q'] ?? ''))
                    <a href="{{ route('admin.cxp.recurrentes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Limpiar</a>
                @endif
            </form>

            <div class="overflow-x-auto rounded-md bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Plantilla</th>
                            <th class="px-4 py-3">Proveedor</th>
                            <th class="px-4 py-3">Frecuencia</th>
                            <th class="px-4 py-3">Próximo vencimiento</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Generadas</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($plantillas as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.cxp.recurrentes.show', $p) }}" class="font-medium text-blue-700 hover:underline">{{ $p->nombre }}</a>
                                    @if ($p->referencia)<div class="text-xs text-gray-400">{{ $p->referencia }}</div>@endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $p->proveedor?->nombre }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $p->etiquetaFrecuencia() }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ optional($p->proxima_fecha)->format('Y-m-d') }}
                                    @if ($p->esActiva() && $p->proxima_fecha && $p->proxima_fecha->isPast())
                                        <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">vencido</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">B/. {{ number_format($p->total, 2) }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $p->ocurrencias_generadas }}@if ($p->ocurrencias_max) / {{ $p->ocurrencias_max }}@endif
                                </td>
                                <td class="px-4 py-3">
                                    @php $badge = ['ACTIVA' => 'bg-green-100 text-green-700', 'PAUSADA' => 'bg-gray-200 text-gray-700', 'FINALIZADA' => 'bg-blue-100 text-blue-700'][$p->estado] ?? 'bg-gray-100'; @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $p->estado }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('cxp.gestionar')
                                            @if ($p->esActiva())
                                                <form method="POST" action="{{ route('admin.cxp.recurrentes.pausar', $p) }}">@csrf
                                                    <button class="text-xs text-gray-500 hover:text-gray-700">Pausar</button>
                                                </form>
                                            @elseif (! $p->estaFinalizada())
                                                <form method="POST" action="{{ route('admin.cxp.recurrentes.reactivar', $p) }}">@csrf
                                                    <button class="text-xs text-green-600 hover:text-green-800">Reactivar</button>
                                                </form>
                                            @endif
                                        @endcan
                                        <a href="{{ route('admin.cxp.recurrentes.show', $p) }}" class="text-xs text-blue-600 hover:underline">Ver</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No hay plantillas de facturas recurrentes. Crea la primera con «Nueva plantilla».</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $plantillas->links() }}
        </div>
    </div>
</x-app-layout>
