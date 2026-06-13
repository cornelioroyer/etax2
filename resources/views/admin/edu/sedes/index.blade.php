<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sedes</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @can('edu.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nueva sede</h3>
                <form method="POST" action="{{ route('admin.edu.sedes.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="s_institucion_id" value="Institución *" />
                            <select id="s_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="s_codigo" value="Código *" />
                            <x-text-input id="s_codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="s_nombre" value="Nombre *" />
                            <x-text-input id="s_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="s_telefono" value="Teléfono" />
                            <x-text-input id="s_telefono" name="telefono" type="text" class="mt-1 block w-full"
                                :value="old('telefono')" maxlength="50" />
                        </div>
                        <div>
                            <x-input-label for="s_email" value="Email" />
                            <x-text-input id="s_email" name="email" type="email" class="mt-1 block w-full"
                                :value="old('email')" maxlength="150" />
                        </div>
                        <div>
                            <x-input-label for="s_direccion" value="Dirección" />
                            <x-text-input id="s_direccion" name="direccion" type="text" class="mt-1 block w-full"
                                :value="old('direccion')" maxlength="300" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar sede</x-primary-button>
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
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Teléfono</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Activo</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sedes as $sede)
                            <tr x-data="{ edit: false }">
                                <td class="px-4 py-2 font-mono">{{ $sede->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $sede->nombre }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $sede->institucion?->nombre }}</td>
                                <td class="px-4 py-2">{{ $sede->telefono ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $sede->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $sede->activo ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                @can('edu.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.edu.sedes.destroy', $sede) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar sede?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('edu.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="6" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.edu.sedes.update', $sede) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Código *" />
                                                <x-text-input name="codigo" type="text" class="mt-1 block w-full"
                                                    value="{{ $sede->codigo }}" required maxlength="30" />
                                            </div>
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $sede->nombre }}" required maxlength="200" />
                                            </div>
                                            <div>
                                                <x-input-label value="Teléfono" />
                                                <x-text-input name="telefono" type="text" class="mt-1 block w-full"
                                                    value="{{ $sede->telefono }}" maxlength="50" />
                                            </div>
                                            <div>
                                                <x-input-label value="Email" />
                                                <x-text-input name="email" type="email" class="mt-1 block w-full"
                                                    value="{{ $sede->email }}" maxlength="150" />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex gap-2 items-center">
                                            <x-primary-button>Actualizar</x-primary-button>
                                            <button type="button" @click="edit = false"
                                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                            <label class="flex items-center gap-1 text-sm text-gray-700">
                                                <input type="hidden" name="activo" value="0">
                                                <input type="checkbox" name="activo" value="1" {{ $sede->activo ? 'checked' : '' }} class="rounded border-gray-300">
                                                Activo
                                            </label>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin sedes registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $sedes->links() }}</div>
        </div>
    </div>
</x-app-layout>
