<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Comunicados</h2>
            @can('edu.gestionar')
            <a href="{{ route('admin.edu.comunicados.create') }}"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + Nuevo comunicado
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.edu.comunicados.index') }}" class="flex flex-wrap gap-2">
                <select name="institucion_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Todas las instituciones —</option>
                    @foreach ($instituciones as $i)
                        <option value="{{ $i->id }}" @selected(request('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                    @endforeach
                </select>
                <x-primary-button>Filtrar</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Asunto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Canal</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Envío</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Destinatarios</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($comunicados as $com)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $com->asunto }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $com->institucion?->nombre }}</td>
                            <td class="px-4 py-2 capitalize">{{ $com->canal ?? '—' }}</td>
                            <td class="px-4 py-2 text-center text-xs">{{ $com->fecha_envio?->format('d/m/Y H:i') ?? 'Pendiente' }}</td>
                            <td class="px-4 py-2 text-center">{{ $com->destinatarios->count() }}</td>
                            @can('edu.gestionar')
                            <td class="px-4 py-2 text-right space-x-2">
                                <a href="{{ route('admin.edu.comunicados.show', $com) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                                <a href="{{ route('admin.edu.comunicados.edit', $com) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                <form method="POST" action="{{ route('admin.edu.comunicados.destroy', $com) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar comunicado?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin comunicados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $comunicados->links() }}</div>
        </div>
    </div>
</x-app-layout>
