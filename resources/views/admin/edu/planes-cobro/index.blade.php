<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planes de cobro</h2>
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
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo plan de cobro</h3>
                <form method="POST" action="{{ route('admin.edu.planes-cobro.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="pc_institucion_id" value="Institución *" />
                            <select id="pc_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="pc_concepto_id" value="Concepto *" />
                            <select id="pc_concepto_id" name="concepto_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($conceptos as $c)
                                    <option value="{{ $c->id }}" @selected(old('concepto_id') == $c->id)>{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="pc_nombre" value="Nombre del plan *" />
                            <x-text-input id="pc_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="pc_aplica_a" value="Aplica a" />
                            <select id="pc_aplica_a" name="aplica_a"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— todos —</option>
                                <option value="todos" @selected(old('aplica_a')=='todos')>Todos los estudiantes</option>
                                <option value="matriculados" @selected(old('aplica_a')=='matriculados')>Solo matriculados</option>
                                <option value="nivel" @selected(old('aplica_a')=='nivel')>Por nivel</option>
                                <option value="grado" @selected(old('aplica_a')=='grado')>Por grado</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="pc_cuotas" value="Número de cuotas" />
                            <x-text-input id="pc_cuotas" name="cantidad_cuotas" type="number" class="mt-1 block w-full"
                                :value="old('cantidad_cuotas', 1)" min="1" />
                        </div>
                        <div>
                            <x-input-label for="pc_dia_venc" value="Día vencimiento" />
                            <x-text-input id="pc_dia_venc" name="dia_vencimiento" type="number" class="mt-1 block w-full"
                                :value="old('dia_vencimiento', 1)" min="1" max="31" />
                        </div>
                        <div>
                            <x-input-label for="pc_monto" value="Monto por cuota *" />
                            <x-text-input id="pc_monto" name="monto" type="number" step="0.01" class="mt-1 block w-full"
                                :value="old('monto')" required min="0" />
                        </div>
                        <div>
                            <x-input-label for="pc_fecha_ini" value="Fecha inicio" />
                            <x-text-input id="pc_fecha_ini" name="fecha_inicio" type="date" class="mt-1 block w-full"
                                :value="old('fecha_inicio')" />
                        </div>
                        <div>
                            <x-input-label for="pc_fecha_fin" value="Fecha fin" />
                            <x-text-input id="pc_fecha_fin" name="fecha_fin" type="date" class="mt-1 block w-full"
                                :value="old('fecha_fin')" />
                        </div>
                        <div class="flex items-center gap-2 mt-5">
                            <input type="hidden" name="generar_automatico" value="0">
                            <input type="checkbox" id="pc_automatico" name="generar_automatico" value="1"
                                {{ old('generar_automatico') ? 'checked' : '' }} class="rounded border-gray-300">
                            <label for="pc_automatico" class="text-sm">Generación automática</label>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar plan</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Concepto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Cuotas</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Monto</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Auto</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($planes as $plan)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $plan->nombre }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $plan->concepto?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $plan->institucion?->nombre }}</td>
                            <td class="px-4 py-2 text-center">{{ $plan->cantidad_cuotas }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($plan->monto, 2) }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($plan->generar_automatico)
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Sí</span>
                                @else
                                    <span class="text-xs text-gray-400">No</span>
                                @endif
                            </td>
                            @can('edu.gestionar')
                            <td class="px-4 py-2 text-right space-x-2">
                                <a href="{{ route('admin.edu.planes-cobro.edit', $plan) }}" class="text-xs text-indigo-600 hover:underline">Editar</a>
                                <form method="POST" action="{{ route('admin.edu.planes-cobro.destroy', $plan) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar plan?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin planes de cobro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $planes->links() }}</div>
        </div>
    </div>
</x-app-layout>
