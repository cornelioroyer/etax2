<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Asignaturas</h2>
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
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nueva asignatura</h3>
                <form method="POST" action="{{ route('admin.edu.asignaturas.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label for="asig_institucion_id" value="Institución *" />
                            <select id="asig_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="asig_codigo" value="Código *" />
                            <x-text-input id="asig_codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="asig_nombre" value="Nombre *" />
                            <x-text-input id="asig_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="asig_creditos" value="Créditos" />
                            <x-text-input id="asig_creditos" name="creditos" type="number" step="0.5" class="mt-1 block w-full"
                                :value="old('creditos')" min="0" />
                        </div>
                        <div>
                            <x-input-label for="asig_horas" value="Horas semanales" />
                            <x-text-input id="asig_horas" name="horas_semanales" type="number" class="mt-1 block w-full"
                                :value="old('horas_semanales')" min="0" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar asignatura</x-primary-button>
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
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Créditos</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Hrs/sem</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Activo</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($asignaturas as $asig)
                            <tr x-data="{ edit: false }">
                                <td class="px-4 py-2 font-mono">{{ $asig->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $asig->nombre }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $asig->institucion?->nombre }}</td>
                                <td class="px-4 py-2 text-center">{{ $asig->creditos ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">{{ $asig->horas_semanales ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $asig->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $asig->activo ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                @can('edu.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.edu.asignaturas.destroy', $asig) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar asignatura?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('edu.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="7" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.edu.asignaturas.update', $asig) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Código *" />
                                                <x-text-input name="codigo" type="text" class="mt-1 block w-full"
                                                    value="{{ $asig->codigo }}" required maxlength="30" />
                                            </div>
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $asig->nombre }}" required maxlength="200" />
                                            </div>
                                            <div>
                                                <x-input-label value="Créditos" />
                                                <x-text-input name="creditos" type="number" step="0.5" class="mt-1 block w-full"
                                                    value="{{ $asig->creditos }}" min="0" />
                                            </div>
                                            <div>
                                                <x-input-label value="Hrs/sem" />
                                                <x-text-input name="horas_semanales" type="number" class="mt-1 block w-full"
                                                    value="{{ $asig->horas_semanales }}" min="0" />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex gap-2 items-center">
                                            <x-primary-button>Actualizar</x-primary-button>
                                            <button type="button" @click="edit = false"
                                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                            <label class="flex items-center gap-1 text-sm">
                                                <input type="hidden" name="activo" value="0">
                                                <input type="checkbox" name="activo" value="1" {{ $asig->activo ? 'checked' : '' }} class="rounded border-gray-300">
                                                Activo
                                            </label>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin asignaturas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $asignaturas->links() }}</div>
        </div>
    </div>
</x-app-layout>
