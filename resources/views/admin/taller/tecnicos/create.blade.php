<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo técnico</h2>
            <a href="{{ route('admin.taller.tecnicos.index', $tallerId ? ['taller_id' => $tallerId] : []) }}" class="text-sm text-gray-600 hover:text-gray-900">← Técnicos</a>
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
                <form method="POST" action="{{ route('admin.taller.tecnicos.store') }}">
                    @csrf
                    <div>
                        <x-input-label for="taller_id" value="Taller *" />
                        <select id="taller_id" name="taller_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Seleccione —</option>
                            @foreach ($talleres as $t)
                                <option value="{{ $t->id }}" {{ old('taller_id', $tallerId) == $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre_publico" value="Nombre *" />
                            <x-text-input id="nombre_publico" name="nombre_publico" type="text" class="mt-1 block w-full"
                                :value="old('nombre_publico')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="tipo_tecnico" value="Tipo de técnico *" />
                            <select id="tipo_tecnico" name="tipo_tecnico" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— Seleccione —</option>
                                @foreach (\App\Models\TallerTecnico::TIPOS as $val => $label)
                                    <option value="{{ $val }}" {{ old('tipo_tecnico') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="capacidad_horas_dia" value="Capacidad horas/día" />
                            <x-text-input id="capacidad_horas_dia" name="capacidad_horas_dia" type="number" step="0.5" min="0" class="mt-1 block w-full"
                                :value="old('capacidad_horas_dia', 8)" />
                        </div>
                        <div>
                            <x-input-label for="precio_hora" value="Precio por hora" />
                            <x-text-input id="precio_hora" name="precio_hora" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                :value="old('precio_hora')" />
                        </div>
                        <div>
                            <x-input-label for="costo_hora" value="Costo por hora" />
                            <x-text-input id="costo_hora" name="costo_hora" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                :value="old('costo_hora')" />
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">Después de guardar podrás asignar especialidades al técnico.</p>
                    <div class="mt-4 flex gap-3">
                        <x-primary-button>Guardar técnico</x-primary-button>
                        <a href="{{ route('admin.taller.tecnicos.index', $tallerId ? ['taller_id' => $tallerId] : []) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
