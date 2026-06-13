<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Activos fijos</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.activos.categorias.index') }}" class="text-gray-500 hover:text-gray-900">Categorías</a>
                <a href="{{ route('admin.activos.ubicaciones.index') }}" class="text-gray-500 hover:text-gray-900">Ubicaciones</a>
                @can('activos.gestionar')
                    <a href="{{ route('admin.activos.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nuevo activo</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Categoría</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Valor compra</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Dep. acum.</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Valor libros</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($activos as $a)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono font-semibold whitespace-nowrap">{{ $a->codigo }}</td>
                                <td class="px-4 py-2 max-w-xs truncate">{{ $a->descripcion }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $a->categoria?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono">B/. {{ number_format($a->valor_compra, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-orange-700">B/. {{ number_format($a->dep_acumulada, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold">B/. {{ number_format($a->valor_libros, 2) }}</td>
                                <td class="px-4 py-2">@include('admin.activos.activos._estado', ['estado' => $a->estado])</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('admin.activos.show', $a) }}"
                                        class="text-xs text-indigo-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                    Sin activos registrados.
                                    @can('activos.gestionar')
                                        <a href="{{ route('admin.activos.create') }}" class="text-indigo-600 hover:underline">Crear el primero</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($activos->isNotEmpty())
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-xs font-semibold text-gray-600">Totales</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold">B/. {{ number_format($activos->sum('valor_compra'), 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold text-orange-700">B/. {{ number_format($activos->sum('dep_acumulada'), 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold">B/. {{ number_format($activos->sum('valor_libros'), 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
