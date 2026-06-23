@php $esCredito = $nota->tipo_documento === \App\Models\CxcDocumento::TIPO_NOTA_CREDITO; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $esCredito ? 'Nota de crédito' : 'Nota de débito' }} {{ $nota->numero }} — CxC
            </h2>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 print:hidden">Imprimir</button>
                @if (! $nota->esAnulado())
                    @can('cxc.gestionar')
                        @if (! ($nota->tipo_documento === \App\Models\CxcDocumento::TIPO_NOTA_DEBITO && $nota->aplicacionesComoDestino()->exists()))
                            <form method="POST" action="{{ route('admin.cxc.notas.corregir', $nota) }}" onsubmit="return confirm('Para editar esta nota se anulará (revirtiendo su asiento y saldos) y se reabrirá el formulario con sus datos para registrar la corrección como una nota nueva. ¿Continuar?');" class="print:hidden">
                                @csrf
                                <button class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">Editar</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.cxc.notas.anular', $nota) }}" onsubmit="return confirm('¿Anular esta nota? Se revertirá su asiento y los saldos afectados.');" class="print:hidden">
                            @csrf
                            <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm text-red-700 hover:bg-red-50">Anular</button>
                        </form>
                    @endcan
                @endif
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
                    <div class="text-gray-500">Cliente</div>
                    <div class="font-medium text-gray-900">{{ $nota->cliente->nombre ?? '—' }}</div>
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
                <div class="sm:col-span-2 border-t border-gray-100 pt-4 grid grid-cols-3 gap-4">
                    <div><div class="text-gray-500">Subtotal</div><div class="font-medium">B/. {{ number_format((float) $nota->subtotal, 2) }}</div></div>
                    <div><div class="text-gray-500">ITBMS</div><div class="font-medium">B/. {{ number_format((float) $nota->impuesto, 2) }}</div></div>
                    <div><div class="text-gray-500">Total</div><div class="font-semibold text-lg">B/. {{ number_format((float) $nota->total, 2) }}</div></div>
                </div>
            </div>

            @if ($nota->aplicacionesComoOrigen->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-gray-700">Aplicada a</div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($nota->aplicacionesComoOrigen as $apl)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('admin.cxc.facturas.show', $apl->destino) }}" class="text-blue-700 hover:underline">{{ $apl->destino->numero }}</a>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">B/. {{ number_format((float) $apl->monto_aplicado, 2) }}</td>
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

            <a href="{{ route('admin.cxc.notas.index') }}" class="inline-block text-sm text-gray-600 hover:text-gray-900 print:hidden">← Volver a notas</a>
        </div>
    </div>
</x-app-layout>
