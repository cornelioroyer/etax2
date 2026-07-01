@php $esCredito = $nota->tipo_documento === \App\Models\CxpDocumento::TIPO_NOTA_CREDITO; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $esCredito ? 'Nota de crédito' : 'Nota de débito' }} {{ $nota->numero }} — CxP
            </h2>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 print:hidden">Imprimir</button>
                @can('cxp.gestionar')
                    @if ($nota->esBorrador())
                        <form method="POST" action="{{ route('admin.cxp.notas.contabilizar', $nota) }}" class="print:hidden">
                            @csrf
                            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Contabilizar</button>
                        </form>
                        <form method="POST" action="{{ route('admin.cxp.notas.destroy', $nota) }}" onsubmit="return confirm('¿Eliminar este borrador?');" class="print:hidden">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm text-red-700 hover:bg-red-50">Eliminar</button>
                        </form>
                    @elseif (! $nota->esAnulado())
                        <form method="POST" action="{{ route('admin.cxp.notas.anular', $nota) }}" onsubmit="return confirm('¿Anular esta nota? Se revertirá su asiento y los saldos afectados.');" class="print:hidden">
                            @csrf
                            <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm text-red-700 hover:bg-red-50">Anular</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800 print:hidden">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 print:hidden">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-gray-500">Proveedor</div>
                    <div class="font-medium text-gray-900">{{ $nota->proveedor->nombre ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Tipo</div>
                    <div><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $esCredito ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $esCredito ? 'Crédito (reduce deuda)' : 'Débito (aumenta deuda)' }}</span></div>
                </div>
                <div>
                    <div class="text-gray-500">Fecha</div>
                    <div class="font-medium text-gray-900">{{ $nota->fecha->format('d/m/Y') }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Estado</div>
                    <div>@include('admin.cxc._estado', ['estado' => $nota->estado])</div>
                </div>
                @if ($esCredito)
                    <div>
                        <div class="text-gray-500">Disponible</div>
                        <div class="font-semibold {{ (float) $nota->saldo > 0 ? 'text-emerald-700' : 'text-gray-900' }}">B/. {{ number_format((float) $nota->saldo, 2) }}</div>
                    </div>
                @endif
                <div class="sm:col-span-2 border-t border-gray-100 pt-4 grid grid-cols-3 gap-4">
                    <div><div class="text-gray-500">Subtotal</div><div class="font-medium">B/. {{ number_format((float) $nota->subtotal, 2) }}</div></div>
                    <div><div class="text-gray-500">ITBMS</div><div class="font-medium">B/. {{ number_format((float) $nota->impuesto, 2) }}</div></div>
                    <div><div class="text-gray-500">Total</div><div class="font-semibold text-lg">B/. {{ number_format((float) $nota->total, 2) }}</div></div>
                </div>
            </div>

            @can('cxp.gestionar')
                @if ($esCredito && ! $nota->esAnulado() && (float) $nota->saldo > 0)
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="mb-1 text-sm font-semibold text-gray-700">Reembolsar en efectivo</h3>
                        <p class="mb-4 text-xs text-gray-500">El proveedor devuelve en efectivo/banco el crédito disponible, en vez de aplicarlo a una factura futura.</p>
                        <form method="POST" action="{{ route('admin.cxp.notas.reembolsar', $nota) }}" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label for="fecha" value="Fecha *" />
                                    <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" :value="old('fecha', now()->format('Y-m-d'))" />
                                </div>
                                <div>
                                    <x-buscador-contacto name="cuenta_pago_id" label="Cuenta que recibe el efectivo *" required
                                        :opciones="$cuentasPago" :selected="old('cuenta_pago_id')"
                                        placeholder="Buscar cuenta por código o nombre" />
                                    <x-input-error :messages="$errors->get('cuenta_pago_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="monto" value="Monto a reembolsar *" />
                                    <x-text-input id="monto" name="monto" type="number" step="0.01" min="0.01"
                                                  max="{{ (float) $nota->saldo }}" class="mt-1 block w-full"
                                                  :value="old('monto', number_format((float) $nota->saldo, 2, '.', ''))" />
                                    <x-input-error :messages="$errors->get('monto')" class="mt-1" />
                                </div>
                            </div>
                            <div class="sm:w-1/3">
                                <x-input-label for="referencia" value="Referencia (opcional)" />
                                <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                                              :value="old('referencia')" maxlength="100" placeholder="N.° de cheque, transferencia…" />
                            </div>
                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                                <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    Registrar reembolso
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            @endcan

            @if ($nota->detalle->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-700">Detalle</div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Descripción</th>
                                <th class="px-4 py-2 text-right">Cant.</th>
                                <th class="px-4 py-2 text-right">Precio</th>
                                <th class="px-4 py-2 text-right">ITBMS</th>
                                <th class="px-4 py-2">Cuenta</th>
                                <th class="px-4 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($nota->detalle as $det)
                                <tr>
                                    <td class="px-4 py-2">{{ $det->descripcion }}</td>
                                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format((float) $det->cantidad, 2), '0'), '.') }}</td>
                                    <td class="px-4 py-2 text-right">B/. {{ number_format((float) $det->precio_unitario, 2) }}</td>
                                    <td class="px-4 py-2 text-right">B/. {{ number_format((float) $det->impuesto_monto, 2) }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $det->cuenta->codigo ?? '' }} — {{ $det->cuenta->nombre ?? '' }}</td>
                                    <td class="px-4 py-2 text-right">B/. {{ number_format((float) $det->total_linea, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($nota->aplicacionesComoOrigen->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-700">Aplicada a</div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($nota->aplicacionesComoOrigen as $apl)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('admin.cxp.facturas.show', $apl->destino) }}" class="text-blue-700 hover:underline">{{ $apl->destino->numero }}</a>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">B/. {{ number_format((float) $apl->monto_aplicado, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($reembolsos->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-700">Reembolsos en efectivo</div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Fecha</th>
                                <th class="px-4 py-2">Asiento</th>
                                <th class="px-4 py-2 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($reembolsos as $asientoReembolso)
                                <tr>
                                    <td class="px-4 py-2">{{ $asientoReembolso->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('admin.asientos.show', $asientoReembolso) }}" class="text-blue-700 hover:underline">{{ $asientoReembolso->numero }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-right">B/. {{ number_format((float) $asientoReembolso->total_debito, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($nota->asiento)
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-700">
                        Asiento contable
                        <a href="{{ route('admin.asientos.show', $nota->asiento) }}" class="text-blue-700 hover:underline font-normal">{{ $nota->asiento->numero }}</a>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Cuenta</th>
                                <th class="px-4 py-2 text-right">Débito</th>
                                <th class="px-4 py-2 text-right">Crédito</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($nota->asiento->detalle as $det)
                                <tr>
                                    <td class="px-4 py-2">{{ $det->cuenta->codigo ?? '' }} — {{ $det->cuenta->nombre ?? $det->descripcion }}</td>
                                    <td class="px-4 py-2 text-right">{{ (float) $det->debito > 0 ? 'B/. '.number_format((float) $det->debito, 2) : '' }}</td>
                                    <td class="px-4 py-2 text-right">{{ (float) $det->credito > 0 ? 'B/. '.number_format((float) $det->credito, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <a href="{{ route('admin.cxp.notas.index') }}" class="inline-block text-sm text-gray-600 hover:text-gray-900 print:hidden">← Volver a notas</a>
        </div>
    </div>
</x-app-layout>
