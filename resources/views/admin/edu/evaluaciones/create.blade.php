<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva Evaluación</h2>
            <a href="{{ route('admin.edu.evaluaciones.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Evaluaciones</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.edu.evaluaciones.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="ev_institucion_id" value="Institución *" />
                            <select id="ev_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="ev_periodo_id" value="Período" />
                            <select id="ev_periodo_id" name="periodo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($periodos as $p)
                                    <option value="{{ $p->id }}" @selected(old('periodo_id') == $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="ev_asignatura_id" value="Asignatura" />
                            <select id="ev_asignatura_id" name="asignatura_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguna —</option>
                                @foreach ($asignaturas as $a)
                                    <option value="{{ $a->id }}" @selected(old('asignatura_id') == $a->id)>{{ $a->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="ev_grupo_id" value="Grupo" />
                            <select id="ev_grupo_id" name="grupo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($grupos as $g)
                                    <option value="{{ $g->id }}" @selected(old('grupo_id') == $g->id)>{{ $g->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="ev_docente_id" value="Docente" />
                            <select id="ev_docente_id" name="docente_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($docentes as $d)
                                    <option value="{{ $d->id }}" @selected(old('docente_id') == $d->id)>{{ $d->contacto?->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="ev_titulo" value="Título *" />
                            <x-text-input id="ev_titulo" name="titulo" type="text" class="mt-1 block w-full"
                                :value="old('titulo')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="ev_tipo" value="Tipo de evaluación" />
                            <x-text-input id="ev_tipo" name="tipo_evaluacion" type="text" class="mt-1 block w-full"
                                :value="old('tipo_evaluacion')" maxlength="50" placeholder="examen, tarea, proyecto..." />
                        </div>
                        <div>
                            <x-input-label for="ev_fecha" value="Fecha evaluación" />
                            <x-text-input id="ev_fecha" name="fecha_evaluacion" type="date" class="mt-1 block w-full"
                                :value="old('fecha_evaluacion')" />
                        </div>
                        <div>
                            <x-input-label for="ev_puntaje" value="Puntaje máximo" />
                            <x-text-input id="ev_puntaje" name="puntaje_maximo" type="number" step="0.01" class="mt-1 block w-full"
                                :value="old('puntaje_maximo')" min="0" />
                        </div>
                        <div>
                            <x-input-label for="ev_porcentaje" value="Porcentaje (%)" />
                            <x-text-input id="ev_porcentaje" name="porcentaje" type="number" step="0.01" class="mt-1 block w-full"
                                :value="old('porcentaje')" min="0" max="100" />
                        </div>
                        <div>
                            <x-input-label for="ev_estado" value="Estado *" />
                            <select id="ev_estado" name="estado"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="borrador" @selected(old('estado','borrador')=='borrador')>Borrador</option>
                                <option value="publicada" @selected(old('estado')=='publicada')>Publicada</option>
                                <option value="cerrada" @selected(old('estado')=='cerrada')>Cerrada</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2 flex gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="visible_estudiante" value="0">
                                <input type="checkbox" name="visible_estudiante" value="1" {{ old('visible_estudiante') ? 'checked' : '' }} class="rounded border-gray-300">
                                Visible para estudiante
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="visible_acudiente" value="0">
                                <input type="checkbox" name="visible_acudiente" value="1" {{ old('visible_acudiente') ? 'checked' : '' }} class="rounded border-gray-300">
                                Visible para acudiente
                            </label>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="ev_descripcion" value="Descripción" />
                            <textarea id="ev_descripcion" name="descripcion" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('descripcion') }}</textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Crear evaluación</x-primary-button>
                        <a href="{{ route('admin.edu.evaluaciones.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
