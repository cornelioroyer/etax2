<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Propietarios / Residentes</h2>
            <a href="{{ route('admin.ph.edificios.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Edificios</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @can('ph.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo propietario</h3>
                <form method="POST" action="{{ route('admin.ph.propietarios.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label for="identificacion" value="Cédula / RUC" />
                            <x-text-input id="identificacion" name="identificacion" type="text" class="mt-1 block w-full"
                                :value="old('identificacion')" maxlength="50" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="nombre" value="Nombre completo *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="300" />
                        </div>
                        <div>
                            <x-input-label for="telefono" value="Teléfono" />
                            <x-text-input id="telefono" name="telefono" type="text" class="mt-1 block w-full"
                                :value="old('telefono')" maxlength="50" />
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                :value="old('email')" maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="direccion" value="Dirección" />
                            <x-text-input id="direccion" name="direccion" type="text" class="mt-1 block w-full"
                                :value="old('direccion')" maxlength="500" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar propietario</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            <form method="GET" class="flex gap-2">
                <x-text-input name="q" type="search" class="w-64" placeholder="Buscar..." :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
                @if ($search)
                    <a href="{{ route('admin.ph.propietarios.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Identificación</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Teléfono</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Unidades</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('ph.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($propietarios as $p)
                            <tr x-data="{ edit: false }" class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono">{{ $p->identificacion ?? '—' }}</td>
                                <td class="px-4 py-2 font-medium">{{ $p->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->telefono ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $p->unidades_count }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $p->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $p->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                @can('ph.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.ph.propietarios.destroy', $p) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar propietario?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('ph.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="7" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.ph.propietarios.update', $p) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Cédula / RUC" />
                                                <x-text-input name="identificacion" type="text" class="mt-1 block w-full"
                                                    value="{{ $p->identificacion }}" maxlength="50" />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $p->nombre }}" required maxlength="300" />
                                            </div>
                                            <div>
                                                <x-input-label value="Teléfono" />
                                                <x-text-input name="telefono" type="text" class="mt-1 block w-full"
                                                    value="{{ $p->telefono }}" maxlength="50" />
                                            </div>
                                        </div>
                                        <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <x-input-label value="Email" />
                                                <x-text-input name="email" type="email" class="mt-1 block w-full"
                                                    value="{{ $p->email }}" maxlength="200" />
                                            </div>
                                            <div>
                                                <x-input-label value="Dirección" />
                                                <x-text-input name="direccion" type="text" class="mt-1 block w-full"
                                                    value="{{ $p->direccion }}" maxlength="500" />
                                            </div>
                                            <div class="flex items-end gap-3">
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="hidden" name="activo" value="0">
                                                    <input type="checkbox" name="activo" value="1" class="rounded border-gray-300"
                                                        {{ $p->activo ? 'checked' : '' }}>
                                                    Activo
                                                </label>
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
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin propietarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $propietarios->links() }}
        </div>
    </div>
</x-app-layout>
