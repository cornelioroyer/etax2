<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planilla — Planillas</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <div class="flex justify-end">
                @can('nomina.gestionar')
                <a href="{{ route('admin.nomina.planillas.create') }}"
                   class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color:#0d2d5e">+ Nueva planilla</a>
                @endcan
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Número</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Período</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Ingresos</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Deducciones</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Neto</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr class="{{ $item->estaAnulada() ? 'opacity-50' : '' }}">
                                <td class="px-4 py-2 font-mono font-semibold">
                                    <a href="{{ route('admin.nomina.planillas.show', $item) }}" class="text-indigo-600 hover:underline">{{ $item->numero }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $item->periodo?->etiqueta() }}</td>
                                <td class="px-4 py-2">{{ $item->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format((float) $item->total_ingresos, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format((float) $item->total_deducciones, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold">{{ number_format((float) $item->total_neto, 2) }}</td>
                                <td class="px-4 py-2 text-center">
                                    @php
                                        $color = match ($item->estado) {
                                            'CONTABILIZADA' => 'bg-green-100 text-green-800',
                                            'PROCESADA' => 'bg-yellow-100 text-yellow-800',
                                            'ANULADA' => 'bg-red-100 text-red-700',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $color }}">{{ $item->estado }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin planillas. Crea la primera con "+ Nueva planilla".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links() }}
        </div>
    </div>
</x-app-layout>
