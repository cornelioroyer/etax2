<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Matrículas</h2>
            @can('edu.gestionar')
            <a href="{{ route('admin.edu.matriculas.create') }}"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + Nueva matrícula
            </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.edu.matriculas.index') }}" class="flex flex-wrap gap-2">
                <select name="periodo_id"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— Todos los períodos —</option>
                    @foreach ($periodos as $p)
                        <option value="{{ $p->id }}" @selected($periodoId == $p->id)>{{ $p->nombre }} ({{ $p->anio }})</option>
                    @endforeach
                </select>
                <select name="grupo_id"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— Todos los grupos —</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" @selected($grupoId == $g->id)>{{ $g->nombre }}</option>
                    @endforeach
                </select>
                <x-text-input name="q" type="search" class="block" placeholder="Buscar estudiante..."
                    :value="$search" />
                <x-primary-button>Filtrar</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Estudiante</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Período</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Grado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Grupo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($matriculas as $mat)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $mat->estudiante?->contacto?->nombre }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $mat->periodo?->nombre }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $mat->grado?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $mat->grupo?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @php $sc = ['activo'=>'bg-green-100 text-green-800','retirado'=>'bg-red-100 text-red-800','egresado'=>'bg-blue-100 text-blue-800','suspendido'=>'bg-yellow-100 text-yellow-800']; @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $sc[$mat->estado] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($mat->estado) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center text-xs text-gray-600">{{ $mat->fecha_matricula?->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.edu.matriculas.show', $mat) }}" class="text-xs text-indigo-600 hover:underline">Ver</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin matrículas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $matriculas->links() }}</div>
        </div>
    </div>
</x-app-layout>
