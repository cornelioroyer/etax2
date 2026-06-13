<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $taller->nombre }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ $taller->codigo }} · {{ \App\Models\TallerTaller::TIPOS[$taller->tipo_taller] ?? $taller->tipo_taller }}</p>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.talleres.index') }}" class="text-gray-500 hover:text-gray-900">← Talleres</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.talleres.edit', $taller) }}" class="text-gray-600 hover:text-gray-900">Editar</a>
                    <a href="{{ route('admin.taller.sucursales.create', ['taller_id' => $taller->id]) }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Sucursal</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Info básica y stats --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Sucursales</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $taller->sucursales->count() }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Áreas</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-700">{{ $taller->areas->count() }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Técnicos</p>
                    <p class="mt-1 text-2xl font-bold text-green-700">{{ $taller->tecnicos->count() }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Tipos equipo</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $taller->tiposEquipo->count() }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Marcas</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $taller->marcas->count() }}</p>
                </div>
            </div>

            {{-- Navegación rápida --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Accesos rápidos</h3>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.taller.sucursales.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Sucursales</a>
                    <a href="{{ route('admin.taller.areas.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Áreas</a>
                    <a href="{{ route('admin.taller.tecnicos.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Técnicos</a>
                    <a href="{{ route('admin.taller.tipos-equipo.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Tipos de equipo</a>
                    <a href="{{ route('admin.taller.marcas.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Marcas</a>
                    <a href="{{ route('admin.taller.modelos.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Modelos</a>
                    <a href="{{ route('admin.taller.especialidades.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Especialidades</a>
                    <a href="{{ route('admin.taller.sintomas.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Síntomas</a>
                    <a href="{{ route('admin.taller.servicios.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Servicios estándar</a>
                    <a href="{{ route('admin.taller.checklists.index', ['taller_id' => $taller->id]) }}" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Checklists</a>
                </div>
            </div>

            {{-- Sucursales --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Sucursales</h3>
                    @can('taller.gestionar')
                        <a href="{{ route('admin.taller.sucursales.create', ['taller_id' => $taller->id]) }}" class="text-xs text-indigo-600 hover:underline">+ Nueva sucursal</a>
                    @endcan
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Dirección</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Teléfono</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($taller->sucursales as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono">{{ $s->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $s->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $s->direccion ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $s->telefono ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $s->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $s->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.sucursales.edit', $s) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">Sin sucursales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Técnicos --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Técnicos</h3>
                    @can('taller.gestionar')
                        <a href="{{ route('admin.taller.tecnicos.create', ['taller_id' => $taller->id]) }}" class="text-xs text-indigo-600 hover:underline">+ Nuevo técnico</a>
                    @endcan
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Precio/hora</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($taller->tecnicos as $tec)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono">{{ $tec->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $tec->nombre_publico }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ \App\Models\TallerTecnico::TIPOS[$tec->tipo_tecnico] ?? $tec->tipo_tecnico }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ $tec->precio_hora ? number_format($tec->precio_hora, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $tec->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $tec->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    <a href="{{ route('admin.taller.tecnicos.show', $tec) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.tecnicos.edit', $tec) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">Sin técnicos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('taller.gestionar')
            @if ($taller->sucursales->isEmpty() && $taller->tecnicos->isEmpty())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <h3 class="text-sm font-semibold text-red-700 mb-2">Zona de peligro</h3>
                <form method="POST" action="{{ route('admin.taller.talleres.destroy', $taller) }}"
                      onsubmit="return confirm('¿Eliminar el taller {{ $taller->nombre }}?')">
                    @csrf @method('DELETE')
                    <button class="rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">Eliminar taller</button>
                </form>
            </div>
            @endif
            @endcan
        </div>
    </div>
</x-app-layout>
