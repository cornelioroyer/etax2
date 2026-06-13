<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Recibo {{ $recibo->numero }}</h2>
            <a href="{{ route('admin.ventas.recibos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ $recibo->numero }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ $recibo->fecha->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-right">
                        @if ($recibo->estado === 'APLICADO')
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium bg-green-100 text-green-700">Aplicado</span>
                        @else
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium bg-red-100 text-red-700">Anulado</span>
                        @endif
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-gray-500">Cliente</dt><dd class="font-medium">{{ $recibo->cliente?->nombre }}</dd></div>
                    <div><dt class="text-gray-500">Método pago</dt><dd class="font-medium">{{ $recibo->metodo_pago ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Total cobrado</dt><dd class="font-semibold text-green-700 text-base">B/. {{ number_format((float) $recibo->total, 2) }}</dd></div>
                    @if ($recibo->asiento)
                        <div><dt class="text-gray-500">Asiento</dt><dd><a href="{{ route('admin.asientos.show', $recibo->asiento) }}" class="text-blue-600 hover:underline text-xs">{{ $recibo->asiento->numero }}</a></dd></div>
                    @endif
                </dl>
            </div>

            {{-- Detalle de facturas cobradas --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Facturas cubiertas</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Factura</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-right">Monto cobrado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recibo->detalle as $d)
                            <tr>
                                <td class="px-4 py-3">
                                    @if ($d->factura)
                                        <a href="{{ route('admin.ventas.facturas.show', $d->factura) }}" class="font-mono text-xs text-blue-600 hover:underline">{{ $d->factura->numero }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $d->factura?->fecha?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $d->monto, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 font-semibold text-sm">
                        <tr>
                            <td colspan="2" class="px-4 py-2 text-right text-gray-700">Total</td>
                            <td class="px-4 py-2 text-right">B/. {{ number_format((float) $recibo->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @can('ventas.gestionar')
                @if (! $recibo->esAnulado())
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('admin.ventas.recibos.anular', $recibo) }}"
                            onsubmit="return confirm('¿Anular el recibo {{ $recibo->numero }}? Se restaurarán los saldos de las facturas.')">
                            @csrf
                            <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Anular recibo</button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
