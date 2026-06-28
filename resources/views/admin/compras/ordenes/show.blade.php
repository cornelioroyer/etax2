<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Orden de compra {{ $orden->numero }}
                @include('admin.compras.ordenes._estado', ['estado' => $orden->estado])
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.compras.ordenes.imprimir', $orden) }}" target="_blank"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Imprimir / PDF
                </a>
                <a href="{{ route('admin.compras.ordenes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
            </div>
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

            @php
                // Mapas de control por línea (excluyen recepciones anuladas).
                $recibidoMap = $orden->recibidoPorLinea();
                $facturableMap = $orden->facturablePorLinea();
            @endphp

            {{-- Cabecera --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><div class="text-gray-500">Proveedor</div><div class="font-medium">{{ $orden->proveedor->nombre ?? '—' }}</div></div>
                    <div><div class="text-gray-500">Fecha</div><div class="font-medium">{{ $orden->fecha->format('d/m/Y') }}</div></div>
                    <div><div class="text-gray-500">Total</div><div class="font-medium">B/. {{ number_format((float) $orden->total, 2) }}</div></div>
                    <div>
                        <div class="text-gray-500">Factura CxP</div>
                        <div class="font-medium">
                            @if ($orden->cxpDocumento)
                                <a href="{{ route('admin.cxp.facturas.show', $orden->cxpDocumento) }}" class="text-blue-700 hover:underline">{{ $orden->cxpDocumento->numero }}</a>
                            @else — @endif
                        </div>
                    </div>
                </div>

                @if ($orden->observaciones)
                    <div class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                        <span class="font-medium text-gray-500">Observaciones:</span> {{ $orden->observaciones }}
                    </div>
                @endif

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2 pr-2">Descripción</th>
                                <th class="w-24 py-2 pr-2 text-right">Cant.</th>
                                <th class="w-28 py-2 pr-2 text-right">Precio</th>
                                <th class="w-28 py-2 pr-2 text-right">Total</th>
                                <th class="w-24 py-2 pr-2 text-right">Recibida</th>
                                <th class="w-24 py-2 pr-2 text-right">Facturada</th>
                                <th class="w-24 py-2 pr-2 text-right">Pendiente</th>
                                <th class="py-2 pr-2 hidden md:table-cell">Cuenta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orden->detalle as $linea)
                                @php
                                    $facturada = (float) $linea->cantidad_facturada;
                                    // Pendiente de facturar contra lo ORDENADO (ordenada - facturada).
                                    $pendienteFact = round((float) $linea->cantidad - $facturada, 4);
                                @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-2">{{ $linea->descripcion }}</td>
                                    <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
                                    <td class="py-2 pr-2 text-right">{{ number_format((float) $linea->precio_unitario, 2) }}</td>
                                    <td class="py-2 pr-2 text-right">{{ number_format((float) $linea->total_linea, 2) }}</td>
                                    <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format((float) ($recibidoMap[$linea->id] ?? 0), 4), '0'), '.') }}</td>
                                    <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format($facturada, 4), '0'), '.') }}</td>
                                    <td class="py-2 pr-2 text-right @if ($pendienteFact > 0) font-medium text-amber-700 @else text-gray-400 @endif">{{ rtrim(rtrim(number_format($pendienteFact, 4), '0'), '.') }}</td>
                                    <td class="py-2 pr-2 text-gray-600 text-xs hidden md:table-cell">
                                        {{ $linea->cuenta ? $linea->cuenta->codigo.' — '.$linea->cuenta->nombre : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-gray-200 text-sm">
                            <tr><td colspan="3" class="py-1 pr-2 text-right text-gray-600">Subtotal</td><td class="py-1 pr-2 text-right">{{ number_format((float) $orden->subtotal, 2) }}</td><td colspan="4"></td></tr>
                            <tr><td colspan="3" class="py-1 pr-2 text-right text-gray-600">ITBMS</td><td class="py-1 pr-2 text-right">{{ number_format((float) $orden->itbms, 2) }}</td><td colspan="4"></td></tr>
                            <tr class="font-semibold"><td colspan="3" class="py-2 pr-2 text-right text-gray-700">Total</td><td class="py-2 pr-2 text-right">{{ number_format((float) $orden->total, 2) }}</td><td colspan="4"></td></tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Acciones de estado --}}
                @can('compras.gestionar')
                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                        @if ($orden->estado === \App\Models\CompraOrden::ESTADO_BORRADOR)
                            <a href="{{ route('admin.compras.ordenes.edit', $orden) }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Editar
                            </a>
                            <form method="POST" action="{{ route('admin.compras.ordenes.aprobar', $orden) }}">
                                @csrf
                                <button class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">Aprobar orden</button>
                            </form>
                        @endif

                        @if ($orden->estado !== \App\Models\CompraOrden::ESTADO_FACTURADA && $orden->estado !== \App\Models\CompraOrden::ESTADO_ANULADA)
                            <form method="POST" action="{{ route('admin.compras.ordenes.anular', $orden) }}"
                                  onsubmit="return confirm('¿Anular la orden {{ $orden->numero }}?');">
                                @csrf
                                <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm text-red-700 hover:bg-red-50">Anular</button>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>

            {{-- Recepción de mercancía --}}
            @can('compras.gestionar')
                @if ($orden->esRecibible())
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Registrar recepción</h3>
                        <form method="POST" action="{{ route('admin.compras.ordenes.recepciones.store', $orden) }}">
                            @csrf
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label for="rec_fecha" value="Fecha *" />
                                    <x-text-input id="rec_fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required :value="now()->format('Y-m-d')" />
                                </div>
                                @if ($almacenes->isNotEmpty())
                                    <div class="sm:col-span-2">
                                        <x-buscador-contacto name="almacen_id" label="Almacén (entrada a inventario)"
                                            :opciones="$almacenes" :selected="old('almacen_id')"
                                            placeholder="Buscar por código o nombre" empty-label="— Almacén —" />
                                        <p class="mt-1 text-xs text-gray-500">Las líneas con producto inventariable subirán las existencias a este almacén. Si se deja vacío se usa el primer almacén activo.</p>
                                    </div>
                                @endif
                            </div>
                            <table class="mt-4 min-w-full text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="py-2 pr-2">Descripción</th>
                                        <th class="w-28 py-2 pr-2 text-right">Pendiente</th>
                                        <th class="w-32 py-2 pr-2 text-right">Recibir ahora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($orden->detalle as $i => $linea)
                                        @php $pend = round((float) $linea->cantidad - (float) ($recibidoMap[$linea->id] ?? 0), 4); @endphp
                                        <tr class="border-t border-gray-100 @if ($pend <= 0) opacity-50 @endif">
                                            <td class="py-2 pr-2">{{ $linea->descripcion }}</td>
                                            <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format($pend, 4), '0'), '.') }}</td>
                                            <td class="py-2 pr-2">
                                                <input type="hidden" name="lineas[{{ $i }}][orden_detalle_id]" value="{{ $linea->id }}">
                                                <input type="number" step="0.0001" min="0" max="{{ $pend }}" name="lineas[{{ $i }}][cantidad]"
                                                       value="0" @disabled($pend <= 0)
                                                       class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="mt-4">
                                <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Registrar recepción</button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Facturar --}}
                @if ($orden->esFacturable())
                    @php
                        // El controlador de la orden no inyecta estas variables; se resuelven aquí.
                        // Cuentas de banco/caja para pago al contado/tarjeta (mismo criterio que CxP).
                        $cuentasPago = \App\Models\CuentaContable::where('compania_id', $orden->compania_id)
                            ->where('activa', true)
                            ->where('permite_movimiento', true)
                            ->orderBy('codigo')
                            ->get(['id', 'codigo', 'nombre']);
                        // Forma de pago por defecto = la del proveedor.
                        $formaPagoDefault = ($orden->proveedor->forma_pago ?? null) === \App\Models\Contacto::FORMA_PAGO_CONTADO ? 'CONTADO' : 'CREDITO';
                        $formaPagoOld = old('forma_pago', $formaPagoDefault);
                    @endphp
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg"
                         x-data="{ forma: '{{ $formaPagoOld }}', get contado() { return this.forma === 'CONTADO' || this.forma === 'TARJETA'; } }">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Generar factura de compra (CxP)</h3>
                        <p class="mb-4 text-xs text-gray-500">Se factura por línea hasta lo facturable (bienes: lo recibido no facturado; servicios: lo ordenado no facturado). Puedes facturar parcialmente y emitir varias facturas.</p>
                        <form method="POST" action="{{ route('admin.compras.ordenes.facturar', $orden) }}">
                            @csrf
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label for="fac_numero" value="N° factura proveedor *" />
                                    <x-text-input id="fac_numero" name="numero" type="text" class="mt-1 block w-full" required :value="old('numero')" />
                                    <x-input-error :messages="$errors->get('numero')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="fac_fecha" value="Fecha *" />
                                    <x-text-input id="fac_fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required :value="old('fecha', now()->format('Y-m-d'))" />
                                    <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                                </div>
                                <div x-show="! contado">
                                    <x-input-label for="fac_venc" value="Vence" />
                                    <x-text-input id="fac_venc" name="fecha_vencimiento" type="text" class="js-date mt-1 block w-full" :value="old('fecha_vencimiento')" />
                                    <x-input-error :messages="$errors->get('fecha_vencimiento')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Forma de pago + cuenta de banco/caja (requerida al contado/tarjeta) --}}
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label for="fac_forma_pago" value="Forma de pago *" />
                                    <select id="fac_forma_pago" name="forma_pago" x-model="forma"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="CREDITO">Crédito — Cuenta por pagar</option>
                                        <option value="CONTADO">Contado — Banco / Caja</option>
                                        <option value="TARJETA">Tarjeta de crédito</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('forma_pago')" class="mt-1" />
                                </div>
                                <div class="sm:col-span-2" x-show="contado" x-cloak>
                                    <x-buscador-contacto name="cuenta_pago_id" label="Cuenta de banco / caja / tarjeta *"
                                        :opciones="$cuentasPago" :selected="old('cuenta_pago_id')"
                                        placeholder="Buscar cuenta por código o nombre" empty-label="— Cuenta —" />
                                    <x-input-error :messages="$errors->get('cuenta_pago_id')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Líneas facturables (solo las que tienen cantidad facturable > 0) --}}
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="py-2 pr-2">Descripción</th>
                                            <th class="w-20 py-2 pr-2 text-right">Ordenada</th>
                                            <th class="w-20 py-2 pr-2 text-right">Recibida</th>
                                            <th class="w-20 py-2 pr-2 text-right">Facturada</th>
                                            <th class="w-24 py-2 pr-2 text-right">Facturable</th>
                                            <th class="w-28 py-2 pr-2 text-right">Cant. a facturar</th>
                                            <th class="w-28 py-2 pr-2 text-right">Costo unitario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $iFac = 0; @endphp
                                        @foreach ($orden->detalle as $linea)
                                            @php $facturable = (float) ($facturableMap[$linea->id] ?? 0); @endphp
                                            @if ($facturable > 0.0001)
                                                <tr class="border-t border-gray-100">
                                                    <td class="py-2 pr-2">{{ $linea->descripcion }}</td>
                                                    <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4), '0'), '.') }}</td>
                                                    <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format((float) ($recibidoMap[$linea->id] ?? 0), 4), '0'), '.') }}</td>
                                                    <td class="py-2 pr-2 text-right">{{ rtrim(rtrim(number_format((float) $linea->cantidad_facturada, 4), '0'), '.') }}</td>
                                                    <td class="py-2 pr-2 text-right font-medium">{{ rtrim(rtrim(number_format($facturable, 4), '0'), '.') }}</td>
                                                    <td class="py-2 pr-2">
                                                        <input type="hidden" name="lineas[{{ $iFac }}][orden_detalle_id]" value="{{ $linea->id }}">
                                                        <input type="number" step="0.0001" min="0" max="{{ $facturable }}"
                                                               name="lineas[{{ $iFac }}][cantidad]"
                                                               value="{{ old('lineas.'.$iFac.'.cantidad', rtrim(rtrim(number_format($facturable, 4, '.', ''), '0'), '.')) }}"
                                                               class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </td>
                                                    <td class="py-2 pr-2">
                                                        <input type="number" step="0.0001" min="0"
                                                               name="lineas[{{ $iFac }}][precio_unitario]"
                                                               value="{{ old('lineas.'.$iFac.'.precio_unitario', rtrim(rtrim(number_format((float) $linea->precio_unitario, 4, '.', ''), '0'), '.')) }}"
                                                               class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </td>
                                                </tr>
                                                @php $iFac++; @endphp
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <x-input-error :messages="$errors->get('lineas')" class="mt-2" />

                            {{-- Autorización de diferencia de costo respecto a la orden --}}
                            <div class="mt-4 rounded-md bg-gray-50 p-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="autorizar_diferencia" value="1" @checked(old('autorizar_diferencia'))
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    Autorizar diferencia de costo respecto a la orden
                                </label>
                                <div class="mt-2">
                                    <x-text-input name="motivo_diferencia" type="text" class="block w-full text-sm"
                                                  placeholder="Motivo de la diferencia (opcional)" :value="old('motivo_diferencia')" />
                                    <x-input-error :messages="$errors->get('motivo_diferencia')" class="mt-1" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Generar factura CxP</button>
                            </div>
                        </form>
                    </div>
                @endif
            @endcan

            {{-- Historial de recepciones --}}
            @if ($orden->recepciones->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Recepciones</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2 pr-2">Número</th>
                                <th class="py-2 pr-2">Fecha</th>
                                <th class="py-2 pr-2 text-right">Líneas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orden->recepciones as $recepcion)
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-2 font-medium">
                                        <a href="{{ route('admin.compras.ordenes.recepciones.show', [$orden, $recepcion]) }}" class="text-blue-700 hover:underline">{{ $recepcion->numero }}</a>
                                    </td>
                                    <td class="py-2 pr-2">{{ $recepcion->fecha->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-2 text-right">{{ $recepcion->detalle->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Facturas (CxP) generadas desde esta orden --}}
            @php $facturasOrden = $orden->cxpDocumentos()->orderBy('fecha')->orderBy('numero')->get(); @endphp
            @if ($facturasOrden->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Facturas de esta orden</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2 pr-2">Número</th>
                                <th class="py-2 pr-2">Fecha</th>
                                <th class="py-2 pr-2 text-right">Total</th>
                                <th class="py-2 pr-2 text-right">Saldo</th>
                                <th class="py-2 pr-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($facturasOrden as $factura)
                                @php
                                    [$facClase, $facTexto] = match ($factura->estado) {
                                        'BORRADOR'  => ['bg-gray-100 text-gray-800', 'Borrador'],
                                        'PENDIENTE' => ['bg-amber-100 text-amber-800', 'Pendiente'],
                                        'PARCIAL'   => ['bg-sky-100 text-sky-800', 'Parcial'],
                                        'PAGADO'    => ['bg-green-100 text-green-800', 'Pagada'],
                                        'ANULADO'   => ['bg-gray-200 text-gray-700', 'Anulada'],
                                        default     => ['bg-gray-100 text-gray-800', ucfirst(strtolower((string) $factura->estado))],
                                    };
                                @endphp
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-2 font-medium">
                                        <a href="{{ route('admin.cxp.facturas.show', $factura) }}" class="text-blue-700 hover:underline">{{ $factura->numero }}</a>
                                    </td>
                                    <td class="py-2 pr-2">{{ $factura->fecha->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-2 text-right">B/. {{ number_format((float) $factura->total, 2) }}</td>
                                    <td class="py-2 pr-2 text-right">B/. {{ number_format((float) $factura->saldo, 2) }}</td>
                                    <td class="py-2 pr-2">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $facClase }}">{{ $facTexto }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
