<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Plantilla recurrente: {{ $plantilla->nombre }}</h2>
            <a href="{{ route('admin.asientos-recurrentes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="rounded-md bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs uppercase text-gray-400">Frecuencia</dt><dd class="text-gray-800">{{ $plantilla->etiquetaFrecuencia() }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Estado</dt>
                            <dd>
                                @php $badge = ['ACTIVA' => 'bg-green-100 text-green-700', 'PAUSADA' => 'bg-gray-200 text-gray-700', 'FINALIZADA' => 'bg-blue-100 text-blue-700'][$plantilla->estado] ?? 'bg-gray-100'; @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $plantilla->estado }}</span>
                            </dd>
                        </div>
                        <div><dt class="text-xs uppercase text-gray-400">Próximo vencimiento</dt><dd class="text-gray-800">{{ optional($plantilla->proxima_fecha)->format('Y-m-d') }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Primer vencimiento</dt><dd class="text-gray-800">{{ optional($plantilla->fecha_inicio)->format('Y-m-d') }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Hasta</dt><dd class="text-gray-800">{{ $plantilla->fecha_fin ? $plantilla->fecha_fin->format('Y-m-d') : '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Generados</dt><dd class="text-gray-800">{{ $plantilla->ocurrencias_generadas }}@if ($plantilla->ocurrencias_max) / {{ $plantilla->ocurrencias_max }}@endif</dd></div>
                        @if ($plantilla->descripcion)<div class="col-span-2 sm:col-span-3"><dt class="text-xs uppercase text-gray-400">Concepto</dt><dd class="text-gray-800">{{ $plantilla->descripcion }}</dd></div>@endif
                    </dl>

                    <div class="flex flex-wrap items-center gap-2">
                        @can('contabilidad.crear')
                            <form method="POST" action="{{ route('admin.asientos-recurrentes.generar', $plantilla) }}">@csrf
                                <button class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-500"
                                        @disabled(! $plantilla->esActiva())>Generar ahora</button>
                            </form>
                        @endcan
                        @can('contabilidad.editar')
                            <a href="{{ route('admin.asientos-recurrentes.edit', $plantilla) }}" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Editar</a>
                            @if ($plantilla->esActiva())
                                <form method="POST" action="{{ route('admin.asientos-recurrentes.pausar', $plantilla) }}">@csrf
                                    <button class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Pausar</button>
                                </form>
                            @elseif (! $plantilla->estaFinalizada())
                                <form method="POST" action="{{ route('admin.asientos-recurrentes.reactivar', $plantilla) }}">@csrf
                                    <button class="rounded-md border border-green-300 bg-white px-3 py-1.5 text-sm text-green-700 hover:bg-green-50">Reactivar</button>
                                </form>
                            @endif
                        @endcan
                        @can('contabilidad.eliminar')
                            <form method="POST" action="{{ route('admin.asientos-recurrentes.destroy', $plantilla) }}"
                                  onsubmit="return confirm('¿Eliminar la plantilla? Los asientos ya generados se conservan.')">@csrf @method('DELETE')
                                <button class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">Eliminar</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- Líneas de la plantilla --}}
            <div class="overflow-x-auto rounded-md bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Cuenta</th>
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3 text-right">Débito</th>
                            <th class="px-4 py-3 text-right">Crédito</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($plantilla->detalle as $l)
                            <tr>
                                <td class="px-4 py-2 text-gray-700">{{ $l->cuenta?->codigo }} — {{ $l->cuenta?->nombre }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $l->descripcion }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ $l->debito > 0 ? 'B/. '.number_format($l->debito, 2) : '' }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ $l->credito > 0 ? 'B/. '.number_format($l->credito, 2) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 text-sm font-semibold">
                        <tr>
                            <td colspan="2" class="px-4 py-2 text-right text-gray-600">Totales</td>
                            <td class="px-4 py-2 text-right">B/. {{ number_format($plantilla->total_debito, 2) }}</td>
                            <td class="px-4 py-2 text-right">B/. {{ number_format($plantilla->total_credito, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Asientos generados --}}
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Asientos generados ({{ $generados->count() }}@if($generados->count() >= 50)+@endif)</h3>
                <div class="overflow-x-auto rounded-md bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Número</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                                <th class="px-4 py-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($generados as $a)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2"><a href="{{ route('admin.asientos.show', $a) }}" class="text-blue-600 hover:underline">{{ $a->numero }}</a></td>
                                    <td class="px-4 py-2 text-gray-600">{{ $a->fecha->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700">B/. {{ number_format($a->total_debito, 2) }}</td>
                                    <td class="px-4 py-2">
                                        @php $eb = ['BORRADOR' => 'bg-gray-200 text-gray-700', 'POSTEADO' => 'bg-green-100 text-green-700', 'ANULADO' => 'bg-red-100 text-red-700'][$a->estado] ?? 'bg-gray-100'; @endphp
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $eb }}">{{ $a->estado }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Todavía no se ha generado ningún asiento de esta plantilla.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
