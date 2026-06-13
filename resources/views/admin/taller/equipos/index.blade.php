<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Taller — Equipos</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.talleres.index') }}" class="text-gray-500 hover:text-gray-900">Talleres</a>
                <a href="{{ route('admin.taller.tecnicos.index') }}" class="text-gray-500 hover:text-gray-900">Técnicos</a>
                <a href="{{ route('admin.taller.tipos-equipo.index') }}" class="text-gray-500 hover:text-gray-900">Tipos de equipo</a>
                <a href="{{ route('admin.taller.marcas.index') }}" class="text-gray-500 hover:text-gray-900">Marcas</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.equipos.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nuevo equipo</a>
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

            <form method="GET" class="flex flex-wrap gap-2">
                <x-text-input name="q" type="search" class="w-64" placeholder="Buscar equipo..." :value="$search" />
                <select name="taller_id"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— Todos los talleres —</option>
                    @foreach ($talleres as $t)
                        <option value="{{ $t->id }}" {{ $tallerId == $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                    @endforeach
                </select>
                <x-primary-button>Buscar</x-primary-button>
                @if ($search || $tallerId)
                    <a href="{{ route('admin.taller.equipos.index') }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre / Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Marca / Modelo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Serie / Placa</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cliente principal</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($equipos as $e)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ $e->codigo ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ $e->nombre ?? '—' }}</div>
                                    @if ($e->descripcion)
                                        <div class="text-xs text-gray-500 truncate max-w-xs">{{ $e->descripcion }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $e->tipoEquipo?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">
                                    {{ $e->marca?->nombre ?? '—' }}
                                    @if ($e->modelo)
                                        <span class="text-gray-400">/ {{ $e->modelo->nombre }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">
                                    @if ($e->numero_serie)<div>S/N: {{ $e->numero_serie }}</div>@endif
                                    @if ($e->placa)<div>Placa: {{ $e->placa }}</div>@endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    @if ($e->clientePrincipal)
                                        <div>{{ $e->clientePrincipal->cliente?->nombre ?? 'ID '.$e->clientePrincipal->cliente_id }}</div>
                                        <div class="text-gray-400">{{ \App\Models\TallerClienteEquipo::RELACIONES[$e->clientePrincipal->relacion] ?? $e->clientePrincipal->relacion }}</div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $e->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $e->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-3">
                                    <a href="{{ route('admin.taller.equipos.show', $e) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.equipos.edit', $e) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                    Sin equipos registrados.
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.equipos.create') }}" class="text-indigo-600 hover:underline">Registrar el primero</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $equipos->links() }}
        </div>
    </div>
</x-app-layout>
