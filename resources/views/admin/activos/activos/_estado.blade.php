@php
    $map = [
        'ACTIVO'       => ['bg-green-100 text-green-800',  'Activo'],
        'DADO_DE_BAJA' => ['bg-red-100 text-red-800',      'Dado de baja'],
    ];
    [$cls, $lbl] = $map[$estado] ?? ['bg-gray-100 text-gray-700', $estado];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cls }}">{{ $lbl }}</span>
