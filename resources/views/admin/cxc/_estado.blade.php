@php
    [$clase, $texto] = match ($estado) {
        'BORRADOR' => ['bg-gray-200 text-gray-700', 'Borrador'],
        'PENDIENTE' => ['bg-amber-100 text-amber-800', 'Pendiente'],
        'PARCIAL' => ['bg-blue-100 text-blue-800', 'Parcial'],
        'PAGADO' => ['bg-green-100 text-green-800', 'Pagado'],
        'ANULADO' => ['bg-red-100 text-red-800', 'Anulado'],
        default => ['bg-gray-100 text-gray-800', ucfirst(strtolower($estado))],
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $clase }}">{{ $texto }}</span>
