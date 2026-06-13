<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva unidad — {{ $edificio->nombre }}</h2>
            <a href="{{ route('admin.prh.edificios.show', $edificio) }}" class="text-sm text-gray-600 hover:text-gray-900">← {{ $edificio->nombre }}</a>
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
                <form method="POST" action="{{ route('admin.prh.edificios.unidades.store', $edificio) }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" placeholder="U-101" />
                        </div>
                        <div>
                            <x-input-label for="numero" value="Número / Identificador *" />
                            <x-text-input id="numero" name="numero" type="text" class="mt-1 block w-full"
                                :value="old('numero')" required maxlength="50" placeholder="101" />
                        </div>
                        <div>
                            <x-input-label for="tipo" value="Tipo *" />
                            <select id="tipo" name="tipo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\PrhUnidad::TIPOS as $tipo)
                                    <option value="{{ $tipo }}" @selected(old('tipo', 'APARTAMENTO') === $tipo)>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="piso" value="Piso" />
                            <x-text-input id="piso" name="piso" type="text" class="mt-1 block w-full"
                                :value="old('piso')" maxlength="20" placeholder="1" />
                        </div>
                        <div>
                            <x-input-label for="area_m2" value="Área (m²)" />
                            <x-text-input id="area_m2" name="area_m2" type="number" step="0.01" min="0"
                                class="mt-1 block w-full" :value="old('area_m2')" />
                        </div>
                        <div>
                            <x-input-label for="coeficiente" value="Coeficiente (0–1)" />
                            <x-text-input id="coeficiente" name="coeficiente" type="number" step="0.000001" min="0" max="1"
                                class="mt-1 block w-full" :value="old('coeficiente', '0')" placeholder="0.050000" />
                            <p class="mt-1 text-xs text-gray-400">Proporción del edificio (suma total debe ser 1)</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="propietario_id" value="Propietario" />
                        <select id="propietario_id" name="propietario_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Sin propietario —</option>
                            @foreach ($propietarios as $p)
                                <option value="{{ $p->id }}" @selected(old('propietario_id') == $p->id)>
                                    {{ $p->nombre }}{{ $p->identificacion ? ' — ' . $p->identificacion : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Guardar unidad</x-primary-button>
                        <a href="{{ route('admin.prh.edificios.show', $edificio) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
