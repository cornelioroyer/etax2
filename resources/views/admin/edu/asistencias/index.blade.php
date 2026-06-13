<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Asistencias</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="GET" action="{{ route('admin.edu.asistencias.index') }}" class="flex flex-wrap gap-2 bg-white p-4 shadow-sm sm:rounded-lg">
                <select name="grupo_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Seleccione grupo —</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" @selected($grupoId == $g->id)>{{ $g->nombre }}</option>
                    @endforeach
                </select>
                <select name="asignatura_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Asignatura —</option>
                    @foreach ($asignaturas as $a)
                        <option value="{{ $a->id }}" @selected($asignaturaId == $a->id)>{{ $a->nombre }}</option>
                    @endforeach
                </select>
                <input type="date" name="fecha" value="{{ $fecha }}"
                    class="rounded-md border-gray-300 shadow-sm text-sm" />
                <x-primary-button>Ver lista</x-primary-button>
            </form>

            @if($matriculas->count())
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">
                        Lista de asistencia — {{ $fecha }}
                    </h3>
                    <span class="text-xs text-gray-500">{{ $matriculas->count() }} estudiante(s)</span>
                </div>
                @can('edu.gestionar')
                <form method="POST" action="{{ route('admin.edu.asistencias.store') }}">
                    @csrf
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                    <input type="hidden" name="grupo_id" value="{{ $grupoId }}">
                    <input type="hidden" name="asignatura_id" value="{{ $asignaturaId }}">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Estudiante</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Observación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($matriculas as $mat)
                            @php
                                $asis = $asistencias->get($mat->id);
                            @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium">
                                    {{ $mat->estudiante?->contacto?->nombre }}
                                    <input type="hidden" name="asistencias[{{ $loop->index }}][matricula_id]" value="{{ $mat->id }}">
                                    <input type="hidden" name="asistencias[{{ $loop->index }}][institucion_id]" value="{{ $mat->institucion_id }}">
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <select name="asistencias[{{ $loop->index }}][estado]"
                                        class="rounded-md border-gray-300 shadow-sm text-xs">
                                        <option value="presente" @selected(($asis->estado ?? 'presente') == 'presente')>Presente</option>
                                        <option value="ausente" @selected(($asis->estado ?? '') == 'ausente')>Ausente</option>
                                        <option value="tardanza" @selected(($asis->estado ?? '') == 'tardanza')>Tardanza</option>
                                        <option value="justificado" @selected(($asis->estado ?? '') == 'justificado')>Justificado</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" name="asistencias[{{ $loop->index }}][observacion]"
                                        value="{{ $asis->observacion ?? '' }}"
                                        class="block w-full rounded-md border-gray-300 shadow-sm text-xs" maxlength="200">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="px-4 py-3 border-t border-gray-200">
                        <x-primary-button>Guardar asistencia</x-primary-button>
                    </div>
                </form>
                @else
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Estudiante</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($matriculas as $mat)
                        @php $asis = $asistencias->get($mat->id); @endphp
                        <tr>
                            <td class="px-4 py-2">{{ $mat->estudiante?->contacto?->nombre }}</td>
                            <td class="px-4 py-2 text-center capitalize">{{ $asis->estado ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endcan
            </div>
            @elseif($grupoId)
            <div class="bg-white p-6 shadow-sm sm:rounded-lg text-center text-gray-400">
                No hay estudiantes matriculados en este grupo.
            </div>
            @else
            <div class="bg-white p-6 shadow-sm sm:rounded-lg text-center text-gray-400">
                Seleccione un grupo y una fecha para ver la lista de asistencia.
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
