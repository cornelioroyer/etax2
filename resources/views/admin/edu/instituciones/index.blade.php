<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Instituciones Educativas</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @can('edu.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nueva institución</h3>
                <form method="POST" action="{{ route('admin.edu.instituciones.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                            <x-input-label for="tipo_institucion" value="Tipo" />
                            <select id="tipo_institucion" name="tipo_institucion"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— seleccione —</option>
                                <option value="primaria" @selected(old('tipo_institucion')=='primaria')>Primaria</option>
                                <option value="secundaria" @selected(old('tipo_institucion')=='secundaria')>Secundaria</option>
                                <option value="universidad" @selected(old('tipo_institucion')=='universidad')>Universidad</option>
                                <option value="tecnico" @selected(old('tipo_institucion')=='tecnico')>Técnico</option>
                                <option value="otro" @selected(old('tipo_institucion')=='otro')>Otro</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="telefono" value="Teléfono" />
                            <x-text-input id="telefono" name="telefono" type="text" class="mt-1 block w-full"
                                :value="old('telefono')" maxlength="50" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                :value="old('email')" maxlength="150" />
                        </div>
                        <div>
                            <x-input-label for="sitio_web" value="Sitio web" />
                            <x-text-input id="sitio_web" name="sitio_web" type="text" class="mt-1 block w-full"
                                :value="old('sitio_web')" maxlength="200" />
                        </div>
                        <div class="sm:col-span-3">
                            <x-input-label for="direccion" value="Dirección" />
                            <x-text-input id="direccion" name="direccion" type="text" class="mt-1 block w-full"
                                :value="old('direccion')" maxlength="300" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar institución</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Teléfono</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Email</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Activo</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($instituciones as $inst)
                            <tr x-data="{ edit: false }">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $inst->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $inst->nombre }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $inst->tipo_institucion ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $inst->telefono ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $inst->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $inst->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $inst->activo ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                @can('edu.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.edu.instituciones.destroy', $inst) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar institución?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('edu.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="7" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.edu.instituciones.update', $inst) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Código *" />
                                                <x-text-input name="codigo" type="text" class="mt-1 block w-full"
                                                    value="{{ $inst->codigo }}" required maxlength="30" />
                                            </div>
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $inst->nombre }}" required maxlength="200" />
                                            </div>
                                            <div>
                                                <x-input-label value="Teléfono" />
                                                <x-text-input name="telefono" type="text" class="mt-1 block w-full"
                                                    value="{{ $inst->telefono }}" maxlength="50" />
                                            </div>
                                            <div>
                                                <x-input-label value="Email" />
                                                <x-text-input name="email" type="email" class="mt-1 block w-full"
                                                    value="{{ $inst->email }}" maxlength="150" />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex gap-2 items-center">
                                            <x-primary-button>Actualizar</x-primary-button>
                                            <button type="button" @click="edit = false"
                                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                            <label class="flex items-center gap-1 text-sm text-gray-700">
                                                <input type="hidden" name="activo" value="0">
                                                <input type="checkbox" name="activo" value="1" {{ $inst->activo ? 'checked' : '' }} class="rounded border-gray-300">
                                                Activo
                                            </label>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin instituciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $instituciones->links() }}</div>
        </div>
    </div>
</x-app-layout>
