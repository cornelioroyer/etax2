<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planilla — Empleados</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <x-text-input name="buscar" type="text" class="text-sm" placeholder="Código, nombre o cédula..."
                        value="{{ request('buscar') }}" />
                    <select name="status" class="rounded-md border-gray-300 text-sm">
                        <option value="">Todos los estados</option>
                        @foreach (\App\Models\NomEmpleado::STATUSES as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(request('status') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Filtrar</button>
                </form>
                @can('nomina.gestionar')
                <a href="{{ route('admin.nomina.empleados.create') }}"
                   class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color:#0d2d5e">+ Nuevo empleado</a>
                @endcan
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cédula</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Departamento / Cargo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Planilla</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Salario</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr class="{{ $item->pagable() ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $item->codigo }}</td>
                                <td class="px-4 py-2">{{ $item->nombreCompleto() }}</td>
                                <td class="px-4 py-2">{{ $item->cedula }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    {{ $item->departamento?->nombre ?? '—' }} / {{ $item->cargo?->nombre ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs">{{ \App\Models\NomEmpleado::TIPOS_PLANILLA[$item->tipo_planilla] ?? $item->tipo_planilla }}</td>
                                <td class="px-4 py-2 text-right font-mono">
                                    @if ($item->esPorHora())
                                        B/. {{ number_format((float) $item->tasa_hora, 2) }}/h
                                    @else
                                        B/. {{ number_format((float) $item->salario_mensual, 2) }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $item->pagable() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $item->etiquetaStatus() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('admin.nomina.empleados.edit', $item) }}" class="text-xs text-indigo-600 hover:underline">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">Sin empleados registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links() }}
        </div>
    </div>
</x-app-layout>
