<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Presupuestos — Escenarios</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.presupuestos.index') }}" class="text-gray-500 hover:text-gray-900">Presupuestos</a>
                @can('presupuestos.gestionar')
                    <a href="{{ route('admin.presupuestos.escenarios.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nuevo escenario</a>
                @endcan
            </div>
        </div>
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

            <form method="GET" class="flex gap-2 flex-wrap">
                <x-text-input name="q" type="search" class="w-56" placeholder="Buscar escenario..." :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
                @if ($search)
                    <a href="{{ route('admin.presupuestos.escenarios.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Presupuestos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($escenarios as $e)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium">{{ $e->nombre }}</td>
                                <td class="px-4 py-2 text-right text-gray-600">{{ $e->presupuestos_count }}</td>
                                <td class="px-4 py-2 text-right space-x-3">
                                    @can('presupuestos.gestionar')
                                        <a href="{{ route('admin.presupuestos.escenarios.edit', $e) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                        <form method="POST" action="{{ route('admin.presupuestos.escenarios.destroy', $e) }}" class="inline"
                                              onsubmit="return confirm('¿Eliminar escenario {{ $e->nombre }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-12 text-center text-gray-400">Sin escenarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $escenarios->links() }}
        </div>
    </div>
</x-app-layout>
