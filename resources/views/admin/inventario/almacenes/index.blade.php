<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Almacenes</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @can('inventario.gestionar')
            <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Nuevo almacén</h3>
                <form method="POST" action="{{ route('admin.inventario.almacenes.store') }}" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Código</label>
                        <input type="text" name="codigo" value="{{ old('codigo') }}" placeholder="PRINCIPAL"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500 uppercase w-32" required>
                    </div>
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs text-gray-500 mb-1">Nombre</label>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" placeholder="Almacén principal"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:ring-blue-500" required>
                    </div>
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Crear</button>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($almacenes as $alm)
                            <tr class="{{ $alm->activo ? '' : 'bg-gray-50 opacity-60' }}">
                                <td class="px-4 py-3 font-mono font-medium">{{ $alm->codigo }}</td>
                                <td class="px-4 py-3">{{ $alm->nombre }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $alm->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $alm->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right flex justify-end gap-3">
                                    <a href="{{ route('admin.inventario.almacenes.existencias', $alm) }}" class="text-blue-600 hover:underline text-xs">Ver existencias</a>
                                    @can('inventario.gestionar')
                                        <form method="POST" action="{{ route('admin.inventario.almacenes.toggle', $alm) }}">
                                            @csrf
                                            <button class="text-xs {{ $alm->activo ? 'text-red-500 hover:underline' : 'text-green-600 hover:underline' }}">
                                                {{ $alm->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No hay almacenes registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
