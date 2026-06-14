<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Presupuestos</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.presupuestos.escenarios.index') }}" class="text-gray-500 hover:text-gray-900">Escenarios</a>
                <a href="{{ route('admin.presupuestos.versiones.index') }}" class="text-gray-500 hover:text-gray-900">Versiones</a>
                @can('presupuestos.gestionar')
                    <a href="{{ route('admin.presupuestos.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nuevo presupuesto</a>
                @endcan
            </div>
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

            <form method="GET" class="flex gap-2 flex-wrap">
                <select name="escenario_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    onchange="this.form.submit()">
                    <option value="">— Todos los escenarios —</option>
                    @foreach ($escenarios as $e)
                        <option value="{{ $e->id }}" {{ (string)$escenarioId === (string)$e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                    @endforeach
                </select>
                <select name="version_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    onchange="this.form.submit()">
                    <option value="">— Todas las versiones —</option>
                    @foreach ($versiones as $v)
                        <option value="{{ $v->id }}" {{ (string)$versionId === (string)$v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                    @endforeach
                </select>
                <x-text-input name="anio" type="number" class="w-28" placeholder="Año" :value="$anio" />
                <x-text-input name="q" type="search" class="w-56" placeholder="Buscar presupuesto..." :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
                @if ($search || $escenarioId || $versionId || $anio)
                    <a href="{{ route('admin.presupuestos.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Escenario</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Versión</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Año</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($presupuestos as $p)
                            @php
                                $badge = match($p->estado) {
                                    \App\Models\BudgetPresupuesto::ESTADO_APROBADO => 'bg-green-100 text-green-700',
                                    \App\Models\BudgetPresupuesto::ESTADO_CERRADO  => 'bg-gray-200 text-gray-600',
                                    default => 'bg-yellow-100 text-yellow-700',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('admin.presupuestos.show', $p) }}" class="text-indigo-600 hover:underline">{{ $p->nombre }}</a>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->escenario?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->version?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-center font-mono">{{ $p->anio }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                        {{ \App\Models\BudgetPresupuesto::ESTADOS[$p->estado] ?? $p->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('admin.presupuestos.show', $p) }}" class="text-xs text-gray-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-gray-400">Sin presupuestos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $presupuestos->links() }}
        </div>
    </div>
</x-app-layout>
