<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Antigüedad de saldos — CxP</h2>
            <div class="flex items-center gap-2 print:hidden">
                <a href="{{ route('admin.cxp.antiguedad', array_merge(request()->query(), ['export' => 'xlsx'])) }}" class="rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 hover:bg-green-50">Excel</a>
                <a href="{{ route('admin.cxp.antiguedad', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50">PDF</a>
                <button onclick="window.print()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Imprimir</button>
            </div>
        </div>
    </x-slot>

    @php $ncols = count($columnas) + 2; @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg print:hidden">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <x-input-label for="corte" value="Fecha de corte" />
                        <x-text-input id="corte" name="corte" type="text" class="js-date mt-1 block w-44" :value="$corte->format('Y-m-d')" />
                    </div>
                    <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Actualizar</button>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="border-b border-gray-100 px-4 py-3 text-sm text-gray-600">
                    Facturas de proveedor con saldo al {{ $corte->format('d/m/Y') }} — la edad se mide en meses completos desde la fecha de vencimiento.
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Proveedor</th>
                                @foreach ($columnas as $clave => $titulo)
                                    <th class="px-4 py-3 text-right">{{ $titulo }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($proveedores as $fila)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $fila['proveedor']->nombre ?? '—' }}</td>
                                    @foreach ($columnas as $clave => $titulo)
                                        <td class="px-4 py-3 text-right {{ $loop->last && $fila[$clave] > 0 ? 'font-semibold text-red-700' : '' }}">{{ $fila[$clave] > 0 ? 'B/. '.number_format($fila[$clave], 2) : '—' }}</td>
                                    @endforeach
                                    <td class="px-4 py-3 text-right font-semibold">B/. {{ number_format($fila['total'], 2) }}</td>
                                </tr>
                                @foreach ($fila['facturas'] as $detalle)
                                    <tr class="bg-gray-50/60 text-xs text-gray-600">
                                        <td class="px-4 py-1.5 pl-8">
                                            <a href="{{ route('admin.cxp.facturas.show', $detalle['doc']) }}" class="text-blue-700 hover:underline">{{ $detalle['doc']->numero }}</a>
                                            · {{ $detalle['doc']->fecha->format('d/m/Y') }}
                                            @if ($detalle['doc']->fecha_vencimiento)
                                                · vence {{ $detalle['doc']->fecha_vencimiento->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        @foreach ($columnas as $clave => $titulo)
                                            <td class="px-4 py-1.5 text-right">{{ $detalle['cubeta'] === $clave ? number_format((float) $detalle['doc']->saldo, 2) : '' }}</td>
                                        @endforeach
                                        <td class="px-4 py-1.5"></td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ $ncols }}" class="px-4 py-10 text-center text-gray-500">No hay facturas con saldo pendiente. 🎉</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (count($proveedores))
                            <tfoot class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                                <tr>
                                    <td class="px-4 py-3">TOTAL</td>
                                    @foreach ($columnas as $clave => $titulo)
                                        <td class="px-4 py-3 text-right">B/. {{ number_format($totales[$clave], 2) }}</td>
                                    @endforeach
                                    <td class="px-4 py-3 text-right">B/. {{ number_format($totales['total'], 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
