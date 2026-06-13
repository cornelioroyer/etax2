<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Evaluaciones</h2>
            @can('edu.gestionar')
            <a href="{{ route('admin.edu.evaluaciones.create') }}"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + Nueva evaluación
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.edu.evaluaciones.index') }}" class="flex flex-wrap gap-2">
                <select name="periodo_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Todos los períodos —</option>
                    @foreach ($periodos as $p)
                        <option value="{{ $p->id }}" @selected($periodoId == $p->id)>{{ $p->nombre }}</option>
                    @endforeach
                </select>
                <select name="grupo_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Todos los grupos —</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" @selected($grupoId == $g->id)>{{ $g->nombre }}</option>
                    @endforeach
                </select>
                <x-primary-button>Filtrar</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Título</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Asignatura</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Grupo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Período</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Ptaje máx.</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($evaluaciones as $ev)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $ev->titulo }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $ev->asignatura?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $ev->grupo?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $ev->periodo?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-center text-xs">{{ $ev->fecha_evaluacion?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ $ev->puntaje_maximo ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @php $sc = ['borrador'=>'bg-yellow-100 text-yellow-800','publicada'=>'bg-green-100 text-green-800','cerrada'=>'bg-gray-100 text-gray-600']; @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $sc[$ev->estado] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($ev->estado) }}
                                </span>
                            </td>
                            @can('edu.gestionar')
                            <td class="px-4 py-2 text-right space-x-2">
                                <a href="{{ route('admin.edu.evaluaciones.show', $ev) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                                <form method="POST" action="{{ route('admin.edu.evaluaciones.destroy', $ev) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar evaluación?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">Sin evaluaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $evaluaciones->links() }}</div>
        </div>
    </div>
</x-app-layout>
