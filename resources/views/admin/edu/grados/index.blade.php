<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Grados</h2>
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
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo grado</h3>
                <form method="POST" action="{{ route('admin.edu.grados.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-5">
                        <div>
                            <x-input-label for="g_institucion_id" value="Institución *" />
                            <select id="g_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="g_nivel_id" value="Nivel" />
                            <select id="g_nivel_id" name="nivel_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($niveles as $n)
                                    <option value="{{ $n->id }}" @selected(old('nivel_id') == $n->id)>{{ $n->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="g_programa_id" value="Programa" />
                            <select id="g_programa_id" name="programa_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($programas as $p)
                                    <option value="{{ $p->id }}" @selected(old('programa_id') == $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="g_codigo" value="Código *" />
                            <x-text-input id="g_codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="g_nombre" value="Nombre *" />
                            <x-text-input id="g_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="g_orden" value="Orden" />
                            <x-text-input id="g_orden" name="orden" type="number" class="mt-1 block w-full"
                                :value="old('orden', 0)" min="0" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar grado</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Orden</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nivel</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Programa</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Activo</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($grados as $grado)
                            <tr x-data="{ edit: false }">
                                <td class="px-4 py-2 text-center text-gray-500">{{ $grado->orden ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono">{{ $grado->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $grado->nombre }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $grado->nivel?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $grado->programa?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $grado->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $grado->activo ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                @can('edu.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.edu.grados.destroy', $grado) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar grado?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('edu.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="7" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.edu.grados.update', $grado) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Nivel" />
                                                <select name="nivel_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    <option value="">— ninguno —</option>
                                                    @foreach ($niveles as $n)
                                                        <option value="{{ $n->id }}" @selected($grado->nivel_id == $n->id)>{{ $n->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Programa" />
                                                <select name="programa_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    <option value="">— ninguno —</option>
                                                    @foreach ($programas as $p)
                                                        <option value="{{ $p->id }}" @selected($grado->programa_id == $p->id)>{{ $p->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Código *" />
                                                <x-text-input name="codigo" type="text" class="mt-1 block w-full"
                                                    value="{{ $grado->codigo }}" required maxlength="30" />
                                            </div>
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $grado->nombre }}" required maxlength="200" />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex gap-2 items-center">
                                            <x-primary-button>Actualizar</x-primary-button>
                                            <button type="button" @click="edit = false"
                                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                            <label class="flex items-center gap-1 text-sm">
                                                <input type="hidden" name="activo" value="0">
                                                <input type="checkbox" name="activo" value="1" {{ $grado->activo ? 'checked' : '' }} class="rounded border-gray-300">
                                                Activo
                                            </label>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin grados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $grados->links() }}</div>
        </div>
    </div>
</x-app-layout>
