<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ ucfirst(strtolower($movimiento->tipo_movimiento)) }} — {{ $movimiento->fecha->format('d/m/Y') }}
            </h2>
            <div class="flex items-center gap-3">
                @php
                    $puedeReversar = $movimiento->estado !== 'ANULADO'
                        && ! $movimiento->esReverso()
                        && $movimiento->reversadoPor->isEmpty();
                @endphp
                @can('inventario.gestionar')
                    @if ($puedeReversar)
                        <form method="POST" action="{{ route('admin.inventario.movimientos.reversar', $movimiento) }}"
                              onsubmit="return confirm('¿Reversar este movimiento? Se creará una transacción de compensación (movimiento inverso + asiento inverso); el original queda en el historial.');">
                            @csrf
                            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                Reversar
                            </button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('admin.inventario.movimientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if ($movimiento->esReverso() && $movimiento->reversaDe)
                <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                    Este movimiento es el <strong>reverso</strong> de
                    <a href="{{ route('admin.inventario.movimientos.show', $movimiento->reversaDe) }}" class="font-medium underline">el movimiento #{{ $movimiento->reversaDe->id }}</a>.
                </div>
            @elseif ($movimiento->reversadoPor->isNotEmpty())
                <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                    Este movimiento fue <strong>reversado</strong> por
                    <a href="{{ route('admin.inventario.movimientos.show', $movimiento->reversadoPor->first()) }}" class="font-medium underline">el movimiento #{{ $movimiento->reversadoPor->first()->id }}</a>.
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-gray-500">Almacén</dt>
                        <dd class="font-medium">{{ $movimiento->almacen->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tipo</dt>
                        <dd class="font-medium">{{ ucfirst(strtolower($movimiento->tipo_movimiento)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Estado</dt>
                        <dd><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">{{ $movimiento->estado }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Asiento</dt>
                        <dd class="font-medium">
                            @if ($movimiento->asiento)
                                <a href="{{ route('admin.asientos.show', $movimiento->asiento) }}" class="text-blue-700 hover:underline">{{ $movimiento->asiento->numero }}</a>
                            @else
                                <span class="text-gray-400">Sin asiento</span>
                            @endif
                        </dd>
                    </div>
                    @if ($movimiento->descripcion)
                        <div class="sm:col-span-3">
                            <dt class="text-gray-500">Descripción</dt>
                            <dd class="font-medium">{{ $movimiento->descripcion }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Costo unit.</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($movimiento->detalle as $d)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs text-gray-500 mr-2">{{ $d->item?->codigo }}</span>
                                    {{ $d->item?->nombre }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $d->cantidad, 4), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">B/. {{ number_format((float) $d->costo_unitario, 4) }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $d->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 font-semibold text-sm">
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-right text-gray-700">Total</td>
                            <td class="px-4 py-2 text-right">B/. {{ number_format($movimiento->detalle->sum('total'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
