<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Taller — Citas</h2>
            <div class="flex gap-3 text-sm">
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.citas.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nueva cita</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="flex flex-wrap gap-2">
                <select name="taller_id"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— Todos los talleres —</option>
                    @foreach ($talleres as $t)
                        <option value="{{ $t->id }}" {{ $tallerId == $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                    @endforeach
                </select>
                <select name="estado"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— Todos los estados —</option>
                    @foreach (\App\Models\TallerCita::ESTADOS as $val => $label)
                        <option value="{{ $val }}" {{ $estado === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <x-text-input type="date" name="fecha" class="w-40" :value="$fecha" />
                <x-primary-button>Filtrar</x-primary-button>
                @if ($tallerId || $estado || $fecha)
                    <a href="{{ route('admin.taller.citas.index') }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha inicio</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Duración</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Técnico</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Taller</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($citas as $c)
                            @php
                                $badgeColor = match($c->estado) {
                                    'programada' => 'bg-blue-100 text-blue-700',
                                    'confirmada' => 'bg-cyan-100 text-cyan-700',
                                    'atendida'   => 'bg-green-100 text-green-700',
                                    'cancelada'  => 'bg-red-100 text-red-700',
                                    'no_asistio' => 'bg-orange-100 text-orange-700',
                                    default      => 'bg-gray-100 text-gray-600',
                                };
                                $duracion = $c->fecha_inicio && $c->fecha_fin
                                    ? $c->fecha_inicio->diffInMinutes($c->fecha_fin) . ' min'
                                    : '—';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    {{ $c->fecha_inicio?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $duracion }}</td>
                                <td class="px-4 py-2 text-xs font-medium">{{ $c->cliente?->nombre ?? ($c->cliente_id ? 'ID '.$c->cliente_id : '—') }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $c->tecnico?->nombre_publico ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $c->taller?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeColor }}">
                                        {{ \App\Models\TallerCita::ESTADOS[$c->estado] ?? $c->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-xs space-x-2">
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.citas.edit', $c) }}"
                                            class="text-indigo-600 hover:underline">Editar</a>
                                        @if ($c->estado !== 'atendida')
                                            <form method="POST" action="{{ route('admin.taller.citas.destroy', $c) }}"
                                                class="inline" onsubmit="return confirm('¿Eliminar esta cita?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                    Sin citas registradas.
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.citas.create') }}"
                                            class="text-indigo-600 hover:underline">Registrar la primera</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $citas->links() }}
        </div>
    </div>
</x-app-layout>
