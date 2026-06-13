<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $tecnico->nombre_publico }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ $tecnico->codigo }} · {{ \App\Models\TallerTecnico::TIPOS[$tecnico->tipo_tecnico] ?? $tecnico->tipo_tecnico }} · {{ $tecnico->taller->nombre }}</p>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.tecnicos.index', ['taller_id' => $tecnico->taller_id]) }}" class="text-gray-500 hover:text-gray-900">← Técnicos</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.tecnicos.edit', $tecnico) }}" class="text-gray-600 hover:text-gray-900">Editar</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Datos --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Precio/hora</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ $tecnico->precio_hora ? number_format($tecnico->precio_hora, 2) : '—' }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Costo/hora</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ $tecnico->costo_hora ? number_format($tecnico->costo_hora, 2) : '—' }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Horas/día</p>
                    <p class="mt-1 text-xl font-bold text-indigo-700">{{ $tecnico->capacidad_horas_dia ?? '—' }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Especialidades</p>
                    <p class="mt-1 text-xl font-bold text-green-700">{{ $tecnico->especialidades->count() }}</p>
                </div>
            </div>

            {{-- Especialidades actuales --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Especialidades asignadas</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Especialidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nivel</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tecnico->especialidades as $te)
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $te->especialidad->nombre }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $te->nivel ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    @can('taller.gestionar')
                                        <form method="POST"
                                              action="{{ route('admin.taller.tecnicos.especialidades.destroy', [$tecnico, $te]) }}"
                                              class="inline"
                                              onsubmit="return confirm('¿Remover esta especialidad?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-600 hover:underline">Remover</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-4 text-center text-gray-400 text-sm">Sin especialidades asignadas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Agregar especialidad --}}
            @can('taller.gestionar')
            @if ($especialidadesDisponibles->isNotEmpty())
            <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Agregar especialidad</h3>
                <form method="POST" action="{{ route('admin.taller.tecnicos.especialidades.store', $tecnico) }}" class="flex gap-3 flex-wrap items-end">
                    @csrf
                    <div>
                        <x-input-label for="esp_id" value="Especialidad *" />
                        <select id="esp_id" name="especialidad_id" required
                            class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Seleccione —</option>
                            @foreach ($especialidadesDisponibles as $e)
                                <option value="{{ $e->id }}">{{ $e->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="nivel" value="Nivel" />
                        <x-text-input id="nivel" name="nivel" type="text" class="mt-1"
                            placeholder="básico, avanzado..." maxlength="50" />
                    </div>
                    <x-primary-button>+ Agregar</x-primary-button>
                </form>
            </div>
            @endif
            @endcan

            @can('taller.gestionar')
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <h3 class="text-sm font-semibold text-red-700 mb-2">Zona de peligro</h3>
                <form method="POST" action="{{ route('admin.taller.tecnicos.destroy', $tecnico) }}"
                      onsubmit="return confirm('¿Eliminar el técnico {{ $tecnico->nombre_publico }}?')">
                    @csrf @method('DELETE')
                    <button class="rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">Eliminar técnico</button>
                </form>
            </div>
            @endcan
        </div>
    </div>
</x-app-layout>
