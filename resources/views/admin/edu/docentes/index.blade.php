<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Docentes</h2>
            @can('edu.gestionar')
            <a href="{{ route('admin.edu.docentes.create') }}"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + Nuevo docente
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.edu.docentes.index') }}" class="flex gap-2">
                <x-text-input name="q" type="search" class="block w-full" placeholder="Buscar docente..."
                    :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Especialidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($docentes as $doc)
                        <tr x-data="{ edit: false }">
                            <td class="px-4 py-2 font-mono text-xs">{{ $doc->codigo_docente ?? '—' }}</td>
                            <td class="px-4 py-2 font-medium">{{ $doc->contacto?->nombre }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $doc->especialidad ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $doc->institucion?->nombre }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $doc->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($doc->estado) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right space-x-2">
                                @can('edu.gestionar')
                                <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                <form method="POST" action="{{ route('admin.edu.docentes.destroy', $doc) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar docente?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @can('edu.gestionar')
                        <tr x-show="edit" x-cloak>
                            <td colspan="6" class="bg-gray-50 px-4 py-3">
                                <form method="POST" action="{{ route('admin.edu.docentes.update', $doc) }}">
                                    @csrf @method('PUT')
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                        <div>
                                            <x-input-label value="Código" />
                                            <x-text-input name="codigo_docente" type="text" class="mt-1 block w-full"
                                                value="{{ $doc->codigo_docente }}" maxlength="50" />
                                        </div>
                                        <div>
                                            <x-input-label value="Especialidad" />
                                            <x-text-input name="especialidad" type="text" class="mt-1 block w-full"
                                                value="{{ $doc->especialidad }}" maxlength="200" />
                                        </div>
                                        <div>
                                            <x-input-label value="Estado" />
                                            <select name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                <option value="activo" @selected($doc->estado=='activo')>Activo</option>
                                                <option value="inactivo" @selected($doc->estado=='inactivo')>Inactivo</option>
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label value="Fecha ingreso" />
                                            <x-text-input name="fecha_ingreso" type="date" class="mt-1 block w-full"
                                                value="{{ $doc->fecha_ingreso }}" />
                                        </div>
                                    </div>
                                    <div class="mt-3 flex gap-2">
                                        <x-primary-button>Actualizar</x-primary-button>
                                        <button type="button" @click="edit = false"
                                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endcan
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin docentes registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $docentes->links() }}</div>
        </div>
    </div>
</x-app-layout>
