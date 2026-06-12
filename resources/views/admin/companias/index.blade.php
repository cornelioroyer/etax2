<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compañías</h2>
            @can('companias.crear')
                <a href="{{ route('admin.companias.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Nueva compañía</a>
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
                <input name="search" value="{{ $search }}" placeholder="Buscar por nombre, RUC o email" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Buscar</button>
            </form>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Compañía</th>
                            <th class="hidden px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 md:table-cell">RUC</th>
                            <th class="hidden px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 lg:table-cell">Zona</th>
                            <th class="hidden px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 lg:table-cell">Expira</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Estado</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($companias as $compania)
                            <tr>
                                <td class="px-4 py-4 sm:px-6">
                                    <div class="font-medium text-gray-900">{{ $compania->nombre }}</div>
                                    <div class="text-sm text-gray-500">{{ $compania->email }}</div>
                                    <div class="text-xs text-gray-400 md:hidden">{{ $compania->ruc }} DV {{ $compania->dv }}</div>
                                </td>
                                <td class="hidden px-6 py-4 text-sm text-gray-700 md:table-cell">{{ $compania->ruc }} DV {{ $compania->dv }}</td>
                                <td class="hidden px-6 py-4 text-sm text-gray-700 lg:table-cell">{{ $compania->zona->description ?? '—' }}</td>
                                <td class="hidden px-6 py-4 text-sm text-gray-700 lg:table-cell">{{ $compania->fecha_de_expiracion?->format('d/m/Y') }}</td>
                                <td class="px-4 py-4 sm:px-6">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $compania->activa ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $compania->activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right text-sm font-medium sm:px-6">
                                    @can('companias.editar')
                                        <a href="{{ route('admin.companias.edit', $compania) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    @endcan
                                    @can('companias.eliminar')
                                        <form method="POST" action="{{ route('admin.companias.destroy', $compania) }}" class="inline" onsubmit="return confirm('Eliminar esta compañía?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ms-3 text-red-600 hover:text-red-900">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No hay compañías.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $companias->links() }}
        </div>
    </div>
</x-app-layout>
