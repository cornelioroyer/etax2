<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Taller — Órdenes de trabajo</h2>
            <div class="flex gap-3 text-sm">
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.ordenes.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nueva orden</a>
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
                <x-text-input name="q" type="search" class="w-64" placeholder="Número, cliente..." :value="$search" />
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
                    @foreach (\App\Models\TallerOrden::ESTADOS as $val => $label)
                        <option value="{{ $val }}" {{ $estado === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <x-primary-button>Buscar</x-primary-button>
                @if ($search || $tallerId || $estado)
                    <a href="{{ route('admin.taller.ordenes.index') }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500"># OT</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Equipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Taller</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Prioridad</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($ordenes as $o)
                            @php
                                $colorEstado = \App\Models\TallerOrden::colorEstado($o->estado);
                                $colorPrioridad = match($o->prioridad) {
                                    'urgente' => 'red',
                                    'alta'    => 'orange',
                                    'normal'  => 'blue',
                                    default   => 'gray',
                                };
                                $badgeClasses = [
                                    'blue'    => 'bg-blue-100 text-blue-700',
                                    'indigo'  => 'bg-indigo-100 text-indigo-700',
                                    'yellow'  => 'bg-yellow-100 text-yellow-700',
                                    'cyan'    => 'bg-cyan-100 text-cyan-700',
                                    'orange'  => 'bg-orange-100 text-orange-700',
                                    'amber'   => 'bg-amber-100 text-amber-700',
                                    'purple'  => 'bg-purple-100 text-purple-700',
                                    'teal'    => 'bg-teal-100 text-teal-700',
                                    'green'   => 'bg-green-100 text-green-700',
                                    'emerald' => 'bg-emerald-100 text-emerald-700',
                                    'gray'    => 'bg-gray-100 text-gray-600',
                                    'red'     => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono font-semibold text-indigo-700">{{ $o->numero }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">
                                    {{ $o->fecha_recepcion?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium text-xs">{{ $o->cliente?->nombre ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">
                                    {{ $o->equipo?->nombre ?? ($o->equipo_id ? 'Equipo #'.$o->equipo_id : '—') }}
                                    @if ($o->equipo?->tipoEquipo)
                                        <div class="text-gray-400">{{ $o->equipo->tipoEquipo->nombre }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $o->taller?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClasses[$colorEstado] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ \App\Models\TallerOrden::ESTADOS[$o->estado] ?? $o->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClasses[$colorPrioridad] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ \App\Models\TallerOrden::PRIORIDADES[$o->prioridad] ?? $o->prioridad }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-xs">
                                    {{ number_format($o->total, 2) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('admin.taller.ordenes.show', $o) }}"
                                        class="text-xs text-indigo-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                                    Sin órdenes registradas.
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.ordenes.create') }}"
                                            class="text-indigo-600 hover:underline">Crear la primera</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $ordenes->links() }}
        </div>
    </div>
</x-app-layout>
