<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Estudiante: {{ $estudiante->contacto?->nombre }}
            </h2>
            <a href="{{ route('admin.edu.estudiantes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Estudiantes</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Datos generales --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Datos del estudiante</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Código</dt>
                        <dd class="font-mono">{{ $estudiante->codigo_estudiante ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Institución</dt>
                        <dd>{{ $estudiante->institucion?->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Estado</dt>
                        <dd class="capitalize">{{ $estudiante->estado }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Fecha ingreso</dt>
                        <dd>{{ $estudiante->fecha_ingreso ? \Carbon\Carbon::parse($estudiante->fecha_ingreso)->format('d/m/Y') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Nombre</dt>
                        <dd class="font-semibold">{{ $estudiante->contacto?->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Identificación</dt>
                        <dd>{{ $estudiante->contacto?->identificacion ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Teléfono</dt>
                        <dd>{{ $estudiante->contacto?->telefono ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Email</dt>
                        <dd>{{ $estudiante->contacto?->email ?? '—' }}</dd>
                    </div>
                </dl>

                @can('edu.gestionar')
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <form method="POST" action="{{ route('admin.edu.estudiantes.update', $estudiante) }}" class="flex gap-3 items-end">
                        @csrf @method('PUT')
                        <div>
                            <x-input-label for="s_estado" value="Estado" />
                            <select id="s_estado" name="estado"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="activo" @selected($estudiante->estado=='activo')>Activo</option>
                                <option value="inactivo" @selected($estudiante->estado=='inactivo')>Inactivo</option>
                                <option value="egresado" @selected($estudiante->estado=='egresado')>Egresado</option>
                                <option value="retirado" @selected($estudiante->estado=='retirado')>Retirado</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="s_codigo" value="Código" />
                            <x-text-input id="s_codigo" name="codigo_estudiante" type="text" class="mt-1 block"
                                value="{{ $estudiante->codigo_estudiante }}" maxlength="50" />
                        </div>
                        <x-primary-button>Actualizar</x-primary-button>
                    </form>
                </div>
                @endcan
            </div>

            {{-- Acudientes --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Acudientes / Responsables</h3>
                </div>
                @if($estudiante->acudientes->count())
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Relación</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Principal</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Resp. pago</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Aut. retirar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($estudiante->acudientes as $ac)
                        <tr>
                            <td class="px-4 py-2">{{ $ac->contacto?->nombre }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $ac->tipo_relacion ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">{{ $ac->principal ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-2 text-center">{{ $ac->responsable_pago ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-2 text-center">{{ $ac->autorizado_retirar ? 'Sí' : 'No' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <p class="px-4 py-4 text-sm text-gray-400">Sin acudientes registrados.</p>
                @endif
            </div>

            {{-- Matrículas --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Matrículas</h3>
                    @can('edu.gestionar')
                    <a href="{{ route('admin.edu.matriculas.create', ['estudiante_id' => $estudiante->id]) }}"
                        class="text-xs text-indigo-600 hover:underline">+ Nueva matrícula</a>
                    @endcan
                </div>
                @if($estudiante->matriculas->count())
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Período</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Grado</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Grupo</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($estudiante->matriculas as $mat)
                        <tr>
                            <td class="px-4 py-2">{{ $mat->periodo?->nombre }}</td>
                            <td class="px-4 py-2">{{ $mat->grado?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $mat->grupo?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-center capitalize">{{ $mat->estado }}</td>
                            <td class="px-4 py-2 text-center text-xs text-gray-600">{{ $mat->fecha_matricula?->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.edu.matriculas.show', $mat) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <p class="px-4 py-4 text-sm text-gray-400">Sin matrículas.</p>
                @endif
            </div>

            @can('edu.gestionar')
            <div class="flex">
                <form method="POST" action="{{ route('admin.edu.estudiantes.destroy', $estudiante) }}"
                      onsubmit="return confirm('¿Eliminar este estudiante permanentemente?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-600 hover:underline">Eliminar estudiante</button>
                </form>
            </div>
            @endcan
        </div>
    </div>
</x-app-layout>
