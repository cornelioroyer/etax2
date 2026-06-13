<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Esquemas de Calificación</h2>
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
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo esquema</h3>
                <form method="POST" action="{{ route('admin.edu.esquemas.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label for="esq_institucion_id" value="Institución *" />
                            <select id="esq_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="esq_codigo" value="Código *" />
                            <x-text-input id="esq_codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="esq_nombre" value="Nombre *" />
                            <x-text-input id="esq_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar esquema</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            @forelse ($esquemas as $esquema)
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="{ open: false }">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <button @click="open = !open" class="text-indigo-600 hover:text-indigo-800">
                            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <div>
                            <span class="font-mono text-xs text-gray-500">{{ $esquema->codigo }}</span>
                            <span class="ml-2 font-semibold text-sm">{{ $esquema->nombre }}</span>
                            <span class="ml-2 text-xs text-gray-500">{{ $esquema->institucion?->nombre }}</span>
                        </div>
                    </div>
                    @can('edu.gestionar')
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">{{ $esquema->detalles->count() }} componentes</span>
                        <form method="POST" action="{{ route('admin.edu.esquemas.destroy', $esquema) }}"
                              onsubmit="return confirm('¿Eliminar esquema?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </div>
                    @endcan
                </div>
                <div x-show="open" x-cloak>
                    @if($esquema->detalles->count())
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">%</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Orden</th>
                                @can('edu.gestionar')<th></th>@endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($esquema->detalles->sortBy('orden') as $det)
                            <tr>
                                <td class="px-4 py-2 font-mono">{{ $det->codigo }}</td>
                                <td class="px-4 py-2">{{ $det->nombre }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $det->tipo_evaluacion ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-semibold">{{ $det->porcentaje }}%</td>
                                <td class="px-4 py-2 text-center">{{ $det->orden ?? '—' }}</td>
                                @can('edu.gestionar')
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('admin.edu.esquemas.detalles.destroy', [$esquema, $det]) }}"
                                          onsubmit="return confirm('¿Eliminar componente?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <p class="px-4 py-3 text-sm text-gray-400">Sin componentes aún.</p>
                    @endif

                    @can('edu.gestionar')
                    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                        <h4 class="text-xs font-semibold text-gray-600 mb-2">Agregar componente</h4>
                        <form method="POST" action="{{ route('admin.edu.esquemas.detalles.store', $esquema) }}">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                                <div>
                                    <x-text-input name="codigo" type="text" class="block w-full text-xs" placeholder="Código *" required maxlength="30" />
                                </div>
                                <div>
                                    <x-text-input name="nombre" type="text" class="block w-full text-xs" placeholder="Nombre *" required maxlength="200" />
                                </div>
                                <div>
                                    <x-text-input name="tipo_evaluacion" type="text" class="block w-full text-xs" placeholder="Tipo evaluación" maxlength="50" />
                                </div>
                                <div>
                                    <x-text-input name="porcentaje" type="number" step="0.01" class="block w-full text-xs" placeholder="% *" required min="0" max="100" />
                                </div>
                                <div>
                                    <x-text-input name="orden" type="number" class="block w-full text-xs" placeholder="Orden" min="0" />
                                </div>
                            </div>
                            <div class="mt-2">
                                <x-primary-button>Agregar</x-primary-button>
                            </div>
                        </form>
                    </div>
                    @endcan
                </div>
            </div>
            @empty
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-gray-400">Sin esquemas de calificación.</div>
            @endforelse
            <div>{{ $esquemas->links() }}</div>
        </div>
    </div>
</x-app-layout>
