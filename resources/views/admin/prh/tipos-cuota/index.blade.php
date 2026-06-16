<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tipos de cuota</h2>
            <a href="{{ route('admin.prh.edificios.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Edificios</a>
        </div>
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

            @can('prh.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo tipo de cuota</h3>
                <form method="POST" action="{{ route('admin.prh.tipos-cuota.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" placeholder="MTO" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="150" placeholder="Mantenimiento mensual" />
                        </div>
                        <div>
                            <x-input-label for="monto_base" value="Monto base B/. *" />
                            <x-text-input id="monto_base" name="monto_base" type="number" step="0.01" min="0"
                                class="mt-1 block w-full" :value="old('monto_base')" required />
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="periodicidad" value="Periodicidad *" />
                            <select id="periodicidad" name="periodicidad"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\PrhTipoCuota::PERIODICIDADES as $per)
                                    <option value="{{ $per }}" @selected(old('periodicidad', 'MENSUAL') === $per)>{{ $per }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="descripcion" value="Descripción" />
                            <x-text-input id="descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                                :value="old('descripcion')" maxlength="500" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar tipo</x-primary-button>
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
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Periodicidad</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Monto base</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('prh.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tipos as $t)
                            <tr x-data="{ edit: false }" class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $t->codigo }}</td>
                                <td class="px-4 py-2">{{ $t->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $t->periodicidad }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold">B/. {{ number_format($t->monto_base, 2) }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $t->descripcion ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $t->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $t->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                @can('prh.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.prh.tipos-cuota.destroy', $t) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar tipo de cuota?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('prh.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="7" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.prh.tipos-cuota.update', $t) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $t->nombre }}" required maxlength="150" />
                                            </div>
                                            <div>
                                                <x-input-label value="Monto base B/. *" />
                                                <x-text-input name="monto_base" type="number" step="0.01" min="0"
                                                    class="mt-1 block w-full" value="{{ $t->monto_base }}" required />
                                            </div>
                                            <div>
                                                <x-input-label value="Periodicidad *" />
                                                <select name="periodicidad" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    @foreach (\App\Models\PrhTipoCuota::PERIODICIDADES as $per)
                                                        <option value="{{ $per }}" @selected($t->periodicidad === $per)>{{ $per }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex items-end gap-3">
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="hidden" name="activo" value="0">
                                                    <input type="checkbox" name="activo" value="1" class="rounded border-gray-300"
                                                        {{ $t->activo ? 'checked' : '' }}>
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
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Sin tipos de cuota.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
