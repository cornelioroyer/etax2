<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Zonas</h2>
            @can('zonas.crear')
                <a href="{{ route('admin.zonas.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Nueva zona</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="GET" class="flex gap-3 rounded-lg bg-white p-4 shadow-sm">
                <input name="search" value="{{ $search }}" placeholder="Buscar por descripción" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Buscar</button>
            </form>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Descripción</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Compañías</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($zonas as $zona)
                            <tr>
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $zona->description }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $zona->companias_count ?? $zona->companias()->count() }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    @can('zonas.editar')
                                        <a href="{{ route('admin.zonas.edit', $zona) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    @endcan
                                    @can('zonas.eliminar')
                                        <form method="POST" action="{{ route('admin.zonas.destroy', $zona) }}" class="inline" onsubmit="return confirm('Eliminar esta zona?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ms-3 text-red-600 hover:text-red-900">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">No hay zonas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $zonas->links() }}
        </div>
    </div>
</x-app-layout>
