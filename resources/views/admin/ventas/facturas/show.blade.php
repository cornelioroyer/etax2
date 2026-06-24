<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                @if ($factura->estado === 'BORRADOR') Factura (Borrador) @else Factura {{ $factura->numero }} @endif
            </h2>
            <a href="{{ route('admin.ventas.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
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
                            <dd class="font-medium text-gray-900">{{ $factura->cliente->nombre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Fecha</dt>
                            <dd class="font-medium">{{ $factura->fecha->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Vence</dt>
                            <dd class="font-medium">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Estado</dt>
                            <dd>@include('admin.ventas.facturas._estado', ['estado' => $factura->estado])</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Saldo</dt>
                            <dd class="text-lg font-bold text-[#0d2d5e]">B/. {{ number_format((float) $factura->saldo, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Asiento</dt>
                            <dd>
                                @if ($factura->asiento)
                                    <a href="{{ route('admin.asientos.show', $factura->asiento) }}" class="text-blue-700 hover:underline">{{ $factura->asiento->numero }}</a>
                                @else —
                                @endif
                            </dd>
                        </div>
                        @if ($factura->cotizacion)
                            <div>
                                <dt class="text-gray-500">Cotización origen</dt>
                                <dd>
                                    <a href="{{ route('admin.ventas.cotizaciones.show', $factura->cotizacion) }}" class="text-blue-700 hover:underline">{{ $factura->cotizacion->numero }}</a>
                                </dd>
                            </div>
                        @endif
                        @if ($factura->cxcDocumento)
                            <div>
                                <dt class="text-gray-500">Documento CxC</dt>
                                <dd>
                                    <a href="{{ route('admin.cxc.facturas.show', $factura->cxcDocumento) }}" class="text-blue-700 hover:underline">{{ $factura->cxcDocumento->numero }}</a>
                                    <span class="ml-1 text-xs text-gray-400">(saldo B/. {{ number_format((float) $factura->cxcDocumento->saldo, 2) }})</span>
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">FEL</dt>
                            <dd>
                                @if ($factura->fel_documento_id)
                                    <span class="text-green-600 font-medium text-xs">Emitida</span>
                                @else
                                    <span class="text-gray-400 text-xs">Pendiente de envío</span>
                                @endif
                            </dd>
                        </div>
                        @if ($factura->cufe)
                            <div class="col-span-2 sm:col-span-3">
                                <dt class="text-gray-500">CUFE</dt>
                                <dd class="font-mono text-xs text-gray-700 break-all">
                                    <a href="https://dgi-fep.mef.gob.pa/Consultas/FacturasPorCUFE/{{ $factura->cufe }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline" title="Consultar en la DGI">{{ $factura->cufe }}</a>
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.ventas.facturas.imprimir', $factura) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                            </svg>
                            Imprimir / PDF
                        </a>
                        @if ($factura->asiento)
                            <a href="{{ route('admin.asientos.show', $factura->asiento) }}"
                               class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 3h4M5.25 4.5h13.5a.75.75 0 0 1 .75.75v15l-2.625-1.5L14.25 20.25l-2.25-1.5-2.25 1.5-2.625-1.5L4.5 20.25v-15a.75.75 0 0 1 .75-.75Z" />
                                </svg>
                                Ver asiento contable
                            </a>
                        @elseif ($factura->estado !== 'BORRADOR')
                            <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-400 cursor-default">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 3h4M5.25 4.5h13.5a.75.75 0 0 1 .75.75v15l-2.625-1.5L14.25 20.25l-2.25-1.5-2.25 1.5-2.625-1.5L4.5 20.25v-15a.75.75 0 0 1 .75-.75Z" />
                                </svg>
                                Sin asiento contable
                            </span>
                        @endif

                        @can('ventas.gestionar')
                        @if ($factura->estado === 'BORRADOR')
                                <a href="{{ route('admin.ventas.facturas.edit', $factura) }}"
                                   class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Editar
                                </a>
                                <form method="POST" action="{{ route('admin.ventas.facturas.emitir', $factura) }}"
                                      onsubmit="return confirm('¿Emitir la factura? Se asignará número correlativo y se creará el asiento contable.')">
                                    @csrf
                                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                                        Emitir factura
                                    </button>
                                </form>
                            @elseif (! $factura->esAnulada() && ! $factura->fel_documento_id)
                                @unless ($factura->cxcDocumento && $factura->cxcDocumento->aplicacionesComoDestino()->exists())
                                    {{-- "Editar" una factura emitida: por dentro crea una versión borrador
                                         (anula la actual y revierte su asiento) para poder modificarla. --}}
                                    <form method="POST" action="{{ route('admin.ventas.facturas.corregir', $factura) }}"
                                          onsubmit="return confirm('Para editar la factura {{ $factura->numero }} se creará una versión en borrador y la actual se anulará (se revierte su asiento). Al volver a emitir se asignará un número nuevo. ¿Continuar?')">
                                        @csrf
                                        <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                            Editar
                                        </button>
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.ventas.facturas.anular', $factura) }}"
                                      onsubmit="return confirm('¿Anular la factura {{ $factura->numero }}? También se anulará el asiento contable.')">
                                    @csrf
                                    <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                        Anular
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </div>
            </div>

            {{-- Notas --}}
            @can('ventas.gestionar')
                <div class="bg-white p-6 shadow-sm sm:rounded-lg" x-data="{ editing: false }">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Notas</h3>
                        @if (! $factura->esAnulada())
                            <button type="button" @click="editing = true" x-show="!editing"
                                    class="text-sm font-medium text-indigo-600 hover:underline">Editar</button>
                        @endif
                    </div>

                    <div x-show="!editing">
                        @if (!empty($factura->notas))
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $factura->notas }}</p>
                        @else
                            <p class="mt-2 text-sm text-gray-400">Sin notas.</p>
                        @endif
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.ventas.facturas.notas', $factura) }}" class="mt-3">
                        @csrf
                        <textarea name="notas" rows="3" maxlength="1000"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notas', $factura->notas) }}</textarea>
                        <div class="mt-2 flex gap-2">
                            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Guardar notas</button>
                            <button type="button" @click="editing = false"
                                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                        </div>
                    </form>
                </div>
            @elseif (!empty($factura->notas))
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700">Notas</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $factura->notas }}</p>
                </div>
            @endcan

            {{-- Asiento contable --}}
            @if ($factura->asiento)
                @php $asiento = $factura->asiento; $detalle = $asiento->detalle ?? collect(); @endphp
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">
                            Asiento contable —
                            <a href="{{ route('admin.asientos.show', $asiento) }}" class="text-indigo-600 hover:underline">{{ $asiento->numero }}</a>
                            <span class="ml-2 text-gray-400 font-normal">{{ $asiento->fecha->format('d/m/Y') }}</span>
                        </h3>
                        <a href="{{ route('admin.asientos.show', $asiento) }}"
                           class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 hover:underline">
                            Ver completo
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 10a.75.75 0 01.75-.75h6.638L10.23 7.29a.75.75 0 111.04-1.08l3.5 3.25a.75.75 0 010 1.08l-3.5 3.25a.75.75 0 11-1.04-1.08l2.158-1.96H5.75A.75.75 0 015 10z" clip-rule="evenodd"/></svg>
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-100">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2 text-left">Cuenta</th>
                                    <th class="px-4 py-2 text-left hidden md:table-cell">Descripción</th>
                                    <th class="px-4 py-2 text-right">Débito</th>
                                    <th class="px-4 py-2 text-right">Crédito</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($detalle as $linea)
                                    <tr>
                                        <td class="px-4 py-2 font-mono text-xs">
                                            {{ $linea->cuenta->codigo ?? '—' }}
                                            <span class="font-sans font-normal text-gray-600">{{ $linea->cuenta->nombre ?? '' }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500 hidden md:table-cell">{{ $linea->descripcion }}</td>
                                        <td class="px-4 py-2 text-right">
                                            @if ((float) $linea->debito > 0) B/. {{ number_format((float) $linea->debito, 2) }} @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            @if ((float) $linea->credito > 0) B/. {{ number_format((float) $linea->credito, 2) }} @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-200 text-xs font-semibold">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 text-right text-gray-500 hidden md:table-cell">Totales</td>
                                    <td class="px-4 py-2 text-right">B/. {{ number_format((float) $asiento->total_debito, 2) }}</td>
                                    <td class="px-4 py-2 text-right">B/. {{ number_format((float) $asiento->total_credito, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

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
                            @foreach ($factura->detalle as $linea)
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
                                <td class="px-4 py-1 text-right">B/. {{ number_format((float) $factura->subtotal, 2) }}</td>
                            </tr>
                            @if ((float) $factura->itbms > 0)
                                <tr>
                                    <td colspan="5" class="px-4 py-1 text-right text-gray-600">ITBMS</td>
                                    <td class="px-4 py-1 text-right">B/. {{ number_format((float) $factura->itbms, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="font-semibold">
                                <td colspan="5" class="px-4 py-2 text-right text-gray-700">Total</td>
                                <td class="px-4 py-2 text-right">B/. {{ number_format((float) $factura->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
