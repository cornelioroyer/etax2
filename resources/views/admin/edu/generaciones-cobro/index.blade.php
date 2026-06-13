<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Generaciones de cobro</h2>
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
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Generar cobros</h3>
                <form method="POST" action="{{ route('admin.edu.generaciones-cobro.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label for="gc_institucion_id" value="Institución *" />
                            <select id="gc_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="gc_plan_cobro_id" value="Plan de cobro *" />
                            <select id="gc_plan_cobro_id" name="plan_cobro_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($planes as $p)
                                    <option value="{{ $p->id }}" @selected(old('plan_cobro_id') == $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="gc_periodo_id" value="Período" />
                            <select id="gc_periodo_id" name="periodo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                @foreach ($periodos as $p)
                                    <option value="{{ $p->id }}" @selected(old('periodo_id') == $p->id)>{{ $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="gc_anio" value="Año *" />
                            <x-text-input id="gc_anio" name="anio" type="number" class="mt-1 block w-full"
                                :value="old('anio', now()->year)" required min="2020" max="2099" />
                        </div>
                        <div>
                            <x-input-label for="gc_mes" value="Mes *" />
                            <select id="gc_mes" name="mes"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                @foreach(range(1,12) as $m)
                                    <option value="{{ $m }}" @selected(old('mes', now()->month) == $m)>
                                        {{ \Carbon\Carbon::create()->month($m)->locale('es')->monthName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="gc_cuota" value="Número de cuota" />
                            <x-text-input id="gc_cuota" name="numero_cuota" type="number" class="mt-1 block w-full"
                                :value="old('numero_cuota', 1)" min="1" />
                        </div>
                        <div>
                            <x-input-label for="gc_fecha_venc" value="Fecha vencimiento *" />
                            <x-text-input id="gc_fecha_venc" name="fecha_vencimiento" type="date" class="mt-1 block w-full"
                                :value="old('fecha_vencimiento')" required />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Generar cobros</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            <form method="GET" action="{{ route('admin.edu.generaciones-cobro.index') }}" class="flex flex-wrap gap-2">
                <select name="plan_cobro_id" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Todos los planes —</option>
                    @foreach ($planes as $p)
                        <option value="{{ $p->id }}" @selected(request('plan_cobro_id') == $p->id)>{{ $p->nombre }}</option>
                    @endforeach
                </select>
                <select name="anio" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Año —</option>
                    @foreach(range(now()->year, now()->year - 3, -1) as $yr)
                        <option value="{{ $yr }}" @selected(request('anio') == $yr)>{{ $yr }}</option>
                    @endforeach
                </select>
                <select name="mes" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">— Mes —</option>
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected(request('mes') == $m)>{{ $m }}</option>
                    @endforeach
                </select>
                <x-primary-button>Filtrar</x-primary-button>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Plan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Año</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Mes</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Cuota</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Vencimiento</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Generado</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($generaciones as $gen)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $gen->planCobro?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $gen->institucion?->nombre }}</td>
                            <td class="px-4 py-2 text-center">{{ $gen->anio }}</td>
                            <td class="px-4 py-2 text-center">{{ $gen->mes }}</td>
                            <td class="px-4 py-2 text-center">{{ $gen->numero_cuota }}</td>
                            <td class="px-4 py-2 text-center">{{ $gen->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-center text-xs text-gray-500">{{ $gen->fecha_generacion?->format('d/m/Y') }}</td>
                            @can('edu.gestionar')
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('admin.edu.generaciones-cobro.destroy', $gen) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar generación? Se eliminarán los cargos asociados.')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">Sin generaciones de cobro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $generaciones->links() }}</div>
        </div>
    </div>
</x-app-layout>
