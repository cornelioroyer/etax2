<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Categorías de activos</h2>
            <a href="{{ route('admin.activos.activos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Activos</a>
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

            @can('activos.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nueva categoría</h3>
                <form method="POST" action="{{ route('admin.activos.categorias.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" placeholder="EQUIPO" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="150" />
                        </div>
                        <div>
                            <x-input-label for="vida_util_meses_default" value="Vida útil (meses)" />
                            <x-text-input id="vida_util_meses_default" name="vida_util_meses_default" type="number"
                                class="mt-1 block w-full" :value="old('vida_util_meses_default')" min="1" max="600" />
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="c_cuenta_activo_id" value="Cuenta activo" />
                            <select id="c_cuenta_activo_id" name="cuenta_activo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguna —</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_activo_id') == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="c_cuenta_dep_acum_id" value="Dep. acumulada" />
                            <select id="c_cuenta_dep_acum_id" name="cuenta_depreciacion_acum_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguna —</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_depreciacion_acum_id') == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="c_cuenta_gasto_dep_id" value="Gasto depreciación" />
                            <select id="c_cuenta_gasto_dep_id" name="cuenta_gasto_depreciacion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguna —</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_gasto_depreciacion_id') == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar categoría</x-primary-button>
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
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">V. útil</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cta. activo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dep. acum.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Gasto dep.</th>
                            @can('activos.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($categorias as $cat)
                            <tr x-data="{ edit: false }">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $cat->codigo }}</td>
                                <td class="px-4 py-2">{{ $cat->nombre }}</td>
                                <td class="px-4 py-2 text-right">{{ $cat->vida_util_meses_default ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $cat->cuentaActivo?->codigo }} {{ $cat->cuentaActivo?->nombre }}</td>
                                <td class="px-4 py-2 text-xs">{{ $cat->cuentaDepreciacionAcum?->codigo }} {{ $cat->cuentaDepreciacionAcum?->nombre }}</td>
                                <td class="px-4 py-2 text-xs">{{ $cat->cuentaGastoDepreciacion?->codigo }} {{ $cat->cuentaGastoDepreciacion?->nombre }}</td>
                                @can('activos.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.activos.categorias.destroy', $cat) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar categoría?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('activos.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="7" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.activos.categorias.update', $cat) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $cat->nombre }}" required maxlength="150" />
                                            </div>
                                            <div>
                                                <x-input-label value="Vida útil (meses)" />
                                                <x-text-input name="vida_util_meses_default" type="number" class="mt-1 block w-full"
                                                    value="{{ $cat->vida_util_meses_default }}" min="1" max="600" />
                                            </div>
                                            <div>
                                                <x-input-label value="Cta. activo" />
                                                <select name="cuenta_activo_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    <option value="">— ninguna —</option>
                                                    @foreach ($cuentas as $c)
                                                        <option value="{{ $c->id }}" @selected($cat->cuenta_activo_id == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Dep. acum." />
                                                <select name="cuenta_depreciacion_acum_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    <option value="">— ninguna —</option>
                                                    @foreach ($cuentas as $c)
                                                        <option value="{{ $c->id }}" @selected($cat->cuenta_depreciacion_acum_id == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                                    @endforeach
                                                </select>
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
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin categorías.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
