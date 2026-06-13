<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar edificio — {{ $edificio->nombre }}</h2>
            <a href="{{ route('admin.ph.edificios.show', $edificio) }}" class="text-sm text-gray-600 hover:text-gray-900">← Ver edificio</a>
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
                <form method="POST" action="{{ route('admin.ph.edificios.update', $edificio) }}">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                value="{{ old('codigo', $edificio->codigo) }}" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                value="{{ old('nombre', $edificio->nombre) }}" required maxlength="200" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="direccion" value="Dirección" />
                        <x-text-input id="direccion" name="direccion" type="text" class="mt-1 block w-full"
                            value="{{ old('direccion', $edificio->direccion) }}" maxlength="500" />
                    </div>
                    <div class="mt-4">
                        <x-input-label for="descripcion" value="Descripción" />
                        <textarea id="descripcion" name="descripcion" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            maxlength="1000">{{ old('descripcion', $edificio->descripcion) }}</textarea>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" id="activo" name="activo" value="1" class="rounded border-gray-300 text-indigo-600"
                            {{ old('activo', $edificio->activo) ? 'checked' : '' }}>
                        <x-input-label for="activo" value="Activo" class="mb-0" />
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Actualizar</x-primary-button>
                        <a href="{{ route('admin.ph.edificios.show', $edificio) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
