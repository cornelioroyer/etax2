<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubicaciones</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            @can('dimensiones.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nueva ubicación</h3>
                <form method="POST" action="{{ route('admin.dimensiones.ubicaciones.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" placeholder="MATRIZ" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="150" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar ubicación</x-primary-button>
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
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Activo</th>
                            @can('dimensiones.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr x-data="{ edit: false }" class="{{ $item->activo ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $item->codigo }}</td>
                                <td class="px-4 py-2">{{ $item->nombre }}</td>
                                <td class="px-4 py-2 text-center">{{ $item->activo ? '✓' : '—' }}</td>
                                @can('dimensiones.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.dimensiones.ubicaciones.destroy', $item) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar ubicación?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('dimensiones.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="4" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.dimensiones.ubicaciones.update', $item) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 items-end">
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $item->nombre }}" required maxlength="150" />
                                            </div>
                                            <div class="flex items-center gap-2 mt-4">
                                                <input type="hidden" name="activo" value="0">
                                                <input type="checkbox" id="activo_{{ $item->id }}" name="activo" value="1"
                                                    class="rounded border-gray-300" {{ $item->activo ? 'checked' : '' }}>
                                                <label for="activo_{{ $item->id }}" class="text-sm text-gray-700">Activo</label>
                                            </div>
                                            <div class="flex gap-2 mt-4">
                                                <x-primary-button>Actualizar</x-primary-button>
                                                <button type="button" @click="edit = false"
                                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin ubicaciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
