<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Matrícula — {{ $matricula->estudiante?->contacto?->nombre }}
            </h2>
            <a href="{{ route('admin.edu.matriculas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Matrículas</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Datos de la matrícula</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Estudiante</dt>
                        <dd class="font-semibold">{{ $matricula->estudiante?->contacto?->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Período</dt>
                        <dd>{{ $matricula->periodo?->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Institución</dt>
                        <dd>{{ $matricula->institucion?->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Sede</dt>
                        <dd>{{ $matricula->sede?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Nivel</dt>
                        <dd>{{ $matricula->nivel?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Programa</dt>
                        <dd>{{ $matricula->programa?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Grado</dt>
                        <dd>{{ $matricula->grado?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Grupo</dt>
                        <dd>{{ $matricula->grupo?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Estado</dt>
                        <dd class="capitalize font-semibold">{{ $matricula->estado }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Fecha matrícula</dt>
                        <dd>{{ $matricula->fecha_matricula?->format('d/m/Y') }}</dd>
                    </div>
                </dl>

                @can('edu.gestionar')
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <form method="POST" action="{{ route('admin.edu.matriculas.update', $matricula) }}" class="flex gap-3 items-end">
                        @csrf @method('PUT')
                        <input type="hidden" name="fecha_matricula" value="{{ $matricula->fecha_matricula?->format('Y-m-d') }}">
                        <input type="hidden" name="grado_id" value="{{ $matricula->grado_id }}">
                        <input type="hidden" name="grupo_id" value="{{ $matricula->grupo_id }}">
                        <div>
                            <x-input-label for="mat_estado_upd" value="Estado" />
                            <select id="mat_estado_upd" name="estado"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="activo" @selected($matricula->estado=='activo')>Activo</option>
                                <option value="retirado" @selected($matricula->estado=='retirado')>Retirado</option>
                                <option value="egresado" @selected($matricula->estado=='egresado')>Egresado</option>
                                <option value="suspendido" @selected($matricula->estado=='suspendido')>Suspendido</option>
                            </select>
                        </div>
                        <x-primary-button>Actualizar estado</x-primary-button>
                    </form>
                </div>
                @endcan
            </div>

            {{-- Asignaturas --}}
            @if($matricula->detalles->count())
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Asignaturas matriculadas</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Asignatura</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Nota final</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($matricula->detalles as $det)
                        <tr>
                            <td class="px-4 py-2">{{ $det->asignatura?->nombre }}</td>
                            <td class="px-4 py-2 text-center capitalize">{{ $det->estado ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">{{ $det->nota_final ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @can('edu.gestionar')
            <div>
                <form method="POST" action="{{ route('admin.edu.matriculas.destroy', $matricula) }}"
                      onsubmit="return confirm('¿Eliminar esta matrícula?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-600 hover:underline">Eliminar matrícula</button>
                </form>
            </div>
            @endcan
        </div>
    </div>
</x-app-layout>
