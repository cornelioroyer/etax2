<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Taller — Talleres</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.sucursales.index') }}" class="text-gray-500 hover:text-gray-900">Sucursales</a>
                <a href="{{ route('admin.taller.areas.index') }}" class="text-gray-500 hover:text-gray-900">Áreas</a>
                <a href="{{ route('admin.taller.tipos-equipo.index') }}" class="text-gray-500 hover:text-gray-900">Tipos de equipo</a>
                <a href="{{ route('admin.taller.tecnicos.index') }}" class="text-gray-500 hover:text-gray-900">Técnicos</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.talleres.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nuevo taller</a>
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

            <form method="GET" class="flex gap-2">
                <x-text-input name="q" type="search" class="w-64" placeholder="Buscar taller..." :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
                @if ($search)
                    <a href="{{ route('admin.taller.talleres.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dirección</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Sucursales</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Técnicos</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($talleres as $t)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $t->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $t->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ \App\Models\TallerTaller::TIPOS[$t->tipo_taller] ?? $t->tipo_taller }}</td>
                                <td class="px-4 py-2 text-gray-600 text-xs max-w-xs truncate">{{ $t->direccion ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $t->sucursales_count }}</td>
                                <td class="px-4 py-2 text-right">{{ $t->tecnicos_count }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $t->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $t->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-3">
                                    <a href="{{ route('admin.taller.talleres.show', $t) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.talleres.edit', $t) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                    Sin talleres registrados.
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.talleres.create') }}" class="text-indigo-600 hover:underline">Crear el primero</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $talleres->links() }}
        </div>
    </div>
</x-app-layout>
