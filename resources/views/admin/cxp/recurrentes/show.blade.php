<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Factura recurrente: {{ $plantilla->nombre }}</h2>
            <a href="{{ route('admin.cxp.recurrentes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
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
                        <div><dt class="text-xs uppercase text-gray-400">Proveedor</dt><dd class="text-gray-800">{{ $plantilla->proveedor?->nombre }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Frecuencia</dt><dd class="text-gray-800">{{ $plantilla->etiquetaFrecuencia() }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Estado</dt>
                            <dd>
                                @php $badge = ['ACTIVA' => 'bg-green-100 text-green-700', 'PAUSADA' => 'bg-gray-200 text-gray-700', 'FINALIZADA' => 'bg-blue-100 text-blue-700'][$plantilla->estado] ?? 'bg-gray-100'; @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $plantilla->estado }}</span>
                            </dd>
                        </div>
                        <div><dt class="text-xs uppercase text-gray-400">Próximo vencimiento</dt><dd class="text-gray-800">{{ optional($plantilla->proxima_fecha)->format('Y-m-d') }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Días de crédito</dt><dd class="text-gray-800">{{ $plantilla->dias_credito }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Hasta</dt><dd class="text-gray-800">{{ $plantilla->fecha_fin ? $plantilla->fecha_fin->format('Y-m-d') : '—' }}</dd></div>
                        <div><dt class="text-xs uppercase text-gray-400">Generadas</dt><dd class="text-gray-800">{{ $plantilla->ocurrencias_generadas }}@if ($plantilla->ocurrencias_max) / {{ $plantilla->ocurrencias_max }}@endif</dd></div>
                        @if ($plantilla->referencia)<div><dt class="text-xs uppercase text-gray-400">Referencia</dt><dd class="text-gray-800">{{ $plantilla->referencia }}</dd></div>@endif
                    </dl>

                    <div class="flex flex-wrap items-center gap-2">
                        @can('cxp.gestionar')
                            <form method="POST" action="{{ route('admin.cxp.recurrentes.generar', $plantilla) }}">@csrf
                                <button class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-500"
                                        @disabled(! $plantilla->esActiva())>Generar ahora</button>
                            </form>
                            <a href="{{ route('admin.cxp.recurrentes.edit', $plantilla) }}" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Editar</a>
                            @if ($plantilla->esActiva())
                                <form method="POST" action="{{ route('admin.cxp.recurrentes.pausar', $plantilla) }}">@csrf
                                    <button class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Pausar</button>
                                </form>
                            @elseif (! $plantilla->estaFinalizada())
                                <form method="POST" action="{{ route('admin.cxp.recurrentes.reactivar', $plantilla) }}">@csrf
                                    <button class="rounded-md border border-green-300 bg-white px-3 py-1.5 text-sm text-green-700 hover:bg-green-50">Reactivar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.cxp.recurrentes.destroy', $plantilla) }}"
                                  onsubmit="return confirm('¿Eliminar la plantilla? Las facturas ya generadas se conservan.')">@csrf @method('DELETE')
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
                            <th class="px-4 py-3">Descripción</th>
                            <th class="px-4 py-3">Cuenta</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3 text-right">ITBMS %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($plantilla->detalle as $l)
                            <tr>
                                <td class="px-4 py-2 text-gray-700">{{ $l->descripcion }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $l->cuenta?->codigo }} — {{ $l->cuenta?->nombre }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($l->cantidad, 2) }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">B/. {{ number_format($l->precio_unitario, 2) }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ $l->tasa_itbms }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 text-sm font-semibold">
                        <tr><td colspan="4" class="px-4 py-1 text-right text-gray-600">Subtotal</td><td class="px-4 py-1 text-right">B/. {{ number_format($plantilla->subtotal, 2) }}</td></tr>
                        <tr><td colspan="4" class="px-4 py-1 text-right text-gray-600">ITBMS</td><td class="px-4 py-1 text-right">B/. {{ number_format($plantilla->impuesto, 2) }}</td></tr>
                        <tr><td colspan="4" class="px-4 py-2 text-right text-gray-700">Total</td><td class="px-4 py-2 text-right text-gray-900">B/. {{ number_format($plantilla->total, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Facturas generadas --}}
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Facturas generadas ({{ $generadas->count() }}@if($generadas->count() >= 50)+@endif)</h3>
                <div class="overflow-x-auto rounded-md bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Número</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Vencimiento</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($generadas as $f)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2"><a href="{{ route('admin.cxp.facturas.show', $f) }}" class="text-blue-600 hover:underline">{{ $f->numero }}</a></td>
                                    <td class="px-4 py-2 text-gray-600">{{ $f->fecha->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ optional($f->fecha_vencimiento)->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700">B/. {{ number_format($f->total, 2) }}</td>
                                    <td class="px-4 py-2">
                                        @php $eb = ['BORRADOR' => 'bg-gray-200 text-gray-700', 'PENDIENTE' => 'bg-amber-100 text-amber-700', 'PARCIAL' => 'bg-amber-100 text-amber-700', 'PAGADO' => 'bg-green-100 text-green-700', 'ANULADO' => 'bg-red-100 text-red-700'][$f->estado] ?? 'bg-gray-100'; @endphp
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $eb }}">{{ $f->estado }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Todavía no se ha generado ninguna factura de esta plantilla.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
