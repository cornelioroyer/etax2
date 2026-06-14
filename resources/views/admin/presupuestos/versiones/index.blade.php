<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Presupuestos — Versiones</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.presupuestos.index') }}" class="text-gray-500 hover:text-gray-900">Presupuestos</a>
                @can('presupuestos.gestionar')
                    <a href="{{ route('admin.presupuestos.versiones.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nueva versión</a>
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
                <x-text-input name="q" type="search" class="w-56" placeholder="Buscar versión..." :value="$search" />
                <x-primary-button>Buscar</x-primary-button>
                @if ($search)
                    <a href="{{ route('admin.presupuestos.versiones.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Presupuestos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($versiones as $v)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium">{{ $v->nombre }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $v->activa ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $v->activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-gray-600">{{ $v->presupuestos_count }}</td>
                                <td class="px-4 py-2 text-right space-x-3">
                                    @can('presupuestos.gestionar')
                                        <a href="{{ route('admin.presupuestos.versiones.edit', $v) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                        <form method="POST" action="{{ route('admin.presupuestos.versiones.destroy', $v) }}" class="inline"
                                              onsubmit="return confirm('¿Eliminar versión {{ $v->nombre }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center text-gray-400">Sin versiones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $versiones->links() }}
        </div>
    </div>
</x-app-layout>
