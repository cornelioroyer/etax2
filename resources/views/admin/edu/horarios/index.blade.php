<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Horarios</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @can('edu.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo horario</h3>
                <form method="POST" action="{{ route('admin.edu.horarios.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label for="hor_institucion_id" value="Institución *" />
                            <select id="hor_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="hor_periodo_id" value="Período" />
                            <select id="hor_periodo_id" name="periodo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($periodos as $p)
                                    <option value="{{ $p->id }}" @selected(old('periodo_id') == $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="hor_grupo_id" value="Grupo" />
                            <select id="hor_grupo_id" name="grupo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($grupos as $g)
                                    <option value="{{ $g->id }}" @selected(old('grupo_id') == $g->id)>{{ $g->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="hor_nombre" value="Nombre *" />
                            <x-text-input id="hor_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar horario</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            @forelse ($horarios as $horario)
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="{ open: false }">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <button @click="open = !open" class="text-indigo-600">
                            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <div>
                            <span class="font-semibold text-sm">{{ $horario->nombre }}</span>
                            <span class="ml-2 text-xs text-gray-500">
                                {{ $horario->grupo?->nombre }} — {{ $horario->periodo?->nombre }}
                            </span>
                        </div>
                    </div>
                    @can('edu.gestionar')
                    <form method="POST" action="{{ route('admin.edu.horarios.destroy', $horario) }}"
                          onsubmit="return confirm('¿Eliminar horario?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                    </form>
                    @endcan
                </div>
                <div x-show="open" x-cloak>
                    @if($horario->detalles->count())
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Día</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Hora inicio</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Hora fin</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Asignatura</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Docente</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Aula</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($horario->detalles as $det)
                            <tr>
                                <td class="px-4 py-2 capitalize">{{ $det->dia_semana }}</td>
                                <td class="px-4 py-2">{{ $det->hora_inicio }}</td>
                                <td class="px-4 py-2">{{ $det->hora_fin }}</td>
                                <td class="px-4 py-2">{{ $det->asignatura?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $det->docente?->contacto?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $det->aula ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <p class="px-4 py-3 text-sm text-gray-400">Sin bloques de horario.</p>
                    @endif
                </div>
            </div>
            @empty
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-gray-400">Sin horarios.</div>
            @endforelse
            <div>{{ $horarios->links() }}</div>
        </div>
    </div>
</x-app-layout>
