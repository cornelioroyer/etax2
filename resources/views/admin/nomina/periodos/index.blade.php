<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planilla — Períodos de pago</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-sm">
                    <a href="?anio={{ $anio - 1 }}" class="rounded border border-gray-300 bg-white px-2 py-1">‹</a>
                    <span class="font-semibold">{{ $anio }}</span>
                    <a href="?anio={{ $anio + 1 }}" class="rounded border border-gray-300 bg-white px-2 py-1">›</a>
                </div>
                @can('nomina.gestionar')
                <form method="POST" action="{{ route('admin.nomina.periodos.generar-anio') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="anio" value="{{ $anio }}">
                    <select name="tipo_planilla" class="rounded-md border-gray-300 text-sm">
                        @foreach (\App\Models\NomEmpleado::TIPOS_PLANILLA as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color:#0d2d5e">
                        Generar períodos de {{ $anio }}
                    </button>
                </form>
                @endcan
            </div>

            @can('nomina.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Configuración de nómina de la compañía</h3>
                <form method="POST" action="{{ route('admin.nomina.configuracion.update') }}" class="flex flex-wrap items-end gap-4">
                    @csrf @method('PUT')
                    <div>
                        <x-input-label value="Riesgo profesional (%)" />
                        <x-text-input name="riesgo_profesional" type="number" step="0.0001" min="0" max="20" class="mt-1 block w-40"
                            value="{{ old('riesgo_profesional', $config->riesgo_profesional) }}" required />
                        <p class="mt-1 text-xs text-gray-400">Prima del empleador según actividad (CSS).</p>
                    </div>
                    <div>
                        <x-input-label value="Tipo de planilla default" />
                        <select name="tipo_planilla_default" class="mt-1 block w-44 rounded-md border-gray-300 text-sm">
                            @foreach (\App\Models\NomEmpleado::TIPOS_PLANILLA as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected($config->tipo_planilla_default === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Guardar configuración</x-primary-button>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Nº</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Desde</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Hasta</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha de pago</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('nomina.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr class="{{ $item->estaAbierto() ? '' : 'opacity-60' }}">
                                <td class="px-4 py-2">{{ \App\Models\NomEmpleado::TIPOS_PLANILLA[$item->tipo_planilla] ?? $item->tipo_planilla }}</td>
                                <td class="px-4 py-2 text-center font-mono">{{ $item->numero }}</td>
                                <td class="px-4 py-2">{{ $item->desde->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $item->hasta->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $item->fecha_pago->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $item->estaAbierto() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $item->estado }}
                                    </span>
                                </td>
                                @can('nomina.gestionar')
                                <td class="px-4 py-2 text-right">
                                    @if ($item->estaAbierto())
                                        <form method="POST" action="{{ route('admin.nomina.periodos.cerrar', $item) }}" class="inline">
                                            @csrf
                                            <button class="text-xs text-gray-500 hover:underline">Cerrar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.nomina.periodos.reabrir', $item) }}" class="inline">
                                            @csrf
                                            <button class="text-xs text-indigo-600 hover:underline">Reabrir</button>
                                        </form>
                                    @endif
                                </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">
                                Sin períodos en {{ $anio }}. Usa "Generar períodos" para crear el calendario del año.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
