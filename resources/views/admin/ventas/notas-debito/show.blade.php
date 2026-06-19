<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nota de débito {{ $nota->numero }}</h2>
            <a href="{{ route('admin.ventas.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ $nota->numero }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ $nota->fecha->format('d/m/Y') }}</p>
                        @php $tf = data_get($nota->extra, 'tipo_fel'); @endphp
                        @if ($tf)<p class="text-xs text-gray-400 mt-0.5">DGI {{ $tf }} — {{ \App\Services\FelDocumentoBuilder::TIPOS_DOCUMENTO[$tf] ?? '' }}</p>@endif
                    </div>
                    @php
                        $colores = ['EMITIDA' => 'bg-amber-100 text-amber-800', 'PARCIAL' => 'bg-blue-100 text-blue-800', 'PAGADA' => 'bg-green-100 text-green-700', 'ANULADA' => 'bg-red-100 text-red-700'];
                    @endphp
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $colores[$nota->estado] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst(strtolower($nota->estado)) }}
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div><dt class="text-gray-500">Cliente</dt><dd class="font-medium">{{ $nota->cliente?->nombre }}</dd></div>
                    <div><dt class="text-gray-500">Total</dt><dd class="font-semibold text-base">B/. {{ number_format((float) $nota->total, 2) }}</dd></div>
                    <div><dt class="text-gray-500">Saldo</dt><dd class="font-medium">B/. {{ number_format((float) $nota->saldo, 2) }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500">Motivo</dt><dd class="font-medium">{{ $nota->motivo }}</dd></div>
                    @if ($nota->asiento)
                        <div><dt class="text-gray-500">Asiento</dt><dd><a href="{{ route('admin.asientos.show', $nota->asiento) }}" class="text-blue-600 hover:underline text-xs">{{ $nota->asiento->numero }}</a></dd></div>
                    @endif
                    @if ($nota->cxcDocumento)
                        <div><dt class="text-gray-500">Doc. CxC</dt><dd class="font-mono text-xs">{{ $nota->cxcDocumento->numero }}</dd></div>
                    @endif
                </dl>
            </div>

            @can('ventas.gestionar')
                @if (! $nota->esAnulada())
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('admin.ventas.notas-debito.anular', $nota) }}"
                            onsubmit="return confirm('¿Anular nota de débito {{ $nota->numero }}?')">
                            @csrf
                            <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Anular nota</button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
