<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturación Electrónica — Documentos</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800 break-all">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-600">
                    {{ $compania->nombre }}
                    @if ($config)
                        — ambiente <span class="font-semibold {{ $config->ambiente === 'PRODUCCION' ? 'text-red-700' : 'text-amber-700' }}">{{ $config->ambiente }}</span>
                    @else
                        — <span class="font-semibold text-red-700">sin configurar</span>
                    @endif
                </div>
                <div class="flex gap-2">
                    @can('fel.gestionar')
                        <a href="{{ route('admin.fel.configuracion') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Configuración</a>
                        <a href="{{ route('admin.fel.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Nueva factura</a>
                    @endcan
                </div>
            </div>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Número</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">ITBMS</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($documentos as $doc)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $doc->numero }}</td>
                                <td class="px-4 py-3">{{ $doc->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $doc->cliente->nombre ?? 'Consumidor final' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($doc->subtotal, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($doc->itbms, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($doc->total, 2) }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $badge = match ($doc->estado_fel) {
                                            'AUTORIZADO' => 'bg-green-100 text-green-800',
                                            'RECHAZADO' => 'bg-red-100 text-red-800',
                                            'ANULADO' => 'bg-gray-200 text-gray-700',
                                            default => 'bg-amber-100 text-amber-800',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">{{ $doc->estado_fel }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if ($doc->estado_fel === 'AUTORIZADO')
                                            <a href="{{ route('admin.fel.pdf', $doc) }}" target="_blank" class="text-blue-600 hover:text-blue-800">CAFE</a>
                                            @if ($doc->qr)
                                                <a href="{{ $doc->qr }}" target="_blank" class="text-blue-600 hover:text-blue-800">QR</a>
                                            @endif
                                            @can('fel.gestionar')
                                                <form method="POST" action="{{ route('admin.fel.anular', $doc) }}" onsubmit="return confirm('¿Anular la factura {{ $doc->numero }} ante la DGI?')">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:text-red-800">Anular</button>
                                                </form>
                                            @endcan
                                        @elseif ($doc->estado_fel === 'RECHAZADO')
                                            <span class="max-w-56 truncate text-xs text-red-600" title="{{ json_encode($doc->respuesta_dgi) }}">
                                                {{ data_get($doc->respuesta_dgi, 'EnviarResult.mensaje', data_get($doc->respuesta_dgi, 'mensaje', '')) }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">Aún no se han emitido documentos electrónicos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $documentos->links() }}
        </div>
    </div>
</x-app-layout>
