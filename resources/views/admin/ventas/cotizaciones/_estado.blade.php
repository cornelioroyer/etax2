@php
    [$clase, $texto] = match ($estado) {
        'PENDIENTE' => ['bg-amber-100 text-amber-800', 'Pendiente'],
        'ACEPTADA' => ['bg-green-100 text-green-800', 'Aceptada'],
        'RECHAZADA' => ['bg-red-100 text-red-800', 'Rechazada'],
        'FACTURADA' => ['bg-blue-100 text-blue-800', 'Facturada'],
        'ANULADA' => ['bg-gray-200 text-gray-700', 'Anulada'],
        default => ['bg-gray-100 text-gray-800', ucfirst(strtolower($estado))],
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $clase }}">{{ $texto }}</span>
