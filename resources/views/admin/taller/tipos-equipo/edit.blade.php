<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar tipo de equipo — {{ $tipoEquipo->nombre }}</h2>
            <a href="{{ route('admin.taller.tipos-equipo.index', ['taller_id' => $tipoEquipo->taller_id]) }}" class="text-sm text-gray-600 hover:text-gray-900">← Tipos de equipo</a>
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
                <form method="POST" action="{{ route('admin.taller.tipos-equipo.update', $tipoEquipo) }}">
                    @csrf @method('PUT')
                    <div>
                        <x-input-label value="Taller" />
                        <p class="mt-1 text-sm font-medium text-gray-700">{{ $tipoEquipo->taller->nombre }}</p>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                value="{{ old('codigo', $tipoEquipo->codigo) }}" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                value="{{ old('nombre', $tipoEquipo->nombre) }}" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="categoria" value="Categoría *" />
                            <select id="categoria" name="categoria" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerTipoEquipo::CATEGORIAS as $val => $label)
                                    <option value="{{ $val }}" {{ old('categoria', $tipoEquipo->categoria) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="unidad_medidor" value="Unidad de medidor" />
                            <x-text-input id="unidad_medidor" name="unidad_medidor" type="text" class="mt-1 block w-full"
                                value="{{ old('unidad_medidor', $tipoEquipo->unidad_medidor) }}" maxlength="30" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Campos requeridos en recepción</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ([['requiere_placa','Placa'],['requiere_vin','VIN'],['requiere_serie','N° Serie'],['requiere_medidor','Medidor']] as [$field,$label])
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" name="{{ $field }}" value="1" class="rounded border-gray-300 text-indigo-600"
                                    {{ old($field, $tipoEquipo->$field) ? 'checked' : '' }}>
                                {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" id="activo" name="activo" value="1" class="rounded border-gray-300 text-indigo-600"
                            {{ old('activo', $tipoEquipo->activo) ? 'checked' : '' }}>
                        <x-input-label for="activo" value="Activo" class="mb-0" />
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Actualizar</x-primary-button>
                        <a href="{{ route('admin.taller.tipos-equipo.index', ['taller_id' => $tipoEquipo->taller_id]) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
