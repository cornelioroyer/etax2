<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobro {{ $cobro->numero }}</h2>
            <a href="{{ route('admin.cxc.cobros.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
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

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <dl class="grid grid-cols-2 gap-x-10 gap-y-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-gray-500">Cliente</dt>
                            <dd class="font-medium text-gray-900">{{ $cobro->cliente->nombre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Fecha</dt>
                            <dd class="font-medium text-gray-900">{{ $cobro->fecha->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Estado</dt>
                            <dd>
                                @if ($cobro->esAnulado())
                                    @include('admin.cxc._estado', ['estado' => 'ANULADO'])
                                @else
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Aplicado</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Asiento</dt>
                            <dd class="font-medium">
                                @if ($cobro->asiento)
                                    <a href="{{ route('admin.asientos.show', $cobro->asiento) }}" class="text-blue-700 hover:underline">{{ $cobro->asiento->numero }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Monto</dt>
                            <dd class="text-lg font-bold text-[#0d2d5e]">B/. {{ number_format((float) $cobro->total, 2) }}</dd>
                        </div>
                    </dl>

                    @can('cxc.gestionar')
                        @if (! $cobro->esAnulado())
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('admin.cxc.cobros.corregir', $cobro) }}"
                                      onsubmit="return confirm('Para editar el cobro {{ $cobro->numero }} se anulará (restaurando el saldo de las facturas y su asiento) y se reabrirá el formulario con sus datos para registrar la corrección como un cobro nuevo. ¿Continuar?');">
                                    @csrf
                                    <button class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                                        Editar
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.cxc.cobros.anular', $cobro) }}"
                                      onsubmit="return confirm('¿Anular el cobro {{ $cobro->numero }}? Se restaurará el saldo de las facturas y se anulará el asiento.');">
                                    @csrf
                                    <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                        Anular cobro
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endcan
                </div>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Facturas a las que se aplicó</h3>
                @if ($cobro->aplicacionesComoOrigen->isEmpty())
                    <p class="text-sm text-gray-500">Sin aplicaciones (cobro anulado).</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Factura</th>
                                <th class="py-2 pr-4">Fecha factura</th>
                                <th class="py-2 pr-4 text-right">Monto aplicado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cobro->aplicacionesComoOrigen as $aplicacion)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <a href="{{ route('admin.cxc.facturas.show', $aplicacion->destino) }}" class="text-blue-700 hover:underline">{{ $aplicacion->destino->numero }}</a>
                                    </td>
                                    <td class="py-2 pr-4">{{ $aplicacion->destino->fecha->format('d/m/Y') }}</td>
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
