<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar Estudiante</h2>
            <a href="{{ route('admin.edu.estudiantes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Estudiantes</a>
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
                <form method="POST" action="{{ route('admin.edu.estudiantes.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="est_institucion_id" value="Institución *" />
                            <select id="est_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-buscador-contacto name="contacto_id" label="Contacto (persona) *" required
                                :opciones="$contactos" :selected="old('contacto_id')"
                                placeholder="Buscar por nombre o RUC" empty-label="— seleccione contacto —" mostrar-ruc />
                        </div>
                        <div>
                            <x-input-label for="est_codigo" value="Código de estudiante" />
                            <x-text-input id="est_codigo" name="codigo_estudiante" type="text" class="mt-1 block w-full"
                                :value="old('codigo_estudiante')" maxlength="50" />
                        </div>
                        <div>
                            <x-input-label for="est_estado" value="Estado *" />
                            <select id="est_estado" name="estado"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="activo" @selected(old('estado','activo')=='activo')>Activo</option>
                                <option value="inactivo" @selected(old('estado')=='inactivo')>Inactivo</option>
                                <option value="egresado" @selected(old('estado')=='egresado')>Egresado</option>
                                <option value="retirado" @selected(old('estado')=='retirado')>Retirado</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="est_fecha_ingreso" value="Fecha de ingreso" />
                            <x-text-input id="est_fecha_ingreso" name="fecha_ingreso" type="date" class="mt-1 block w-full"
                                :value="old('fecha_ingreso')" />
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Registrar estudiante</x-primary-button>
                        <a href="{{ route('admin.edu.estudiantes.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
