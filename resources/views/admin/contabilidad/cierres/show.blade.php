<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cierre — {{ $cierre->periodo?->nombre }}</h2>
                <p class="text-sm text-gray-500">Estado: {{ $cierre->estado }}</p>
            </div>
            <div class="flex gap-3">
                @can('contabilidad.gestionar')
                    @if (! $cierre->estaCompletado())
                        <form method="POST" action="{{ route('admin.contabilidad.cierres.cerrar', $cierre) }}"
                              onsubmit="return confirm('¿Cerrar el período? Esta acción no se puede deshacer.')">
                            @csrf
                            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Cerrar período</button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('admin.contabilidad.cierres.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Período</span><p class="font-medium">{{ $cierre->periodo?->nombre }}</p></div>
                    <div><span class="text-gray-500">Estado</span>
                        @php $colores = ['PENDIENTE' => 'bg-yellow-100 text-yellow-700', 'COMPLETADO' => 'bg-green-100 text-green-700']; @endphp
                        <p><span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $colores[$cierre->estado] ?? 'bg-gray-100' }}">{{ $cierre->estado }}</span></p>
                    </div>
                    <div><span class="text-gray-500">Fecha cierre</span><p>{{ $cierre->fecha_cierre?->format('d/m/Y H:i') ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Creado</span><p>{{ $cierre->created_at->format('d/m/Y') }}</p></div>
                    @if ($cierre->observacion)
                        <div class="col-span-2"><span class="text-gray-500">Observación</span><p>{{ $cierre->observacion }}</p></div>
                    @endif
                </div>
            </div>

            @if ($cierre->detalle->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-700">Detalle de pasos</h3></div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Paso</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Observación</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cierre->detalle as $d)
                                <tr><td class="px-4 py-3 font-mono text-xs">{{ $d->paso }}</td>
                                    <td class="px-4 py-3 text-xs font-medium {{ $d->estado === 'COMPLETADO' ? 'text-green-700' : ($d->estado === 'ERROR' ? 'text-red-600' : 'text-yellow-600') }}">{{ $d->estado }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $d->observacion ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
