<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Taller — Presupuestos</h2>
            <div class="flex gap-3 text-sm">
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.presupuestos.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nuevo presupuesto</a>
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
                <x-text-input name="q" type="search" class="w-64" placeholder="Número, descripción..." :value="$search" />
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
                    @foreach (\App\Models\TallerPresupuesto::ESTADOS as $val => $label)
                        <option value="{{ $val }}" {{ $estado === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <x-primary-button>Buscar</x-primary-button>
                @if ($search || $tallerId || $estado)
                    <a href="{{ route('admin.taller.presupuestos.index') }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Número</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Taller</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($presupuestos as $p)
                            @php
                                $badgeColor = match($p->estado) {
                                    'borrador'   => 'bg-gray-100 text-gray-600',
                                    'enviado'    => 'bg-blue-100 text-blue-700',
                                    'aprobado'   => 'bg-green-100 text-green-700',
                                    'rechazado'  => 'bg-red-100 text-red-700',
                                    'vencido'    => 'bg-orange-100 text-orange-700',
                                    'convertido' => 'bg-indigo-100 text-indigo-700',
                                    'anulado'    => 'bg-gray-100 text-gray-400',
                                    default      => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono font-semibold text-indigo-700">{{ $p->numero }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->fecha?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs font-medium">{{ $p->cliente?->nombre ?? ($p->cliente_id ? 'ID '.$p->cliente_id : '—') }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->taller?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeColor }}">
                                        {{ \App\Models\TallerPresupuesto::ESTADOS[$p->estado] ?? $p->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($p->total, 2) }}</td>
                                <td class="px-4 py-2 text-right text-xs space-x-2">
                                    <a href="{{ route('admin.taller.presupuestos.show', $p) }}"
                                        class="text-indigo-600 hover:underline">Ver</a>
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.presupuestos.edit', $p) }}"
                                            class="text-gray-500 hover:underline">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                    Sin presupuestos registrados.
                                    @can('taller.gestionar')
                                        <a href="{{ route('admin.taller.presupuestos.create') }}"
                                            class="text-indigo-600 hover:underline">Crear el primero</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $presupuestos->links() }}
        </div>
    </div>
</x-app-layout>
