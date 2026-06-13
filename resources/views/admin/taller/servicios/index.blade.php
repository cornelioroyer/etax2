<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Taller — Servicios estándar
                @if ($tallerActual) <span class="text-gray-400 font-normal text-base">· {{ $tallerActual->nombre }}</span> @endif
            </h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.especialidades.index', $tallerId ? ['taller_id' => $tallerId] : []) }}" class="text-gray-500 hover:text-gray-900">Especialidades</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.servicios.create', $tallerId ? ['taller_id' => $tallerId] : []) }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nuevo servicio</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="flex gap-2 flex-wrap">
                <select name="taller_id" class="rounded-md border-gray-300 text-sm shadow-sm" onchange="this.form.submit()">
                    <option value="">— Todos los talleres —</option>
                    @foreach ($talleres as $t)
                        <option value="{{ $t->id }}" {{ (string)$tallerId === (string)$t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                    @endforeach
                </select>
                <x-text-input name="q" type="search" class="w-56" placeholder="Buscar servicio..." :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
                @if ($search || $tallerId)
                    <a href="{{ route('admin.taller.servicios.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Especialidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo equipo</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Precio base</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Tiempo (min)</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($servicios as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $s->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $s->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $s->especialidad?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $s->tipoEquipo?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ $s->precio_base ? number_format($s->precio_base, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $s->tiempo_estimado_min ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $s->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $s->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right space-x-3">
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.servicios.edit', $s) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                        <form method="POST" action="{{ route('admin.taller.servicios.destroy', $s) }}" class="inline"
                                              onsubmit="return confirm('¿Eliminar {{ $s->nombre }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Sin servicios estándar registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $servicios->links() }}
        </div>
    </div>
</x-app-layout>
