<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo servicio estándar</h2>
            <a href="{{ route('admin.taller.servicios.index', $tallerId ? ['taller_id' => $tallerId] : []) }}" class="text-sm text-gray-600 hover:text-gray-900">← Servicios</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.taller.servicios.store') }}">
                    @csrf
                    <div>
                        <x-input-label for="taller_id" value="Taller *" />
                        <select id="taller_id" name="taller_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            onchange="this.form.submit()">
                            <option value="">— Seleccione —</option>
                            @foreach ($talleres as $t)
                                <option value="{{ $t->id }}" {{ old('taller_id', $tallerId) == $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="especialidad_id" value="Especialidad" />
                            <select id="especialidad_id" name="especialidad_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— Sin especialidad —</option>
                                @foreach ($especialidades as $e)
                                    <option value="{{ $e->id }}" {{ old('especialidad_id') == $e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="tipo_equipo_id" value="Tipo de equipo" />
                            <select id="tipo_equipo_id" name="tipo_equipo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— General —</option>
                                @foreach ($tiposEquipo as $te)
                                    <option value="{{ $te->id }}" {{ old('tipo_equipo_id') == $te->id ? 'selected' : '' }}>{{ $te->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="precio_base" value="Precio base" />
                            <x-text-input id="precio_base" name="precio_base" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                :value="old('precio_base')" />
                        </div>
                        <div>
                            <x-input-label for="costo_base" value="Costo base" />
                            <x-text-input id="costo_base" name="costo_base" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                :value="old('costo_base')" />
                        </div>
                        <div>
                            <x-input-label for="tiempo_estimado_min" value="Tiempo estimado (minutos)" />
                            <x-text-input id="tiempo_estimado_min" name="tiempo_estimado_min" type="number" min="0" class="mt-1 block w-full"
                                :value="old('tiempo_estimado_min')" />
                        </div>
                        <div>
                            <x-input-label for="garantia_dias" value="Días de garantía" />
                            <x-text-input id="garantia_dias" name="garantia_dias" type="number" min="0" class="mt-1 block w-full"
                                :value="old('garantia_dias')" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="descripcion" value="Descripción" />
                        <textarea id="descripcion" name="descripcion" rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            maxlength="1000">{{ old('descripcion') }}</textarea>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <input type="hidden" name="requiere_aprobacion" value="0">
                        <input type="checkbox" id="requiere_aprobacion" name="requiere_aprobacion" value="1" class="rounded border-gray-300 text-indigo-600"
                            {{ old('requiere_aprobacion') ? 'checked' : '' }}>
                        <x-input-label for="requiere_aprobacion" value="Requiere aprobación del cliente" class="mb-0" />
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Guardar servicio</x-primary-button>
                        <a href="{{ route('admin.taller.servicios.index', $tallerId ? ['taller_id' => $tallerId] : []) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
