<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Revaluaciones — {{ $activo->codigo }}</h2>
            <a href="{{ route('admin.activos.show', $activo) }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al activo</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @can('activos.gestionar')
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Nueva revaluación</h3>
                    <form method="POST" action="{{ route('admin.activos.revaluaciones.store', $activo) }}" class="flex flex-wrap gap-4 items-end">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
                            <input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required
                                   class="rounded-md border-gray-300 text-sm shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor anterior *</label>
                            <input type="number" name="valor_anterior" value="{{ old('valor_anterior') }}" step="0.01" min="0" required
                                   class="rounded-md border-gray-300 text-sm shadow-sm w-36" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor nuevo *</label>
                            <input type="number" name="valor_nuevo" value="{{ old('valor_nuevo') }}" step="0.01" min="0" required
                                   class="rounded-md border-gray-300 text-sm shadow-sm w-36" placeholder="0.00">
                        </div>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Registrar</button>
                    </form>
                </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Historial de revaluaciones</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3 text-right">Valor anterior</th>
                            <th class="px-4 py-3 text-right">Valor nuevo</th>
                            <th class="px-4 py-3 text-right">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($revaluaciones as $r)
                            @php $dif = (float)$r->valor_nuevo - (float)$r->valor_anterior; @endphp
                            <tr>
                                <td class="px-4 py-3">{{ $r->fecha->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-right">B/. {{ number_format((float)$r->valor_anterior, 2) }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float)$r->valor_nuevo, 2) }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $dif >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                    {{ $dif >= 0 ? '+' : '' }}B/. {{ number_format($dif, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin revaluaciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
