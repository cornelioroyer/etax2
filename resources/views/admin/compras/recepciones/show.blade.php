<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Recepción {{ $recepcion->numero }}
            </h2>
            <a href="{{ route('admin.compras.ordenes.show', $orden) }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver a la orden {{ $orden->numero }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Cabecera --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-start justify-between gap-4">
                    <div class="grid flex-1 grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <div class="text-gray-500">Número</div>
                        <div class="font-medium">{{ $recepcion->numero }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Fecha</div>
                        <div class="font-medium">{{ $recepcion->fecha->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Proveedor</div>
                        <div class="font-medium">{{ $recepcion->proveedor->nombre ?? $orden->proveedor->nombre ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Orden de compra</div>
                        <div class="font-medium">
                            <a href="{{ route('admin.compras.ordenes.show', $orden) }}" class="text-blue-700 hover:underline">{{ $orden->numero }}</a>
                        </div>
                    </div>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        @if ($recepcion->estado === \App\Models\CompraRecepcion::ESTADO_ANULADO)
                            <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Anulada</span>
                        @else
                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Recibida</span>
                            @can('compras.gestionar')
                                @if (! $orden->cxp_documento_id && ! in_array($orden->estado, [\App\Models\CompraOrden::ESTADO_FACTURADA, \App\Models\CompraOrden::ESTADO_ANULADA], true))
                                    <form method="POST" action="{{ route('admin.compras.ordenes.recepciones.anular', [$orden, $recepcion]) }}"
                                          onsubmit="return confirm('¿Anular la recepción {{ $recepcion->numero }}? Sus cantidades volverán a quedar pendientes en la orden {{ $orden->numero }}.');">
                                        @csrf
                                        <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Anular recepción</button>
                                    </form>
                                @endif
                            @endcan
                        @endif
                    </div>
                </div>
            </div>

            {{-- Detalle --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Descripción</th>
                                <th class="px-4 py-3 text-right">Cantidad recibida</th>
                                <th class="px-4 py-3 text-right">Costo unitario</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($recepcion->detalle as $linea)
                                <tr>
                                    <td class="px-4 py-3">{{ $linea->descripcion }}</td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float) $linea->costo, 4) }}</td>
                                    <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $linea->cantidad * (float) $linea->costo, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">Sin líneas registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($recepcion->detalle->isNotEmpty())
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="font-semibold">
                                    <td colspan="3" class="px-4 py-2 text-right text-gray-700">Total recepción</td>
                                    <td class="px-4 py-2 text-right">
                                        B/. {{ number_format($recepcion->detalle->sum(fn($l) => (float) $l->cantidad * (float) $l->costo), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
