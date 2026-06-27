<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Estado de cuenta — CxC</h2>
            @if ($cliente)
                <div class="flex items-center gap-2 print:hidden">
                    <a href="{{ route('admin.cxc.estado-cuenta', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                    <a href="{{ route('admin.cxc.estado-cuenta', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                    <button onclick="window.print()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Imprimir</button>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg print:hidden">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-64">
                        <x-buscador-contacto name="cliente_id" label="Cliente" :opciones="$clientes"
                            :selected="$cliente?->id" placeholder="Seleccione — código o nombre"
                            empty-label="— Seleccione un cliente —" mostrar-ruc compact />
                    </div>
                    <div>
                        <x-input-label for="desde" value="Desde" />
                        <x-text-input id="desde" name="desde" type="text" class="js-date mt-1 block w-44" :value="$desde->format('Y-m-d')" />
                    </div>
                    <div>
                        <x-input-label for="hasta" value="Hasta" />
                        <x-text-input id="hasta" name="hasta" type="text" class="js-date mt-1 block w-44" :value="$hasta->format('Y-m-d')" />
                    </div>
                    <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Consultar</button>
                </div>
            </form>

            @if (! $cliente)
                <div class="bg-white shadow-sm sm:rounded-lg px-4 py-10 text-center text-gray-500">
                    Seleccione un cliente para ver su estado de cuenta.
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm text-gray-600 flex flex-wrap justify-between gap-2">
                        <span><span class="font-semibold text-gray-900">{{ $cliente->nombre }}</span> — movimientos del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</span>
                        <span>Saldo final: <span class="font-semibold {{ (count($movimientos) ? end($movimientos)['saldo'] : $saldoInicial) > 0 ? 'text-red-700' : 'text-gray-900' }}">B/. {{ number_format(count($movimientos) ? end($movimientos)['saldo'] : $saldoInicial, 2) }}</span></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Documento</th>
                                    <th class="px-4 py-3">Tipo</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">Cargo</th>
                                    <th class="px-4 py-3 text-right">Abono</th>
                                    <th class="px-4 py-3 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="bg-gray-50/60 font-medium">
                                    <td class="px-4 py-2.5" colspan="6">Saldo inicial al {{ $desde->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2.5 text-right">B/. {{ number_format($saldoInicial, 2) }}</td>
                                </tr>
                                @forelse ($movimientos as $mov)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 whitespace-nowrap">{{ $mov['doc']->fecha->format('d/m/Y') }}</td>
                                        <td class="px-4 py-2.5">
                                            @if ($mov['cargo'] > 0)
                                                <a href="{{ route('admin.cxc.facturas.show', $mov['doc']) }}" class="text-blue-700 hover:underline">{{ $mov['doc']->numero }}</a>
                                            @else
                                                <a href="{{ route('admin.cxc.cobros.show', $mov['doc']) }}" class="text-blue-700 hover:underline">{{ $mov['doc']->numero }}</a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5">{{ $mov['cargo'] > 0 ? 'Factura' : 'Cobro' }}</td>
                                        <td class="px-4 py-2.5">@include('admin.cxc._estado', ['estado' => $mov['doc']->estado])</td>
                                        <td class="px-4 py-2.5 text-right">{{ $mov['cargo'] > 0 ? 'B/. '.number_format($mov['cargo'], 2) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right">{{ $mov['abono'] > 0 ? 'B/. '.number_format($mov['abono'], 2) : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-medium {{ $mov['saldo'] > 0 ? '' : 'text-green-700' }}">B/. {{ number_format($mov['saldo'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">Sin movimientos en el período.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (count($movimientos))
                                <tfoot class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                                    <tr>
                                        <td class="px-4 py-3" colspan="4">TOTAL DEL PERÍODO</td>
                                        <td class="px-4 py-3 text-right">B/. {{ number_format($totalCargos, 2) }}</td>
                                        <td class="px-4 py-3 text-right">B/. {{ number_format($totalAbonos, 2) }}</td>
                                        <td class="px-4 py-3 text-right">B/. {{ number_format(end($movimientos)['saldo'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
