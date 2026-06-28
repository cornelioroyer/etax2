@php
    [$clase, $texto] = match ($estado) {
        'BORRADOR' => ['bg-gray-100 text-gray-800', 'Borrador'],
        'APROBADA' => ['bg-amber-100 text-amber-800', 'Aprobada'],
        'RECIBIDA_PARCIAL' => ['bg-sky-100 text-sky-800', 'Recibida parcial'],
        'RECIBIDA' => ['bg-green-100 text-green-800', 'Recibida'],
        'PARCIALMENTE_FACTURADA' => ['bg-indigo-100 text-indigo-800', 'Parcialmente facturada'],
        'FACTURADA' => ['bg-blue-100 text-blue-800', 'Facturada'],
        'CERRADA' => ['bg-slate-200 text-slate-700', 'Cerrada'],
        'ANULADA' => ['bg-gray-200 text-gray-700', 'Anulada'],
        default => ['bg-gray-100 text-gray-800', ucfirst(strtolower($estado))],
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $clase }}">{{ $texto }}</span>
