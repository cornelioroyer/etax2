<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo checklist</h2>
            <a href="{{ route('admin.taller.checklists.index', $tallerId ? ['taller_id' => $tallerId] : []) }}" class="text-sm text-gray-600 hover:text-gray-900">← Checklists</a>
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
                <form method="POST" action="{{ route('admin.taller.checklists.store') }}">
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
                    <div class="mt-4">
                        <x-input-label for="tipo_equipo_id" value="Tipo de equipo" />
                        <select id="tipo_equipo_id" name="tipo_equipo_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— General —</option>
                            @foreach ($tiposEquipo as $te)
                                <option value="{{ $te->id }}" {{ old('tipo_equipo_id') == $te->id ? 'selected' : '' }}>{{ $te->nombre }}</option>
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
                    <div class="mt-4">
                        <x-input-label for="tipo_checklist" value="Tipo de checklist *" />
                        <select id="tipo_checklist" name="tipo_checklist" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Seleccione —</option>
                            @foreach (\App\Models\TallerChecklist::TIPOS as $val => $label)
                                <option value="{{ $val }}" {{ old('tipo_checklist') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">Después de guardar podrás agregar los ítems del checklist.</p>
                    <div class="mt-4 flex gap-3">
                        <x-primary-button>Guardar y agregar ítems</x-primary-button>
                        <a href="{{ route('admin.taller.checklists.index', $tallerId ? ['taller_id' => $tallerId] : []) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
