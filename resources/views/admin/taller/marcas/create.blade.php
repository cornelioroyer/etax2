<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva marca</h2>
            <a href="{{ route('admin.taller.marcas.index', $tallerId ? ['taller_id' => $tallerId] : []) }}" class="text-sm text-gray-600 hover:text-gray-900">← Marcas</a>
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
                <form method="POST" action="{{ route('admin.taller.marcas.store') }}">
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
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Guardar marca</x-primary-button>
                        <a href="{{ route('admin.taller.marcas.index', $tallerId ? ['taller_id' => $tallerId] : []) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
