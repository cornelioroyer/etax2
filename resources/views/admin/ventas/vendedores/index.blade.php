<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vendedores</h2>
            @can('ventas.gestionar')
                <a href="{{ route('admin.ventas.vendedores.create') }}"
                   class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nuevo vendedor
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Contacto</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($vendedores as $v)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono font-medium">{{ $v->codigo }}</td>
                                <td class="px-4 py-3">{{ $v->contacto?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-medium {{ $v->activo ? 'text-green-700' : 'text-gray-400' }}">
                                        {{ $v->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 flex gap-2">
                                    <a href="{{ route('admin.ventas.vendedores.show', $v) }}" class="text-xs text-blue-600 hover:underline">Ver</a>
                                    @can('ventas.gestionar')
                                        <form method="POST" action="{{ route('admin.ventas.vendedores.toggle', $v) }}">
                                            @csrf
                                            <button type="submit" class="text-xs text-gray-400 hover:text-gray-600">
                                                {{ $v->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Sin vendedores registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
