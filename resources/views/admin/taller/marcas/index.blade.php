<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Taller — Marcas
                @if ($tallerActual) <span class="text-gray-400 font-normal text-base">· {{ $tallerActual->nombre }}</span> @endif
            </h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.tipos-equipo.index', $tallerId ? ['taller_id' => $tallerId] : []) }}" class="text-gray-500 hover:text-gray-900">Tipos de equipo</a>
                <a href="{{ route('admin.taller.modelos.index', $tallerId ? ['taller_id' => $tallerId] : []) }}" class="text-gray-500 hover:text-gray-900">Modelos</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.marcas.create', $tallerId ? ['taller_id' => $tallerId] : []) }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nueva marca</a>
                @endcan
            </div>
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

            <form method="GET" class="flex gap-2 flex-wrap">
                <select name="taller_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                    <option value="">— Todos los talleres —</option>
                    @foreach ($talleres as $t)
                        <option value="{{ $t->id }}" {{ (string)$tallerId === (string)$t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                    @endforeach
                </select>
                <x-text-input name="q" type="search" class="w-56" placeholder="Buscar marca..." :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
                @if ($search || $tallerId)
                    <a href="{{ route('admin.taller.marcas.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Taller</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Modelos</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($marcas as $m)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $m->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $m->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $m->taller->nombre }}</td>
                                <td class="px-4 py-2 text-right">{{ $m->modelos_count }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $m->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $m->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-3">
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.marcas.edit', $m) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                        <form method="POST" action="{{ route('admin.taller.marcas.destroy', $m) }}" class="inline"
                                              onsubmit="return confirm('¿Eliminar {{ $m->nombre }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Sin marcas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $marcas->links() }}
        </div>
    </div>
</x-app-layout>
