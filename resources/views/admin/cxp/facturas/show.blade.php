@php
    $tipoLabel = $factura->etiquetaTipo();
    $tipoLabelLow = mb_strtolower($tipoLabel);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $tipoLabel }} {{ $factura->numero }}</h2>
            <a href="{{ route('admin.cxp.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
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

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <dl class="grid grid-cols-2 gap-x-10 gap-y-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-gray-500">Proveedor</dt>
                            <dd class="font-medium text-gray-900">{{ $factura->proveedor->nombre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Fecha</dt>
                            <dd class="font-medium text-gray-900">{{ $factura->fecha->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Vence</dt>
                            <dd class="font-medium text-gray-900">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Estado</dt>
                            <dd>@include('admin.cxc._estado', ['estado' => $factura->estado])</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Asiento</dt>
                            <dd class="font-medium">
                                @if ($factura->asiento)
                                    <a href="{{ route('admin.asientos.show', $factura->asiento) }}" class="text-blue-700 hover:underline">{{ $factura->asiento->numero }}</a>
                                @elseif ($factura->esBorrador())
                                    <span class="text-gray-500">Sin contabilizar</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Saldo</dt>
                            <dd class="text-lg font-bold text-[#0d2d5e]">B/. {{ number_format((float) $factura->saldo, 2) }}</dd>
                        </div>
                        @if ($factura->compraOrden)
                            <div>
                                <dt class="text-gray-500">Orden de compra</dt>
                                <dd class="font-medium">
                                    <a href="{{ route('admin.compras.ordenes.show', $factura->compraOrden) }}" class="text-blue-700 hover:underline">{{ $factura->compraOrden->numero }}</a>
                                </dd>
                            </div>
                        @endif
                        @if ($factura->archivo_path || ($factura->cufe && strlen($factura->cufe) === 66))
                            <div>
                                <dt class="text-gray-500">Factura física</dt>
                                <dd class="font-medium">
                                    <a href="{{ route('admin.cxp.facturas.archivo', $factura) }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline">
                                        @if ($factura->archivo_path && ! str_ends_with($factura->archivo_path, '.pdf')) Ver foto @else Ver PDF (DGI) @endif →
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($factura->archivo_path || ($factura->cufe && strlen($factura->cufe) === 66))
                            <a href="{{ route('admin.cxp.facturas.archivo', $factura) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z" />
                                </svg>
                                Ver factura física
                            </a>
                        @endif
                        @if ($factura->asiento)
                            <a href="{{ route('admin.asientos.show', $factura->asiento) }}"
                               class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                                Ver asiento
                            </a>
                        @endif
                        @if ($factura->proveedor_id)
                            <a href="{{ route('admin.contactos.edit', $factura->proveedor_id) }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Editar proveedor
                            </a>
                        @endif
                        @can('cxp.gestionar')
                            @if ($factura->esBorrador())
                                <a href="{{ route('admin.cxp.facturas.edit', $factura) }}"
                                   class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Editar
                                </a>
                                <form method="POST" action="{{ route('admin.cxp.facturas.contabilizar', $factura) }}"
                                      onsubmit="return confirm('¿Contabilizar {{ $tipoLabelLow }} {{ $factura->numero }}? Se generará el asiento contable y ya no podrá editarse.');">
                                    @csrf
                                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                                        Contabilizar
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.cxp.facturas.destroy', $factura) }}"
                                      onsubmit="return confirm('¿Eliminar el borrador {{ $factura->numero }}? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                        Eliminar
                                    </button>
                                </form>
                            @elseif (! $factura->esAnulado())
                                @unless ($factura->aplicacionesComoDestino()->exists())
                                    {{-- "Editar" en una factura contabilizada: por dentro crea una versión
                                         borrador (anula la actual y revierte su asiento) para poder modificarla. --}}
                                    <form method="POST" action="{{ route('admin.cxp.facturas.corregir', $factura) }}"
                                          onsubmit="return confirm('Para editar {{ $tipoLabelLow }} {{ $factura->numero }} se creará una versión en borrador y la actual se reemplazará (se revierte su asiento). ¿Continuar?');">
                                        @csrf
                                        <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                            Editar
                                        </button>
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.cxp.facturas.anular', $factura) }}"
                                      onsubmit="return confirm('¿Anular {{ $tipoLabelLow }} {{ $factura->numero }}? También se anulará su asiento contable.');">
                                    @csrf
                                    <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                        Anular {{ $tipoLabelLow }}
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>

            {{-- Detalle --}}
            @php
                $mostrarColumnaAfi = $factura->esBorrador() === false
                    && $factura->esAnulado() === false
                    && in_array($factura->tipo_documento, \App\Models\CxpDocumento::tiposFacturaCargo());
            @endphp
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
                                <th class="px-4 py-3 hidden md:table-cell">Cuenta</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                @can('activos.gestionar')
                                    @if ($mostrarColumnaAfi)
                                        <th class="px-4 py-3 text-center">Activo fijo</th>
                                    @endif
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($factura->detalle as $linea)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500">{{ $linea->linea }}</td>
                                    <td class="px-4 py-3">{{ $linea->descripcion }}</td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float) $linea->precio_unitario, 2) }}</td>
                                    <td class="px-4 py-3 text-right">B/. {{ number_format((float) $linea->impuesto_monto, 2) }}</td>
                                    <td class="px-4 py-3 hidden md:table-cell text-gray-600">{{ $linea->cuenta ? $linea->cuenta->codigo.' — '.$linea->cuenta->nombre : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float) $linea->total_linea, 2) }}</td>
                                    @can('activos.gestionar')
                                        @if ($mostrarColumnaAfi)
                                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                                @if ($activo = $activosPorDetalle[$linea->id] ?? null)
                                                    <a href="{{ route('admin.activos.show', $activo) }}"
                                                       class="text-xs font-medium text-green-700 hover:underline">
                                                        ✓ {{ $activo->codigo }}
                                                    </a>
                                                @elseif ($linea->cuenta && optional($linea->cuenta->tipo)->codigo === 'ACTIVO')
                                                    {{-- Solo cuentas de ACTIVO son capitalizables como activo fijo --}}
                                                    <a href="{{ route('admin.activos.create', ['desde_cxp_detalle' => $linea->id]) }}"
                                                       class="text-xs font-medium text-blue-600 hover:underline">
                                                        + Activo fijo
                                                    </a>
                                                @else
                                                    <span class="text-xs text-gray-300">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 text-sm">
                            <tr>
                                <td colspan="6" class="px-4 py-1 text-right text-gray-600">Subtotal</td>
                                <td class="px-4 py-1 text-right">B/. {{ number_format((float) $factura->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="6" class="px-4 py-1 text-right text-gray-600">ITBMS</td>
                                <td class="px-4 py-1 text-right">B/. {{ number_format((float) $factura->impuesto, 2) }}</td>
                            </tr>
                            <tr class="font-semibold">
                                <td colspan="6" class="px-4 py-2 text-right text-gray-700">Total</td>
                                <td class="px-4 py-2 text-right">B/. {{ number_format((float) $factura->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Pagos aplicados --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Pagos aplicados</h3>
                @if ($factura->aplicacionesComoDestino->isEmpty())
                    <p class="text-sm text-gray-500">Sin pagos aplicados todavía.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Pago</th>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4 text-right">Monto aplicado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($factura->aplicacionesComoDestino as $aplicacion)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <a href="{{ route('admin.cxp.pagos.show', $aplicacion->origen) }}" class="text-blue-700 hover:underline">{{ $aplicacion->origen->numero }}</a>
                                    </td>
                                    <td class="py-2 pr-4">{{ $aplicacion->fecha->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 text-right">B/. {{ number_format((float) $aplicacion->monto_aplicado, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
