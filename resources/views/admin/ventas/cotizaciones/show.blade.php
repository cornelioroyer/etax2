<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cotización {{ $cotizacion->numero }}</h2>
            <a href="{{ route('admin.ventas.cotizaciones.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Cabecera --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <dl class="grid grid-cols-2 gap-x-10 gap-y-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-gray-500">Cliente</dt>
                            <dd class="font-medium text-gray-900">{{ $cotizacion->cliente->nombre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Fecha</dt>
                            <dd class="font-medium">{{ $cotizacion->fecha->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Válida hasta</dt>
                            <dd class="font-medium">{{ $cotizacion->fecha_validez?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Estado</dt>
                            <dd>@include('admin.ventas.cotizaciones._estado', ['estado' => $cotizacion->estado])</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Total</dt>
                            <dd class="text-lg font-bold text-[#0d2d5e]">B/. {{ number_format((float) $cotizacion->total, 2) }}</dd>
                        </div>
                    </dl>

                    @can('ventas.gestionar')
                        <div class="flex flex-wrap gap-2">
                            @php
                                $transiciones = [
                                    'BORRADOR'  => [['estado' => 'ENVIADA',   'label' => 'Marcar enviada',   'class' => 'border-blue-300 text-blue-700 hover:bg-blue-50']],
                                    'ENVIADA'   => [
                                        ['estado' => 'ACEPTADA',  'label' => 'Aceptada',  'class' => 'border-green-300 text-green-700 hover:bg-green-50'],
                                        ['estado' => 'RECHAZADA', 'label' => 'Rechazada', 'class' => 'border-red-300 text-red-700 hover:bg-red-50'],
                                    ],
                                    'ACEPTADA'  => [['estado' => 'RECHAZADA', 'label' => 'Rechazada', 'class' => 'border-red-300 text-red-700 hover:bg-red-50']],
                                    'RECHAZADA' => [['estado' => 'ACEPTADA',  'label' => 'Aceptada',  'class' => 'border-green-300 text-green-700 hover:bg-green-50']],
                                ];
                                $acciones = $transiciones[$cotizacion->estado] ?? [];
                            @endphp

                            @if ($cotizacion->esFacturable())
                                <div x-data="{ open: false }">
                                    <button @click="open = true"
                                            class="rounded-md border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                                        Convertir a factura
                                    </button>
                                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                                        <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                                            <h3 class="mb-4 text-base font-semibold text-gray-800">Generar factura desde {{ $cotizacion->numero }}</h3>
                                            <form method="POST" action="{{ route('admin.ventas.cotizaciones.facturar', $cotizacion) }}">
                                                @csrf
                                                <div class="space-y-3">
                                                    <div>
                                                        <x-input-label for="fac_fecha" value="Fecha de factura *" />
                                                        <x-text-input id="fac_fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                                                      value="{{ now()->format('Y-m-d') }}" />
                                                    </div>
                                                    <div>
                                                        <x-input-label for="fac_vence" value="Fecha de vencimiento" />
                                                        <x-text-input id="fac_vence" name="fecha_vencimiento" type="text" class="js-date mt-1 block w-full" />
                                                    </div>
                                                </div>
                                                <div class="mt-5 flex gap-3">
                                                    <button type="submit"
                                                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                                        Facturar y contabilizar
                                                    </button>
                                                    <button type="button" @click="open = false"
                                                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @foreach ($acciones as $accion)
                                <form method="POST" action="{{ route('admin.ventas.cotizaciones.estado', $cotizacion) }}">
                                    @csrf
                                    <input type="hidden" name="estado" value="{{ $accion['estado'] }}">
                                    <button class="rounded-md border bg-white px-4 py-2 text-sm font-semibold {{ $accion['class'] }}">
                                        {{ $accion['label'] }}
                                    </button>
                                </form>
                            @endforeach

                            @if (! in_array($cotizacion->estado, ['FACTURADA', 'ANULADA']))
                                <form method="POST" action="{{ route('admin.ventas.cotizaciones.anular', $cotizacion) }}"
                                      onsubmit="return confirm('¿Anular la cotización {{ $cotizacion->numero }}?')">
                                    @csrf
                                    <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                                        Anular
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
            </div>

            {{-- Detalle --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Descripción</th>
                                <th class="px-4 py-3 text-right">Cant.</th>
                                <th class="px-4 py-3 text-right">Precio</th>
                                <th class="px-4 py-3 text-right">ITBMS</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cotizacion->detalle as $linea)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500">{{ $linea->linea }}</td>
                                    <td class="px-4 py-3">{{ $linea->descripcion }}</td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float) $linea->precio_unitario, 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">
                                        {{ $linea->impuesto?->nombre ?? 'Exento' }}
                                        (B/. {{ number_format((float) $linea->impuesto_monto, 2) }})
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $linea->total_linea, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 text-sm">
                            <tr>
                                <td colspan="5" class="px-4 py-1 text-right text-gray-600">Subtotal</td>
                                <td class="px-4 py-1 text-right">B/. {{ number_format((float) $cotizacion->subtotal, 2) }}</td>
                            </tr>
                            @if ((float) $cotizacion->itbms > 0)
                                <tr>
                                    <td colspan="5" class="px-4 py-1 text-right text-gray-600">ITBMS</td>
                                    <td class="px-4 py-1 text-right">B/. {{ number_format((float) $cotizacion->itbms, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="font-semibold">
                                <td colspan="5" class="px-4 py-2 text-right text-gray-700">Total</td>
                                <td class="px-4 py-2 text-right">B/. {{ number_format((float) $cotizacion->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($cotizacion->notas)
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Notas / términos</h3>
                    <p class="text-sm text-gray-600 whitespace-pre-line">{{ $cotizacion->notas }}</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
