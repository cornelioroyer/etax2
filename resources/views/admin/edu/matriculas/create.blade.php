<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva Matrícula</h2>
            <a href="{{ route('admin.edu.matriculas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Matrículas</a>
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
                <form method="POST" action="{{ route('admin.edu.matriculas.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="mat_institucion_id" value="Institución *" />
                            <select id="mat_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_estudiante_id" value="Estudiante *" />
                            <select id="mat_estudiante_id" name="estudiante_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione estudiante —</option>
                                @foreach ($estudiantes as $est)
                                    <option value="{{ $est->id }}" @selected(old('estudiante_id', request('estudiante_id')) == $est->id)>{{ $est->contacto?->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_periodo_id" value="Período *" />
                            <select id="mat_periodo_id" name="periodo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($periodos as $p)
                                    <option value="{{ $p->id }}" @selected(old('periodo_id') == $p->id)>{{ $p->nombre }} ({{ $p->anio }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_sede_id" value="Sede" />
                            <select id="mat_sede_id" name="sede_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguna —</option>
                                @foreach ($sedes as $s)
                                    <option value="{{ $s->id }}" @selected(old('sede_id') == $s->id)>{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_nivel_id" value="Nivel" />
                            <select id="mat_nivel_id" name="nivel_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($niveles as $n)
                                    <option value="{{ $n->id }}" @selected(old('nivel_id') == $n->id)>{{ $n->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_programa_id" value="Programa" />
                            <select id="mat_programa_id" name="programa_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($programas as $p)
                                    <option value="{{ $p->id }}" @selected(old('programa_id') == $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_grado_id" value="Grado" />
                            <select id="mat_grado_id" name="grado_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($grados as $g)
                                    <option value="{{ $g->id }}" @selected(old('grado_id') == $g->id)>{{ $g->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_grupo_id" value="Grupo" />
                            <select id="mat_grupo_id" name="grupo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($grupos as $g)
                                    <option value="{{ $g->id }}" @selected(old('grupo_id') == $g->id)>{{ $g->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="mat_fecha" value="Fecha matrícula *" />
                            <x-text-input id="mat_fecha" name="fecha_matricula" type="date" class="mt-1 block w-full"
                                :value="old('fecha_matricula', now()->format('Y-m-d'))" required />
                        </div>
                        <div>
                            <x-input-label for="mat_estado" value="Estado *" />
                            <select id="mat_estado" name="estado"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="activo" @selected(old('estado','activo')=='activo')>Activo</option>
                                <option value="retirado" @selected(old('estado')=='retirado')>Retirado</option>
                                <option value="egresado" @selected(old('estado')=='egresado')>Egresado</option>
                                <option value="suspendido" @selected(old('estado')=='suspendido')>Suspendido</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Registrar matrícula</x-primary-button>
                        <a href="{{ route('admin.edu.matriculas.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
