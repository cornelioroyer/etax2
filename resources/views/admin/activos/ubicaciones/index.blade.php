<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubicaciones</h2>
            <a href="{{ route('admin.activos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Activos</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @can('activos.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nueva ubicación</h3>
                <form method="POST" action="{{ route('admin.activos.ubicaciones.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="ub_codigo" value="Código *" />
                            <x-text-input id="ub_codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" placeholder="OFICINA01" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="ub_nombre" value="Nombre *" />
                            <x-text-input id="ub_nombre" name="nombre" type="text" class="mt-1 block w-full"
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
                            @can('activos.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($ubicaciones as $ub)
                            <tr x-data="{ edit: false }">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $ub->codigo }}</td>
                                <td class="px-4 py-2">{{ $ub->nombre }}</td>
                                @can('activos.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.activos.ubicaciones.destroy', $ub) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('activos.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="3" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.activos.ubicaciones.update', $ub) }}">
                                        @csrf @method('PUT')
                                        <div class="flex gap-3 items-end">
                                            <div class="flex-1">
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $ub->nombre }}" required maxlength="150" />
                                            </div>
                                            <x-primary-button>Actualizar</x-primary-button>
                                            <button type="button" @click="edit = false"
                                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Sin ubicaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
