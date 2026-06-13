<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar síntoma — {{ $sintoma->nombre }}</h2>
            <a href="{{ route('admin.taller.sintomas.index', ['taller_id' => $sintoma->taller_id]) }}" class="text-sm text-gray-600 hover:text-gray-900">← Síntomas</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.taller.sintomas.update', $sintoma) }}">
                    @csrf @method('PUT')
                    <div>
                        <x-input-label value="Taller" />
                        <p class="mt-1 text-sm font-medium text-gray-700">{{ $sintoma->taller->nombre }}</p>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="tipo_equipo_id" value="Tipo de equipo" />
                        <select id="tipo_equipo_id" name="tipo_equipo_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— General —</option>
                            @foreach ($tiposEquipo as $te)
                                <option value="{{ $te->id }}" {{ old('tipo_equipo_id', $sintoma->tipo_equipo_id) == $te->id ? 'selected' : '' }}>{{ $te->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                value="{{ old('codigo', $sintoma->codigo) }}" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                value="{{ old('nombre', $sintoma->nombre) }}" required maxlength="200" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="descripcion" value="Descripción" />
                        <textarea id="descripcion" name="descripcion" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            maxlength="1000">{{ old('descripcion', $sintoma->descripcion) }}</textarea>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" id="activo" name="activo" value="1" class="rounded border-gray-300 text-indigo-600"
                            {{ old('activo', $sintoma->activo) ? 'checked' : '' }}>
                        <x-input-label for="activo" value="Activo" class="mb-0" />
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Actualizar</x-primary-button>
                        <a href="{{ route('admin.taller.sintomas.index', ['taller_id' => $sintoma->taller_id]) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
