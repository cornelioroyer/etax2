<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo equipo</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    <a href="{{ route('admin.taller.equipos.index') }}" class="hover:underline">Equipos</a> &rsaquo; Nuevo
                </p>
            </div>
            <a href="{{ route('admin.taller.equipos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
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
                <form method="POST" action="{{ route('admin.taller.equipos.store') }}">
                    @csrf

                    {{-- Taller --}}
                    <div>
                        <x-input-label for="taller_id" value="Taller *" />
                        <select id="taller_id" name="taller_id" required
                            onchange="this.form.submit()"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Seleccione un taller —</option>
                            @foreach ($talleres as $t)
                                <option value="{{ $t->id }}" {{ old('taller_id', $tallerId) == $t->id ? 'selected' : '' }}>
                                    {{ $t->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Al seleccionar el taller se cargan los tipos y marcas disponibles.</p>
                    </div>

                    @if ($taller)
                        {{-- Tipo / Marca / Modelo --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="tipo_equipo_id" value="Tipo de equipo" />
                                <select id="tipo_equipo_id" name="tipo_equipo_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($tiposEquipo as $te)
                                        <option value="{{ $te->id }}" {{ old('tipo_equipo_id') == $te->id ? 'selected' : '' }}>
                                            {{ $te->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="marca_id" value="Marca" />
                                <select id="marca_id" name="marca_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— Ninguna —</option>
                                    @foreach ($marcas as $m)
                                        <option value="{{ $m->id }}" {{ old('marca_id') == $m->id ? 'selected' : '' }}>
                                            {{ $m->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="modelo_id" value="Modelo" />
                                <select id="modelo_id" name="modelo_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($modelos as $mo)
                                        <option value="{{ $mo->id }}" {{ old('modelo_id') == $mo->id ? 'selected' : '' }}>
                                            {{ $mo->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Código / Nombre --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="codigo" value="Código" />
                                <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                    :value="old('codigo')" maxlength="50" placeholder="EQ-001" />
                            </div>
                            <div>
                                <x-input-label for="nombre" value="Nombre / Identificación" />
                                <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                    :value="old('nombre')" maxlength="200" />
                            </div>
                        </div>

                        {{-- Serie / Placa / VIN --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="numero_serie" value="Número de serie" />
                                <x-text-input id="numero_serie" name="numero_serie" type="text" class="mt-1 block w-full"
                                    :value="old('numero_serie')" maxlength="100" />
                            </div>
                            <div>
                                <x-input-label for="placa" value="Placa" />
                                <x-text-input id="placa" name="placa" type="text" class="mt-1 block w-full"
                                    :value="old('placa')" maxlength="50" />
                            </div>
                            <div>
                                <x-input-label for="vin" value="VIN / Chasis" />
                                <x-text-input id="vin" name="vin" type="text" class="mt-1 block w-full"
                                    :value="old('vin')" maxlength="100" />
                            </div>
                        </div>

                        {{-- Año / Color --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="anio" value="Año" />
                                <x-text-input id="anio" name="anio" type="number" class="mt-1 block w-full"
                                    :value="old('anio')" min="1900" max="2100" placeholder="{{ date('Y') }}" />
                            </div>
                            <div>
                                <x-input-label for="color" value="Color" />
                                <x-text-input id="color" name="color" type="text" class="mt-1 block w-full"
                                    :value="old('color')" maxlength="50" />
                            </div>
                        </div>

                        {{-- Descripción --}}
                        <div class="mt-4">
                            <x-input-label for="descripcion" value="Descripción" />
                            <textarea id="descripcion" name="descripcion" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('descripcion') }}</textarea>
                        </div>

                        {{-- Activo --}}
                        <div class="mt-4 flex items-center gap-2">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" id="activo" name="activo" value="1"
                                {{ old('activo', '1') ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <x-input-label for="activo" value="Equipo activo" class="mb-0" />
                        </div>

                        <div class="mt-6 flex gap-3">
                            <x-primary-button>Guardar equipo</x-primary-button>
                            <a href="{{ route('admin.taller.equipos.index') }}"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Seleccione un taller para continuar con el formulario.</p>
                    @endif
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
