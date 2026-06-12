<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Asientos de diario</h2>
            @can('contabilidad.crear')
                <a href="{{ route('admin.asientos.create') }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    + Nuevo asiento
                </a>
            @endcan
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

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="col-span-2">
                        <x-input-label for="q" value="Buscar" />
                        <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$filtros['q'] ?? ''" placeholder="Número, descripción o referencia" />
                    </div>
                    <div>
                        <x-input-label for="estado" value="Estado" />
                        <select id="estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos</option>
                            @foreach (['BORRADOR', 'POSTEADO', 'ANULADO'] as $estado)
                                <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ ucfirst(strtolower($estado)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="desde" value="Desde" />
                        <x-text-input id="desde" name="desde" type="text" class="js-date mt-1 block w-full" :value="$filtros['desde'] ?? ''" />
                    </div>
                    <div>
                        <x-input-label for="hasta" value="Hasta" />
                        <x-text-input id="hasta" name="hasta" type="text" class="js-date mt-1 block w-full" :value="$filtros['hasta'] ?? ''" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Filtrar</button>
                    <a href="{{ route('admin.asientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                </div>
            </form>

            {{-- Tabla --}}
            @php
                $linkOrden = fn (string $col) => route('admin.asientos.index', array_merge(
                    request()->except('page'),
                    ['orden' => $col, 'dir' => ($orden === $col && $dir === 'asc') ? 'desc' : 'asc']
                ));
                $flecha = fn (string $col) => $orden === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
            @endphp
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3"><a href="{{ $linkOrden('numero') }}" class="hover:text-gray-800">Número{{ $flecha('numero') }}</a></th>
                                <th class="px-4 py-3"><a href="{{ $linkOrden('fecha') }}" class="hover:text-gray-800">Fecha{{ $flecha('fecha') }}</a></th>
                                <th class="px-4 py-3 hidden md:table-cell"><a href="{{ $linkOrden('descripcion') }}" class="hover:text-gray-800">Descripción{{ $flecha('descripcion') }}</a></th>
                                <th class="px-4 py-3 hidden lg:table-cell"><a href="{{ $linkOrden('referencia') }}" class="hover:text-gray-800">Referencia{{ $flecha('referencia') }}</a></th>
                                <th class="px-4 py-3 text-right"><a href="{{ $linkOrden('total_debito') }}" class="hover:text-gray-800">Débito{{ $flecha('total_debito') }}</a></th>
                                <th class="px-4 py-3 text-right hidden sm:table-cell"><a href="{{ $linkOrden('total_credito') }}" class="hover:text-gray-800">Crédito{{ $flecha('total_credito') }}</a></th>
                                <th class="px-4 py-3"><a href="{{ $linkOrden('estado') }}" class="hover:text-gray-800">Estado{{ $flecha('estado') }}</a></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($asientos as $asiento)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('admin.asientos.show', $asiento) }}" class="text-blue-700 hover:underline">{{ $asiento->numero }}</a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $asiento->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 hidden md:table-cell max-w-xs truncate">{{ $asiento->descripcion }}</td>
                                    <td class="px-4 py-3 hidden lg:table-cell">{{ $asiento->referencia }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $asiento->total_debito, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap hidden sm:table-cell">B/. {{ number_format((float) $asiento->total_credito, 2) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($asiento->estado === 'POSTEADO')
                                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Posteado</span>
                                        @elseif ($asiento->estado === 'BORRADOR')
                                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Borrador</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Anulado</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                        No hay asientos que coincidan con el filtro.
                                        @can('contabilidad.crear')
                                            <a href="{{ route('admin.asientos.create') }}" class="text-blue-700 underline">Crear el primero</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($asientos->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">{{ $asientos->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
