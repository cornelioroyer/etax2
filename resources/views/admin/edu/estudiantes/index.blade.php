<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Estudiantes</h2>
            @can('edu.gestionar')
            <a href="{{ route('admin.edu.estudiantes.create') }}"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + Nuevo estudiante
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Búsqueda --}}
            <form method="GET" action="{{ route('admin.edu.estudiantes.index') }}" class="flex gap-2">
                <x-text-input name="q" type="search" class="block w-full" placeholder="Buscar por nombre, cédula o código..."
                    :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Identificación</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Ingreso</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($estudiantes as $est)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">{{ $est->codigo_estudiante ?? '—' }}</td>
                            <td class="px-4 py-2 font-medium">{{ $est->contacto?->nombre }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $est->contacto?->identificacion ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $est->institucion?->nombre }}</td>
                            <td class="px-4 py-2 text-center">
                                @php $sc = ['activo'=>'bg-green-100 text-green-800','inactivo'=>'bg-gray-100 text-gray-600','egresado'=>'bg-blue-100 text-blue-800','retirado'=>'bg-red-100 text-red-800']; @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $sc[$est->estado] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($est->estado) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center text-xs text-gray-600">{{ $est->fecha_ingreso ? \Carbon\Carbon::parse($est->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.edu.estudiantes.show', $est) }}"
                                    class="text-xs text-indigo-600 hover:underline">Ver</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin estudiantes registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $estudiantes->links() }}</div>
        </div>
    </div>
</x-app-layout>
