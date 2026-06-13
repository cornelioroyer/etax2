<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Configuración</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Tabs --}}
            <div x-data="{ tab: '{{ $tab }}' }">
                <div class="flex border-b border-gray-200 gap-1 overflow-x-auto">
                    @foreach ([
                        'sucursales'   => 'Sucursales',
                        'departamentos'=> 'Departamentos',
                        'centros-costo'=> 'Centros de costo',
                        'proyectos'    => 'Proyectos',
                        'monedas'      => 'Monedas',
                        'retenciones'  => 'Retenciones',
                    ] as $key => $label)
                        <button type="button" @click="tab='{{ $key }}'"
                                :class="tab==='{{ $key }}' ? 'border-b-2 border-[#0d2d5e] text-[#0d2d5e] font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm whitespace-nowrap -mb-px">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- SUCURSALES --}}
                <div x-show="tab==='sucursales'" x-cloak class="pt-4 space-y-4">
                    @can('contabilidad.gestionar')
                        <form method="POST" action="{{ route('admin.configuracion.sucursales.store') }}"
                              class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
                            @csrf
                            <div><label class="block text-xs text-gray-500 mb-1">Código *</label>
                                <input type="text" name="codigo" required maxlength="30" class="rounded-md border-gray-300 text-sm shadow-sm uppercase" placeholder="SUC01"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Nombre *</label>
                                <input type="text" name="nombre" required maxlength="150" class="rounded-md border-gray-300 text-sm shadow-sm" placeholder="Nombre sucursal"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Dirección</label>
                                <input type="text" name="direccion" maxlength="200" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Teléfono</label>
                                <input type="text" name="telefono" maxlength="50" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                        </form>
                    @endcan
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Dirección</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3"></th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($sucursales as $s)
                                    <tr><td class="px-4 py-3 font-mono">{{ $s->codigo }}</td><td class="px-4 py-3">{{ $s->nombre }}</td>
                                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $s->direccion ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs {{ $s->activa ? 'text-green-700' : 'text-gray-400' }}">{{ $s->activa ? 'Activa' : 'Inactiva' }}</td>
                                        <td class="px-4 py-3">@can('contabilidad.gestionar')
                                            <form method="POST" action="{{ route('admin.configuracion.sucursales.toggle', $s) }}" class="inline">@csrf
                                                <button type="submit" class="text-xs text-gray-400 hover:text-gray-600">{{ $s->activa ? 'Desactivar' : 'Activar' }}</button>
                                            </form>@endcan</td>
                                    </tr>
                                @empty<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin sucursales.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- DEPARTAMENTOS --}}
                <div x-show="tab==='departamentos'" x-cloak class="pt-4 space-y-4">
                    @can('contabilidad.gestionar')
                        <form method="POST" action="{{ route('admin.configuracion.departamentos.store') }}"
                              class="bg-white shadow-sm sm:rounded-lg p-4 flex gap-3 items-end">
                            @csrf
                            <div><label class="block text-xs text-gray-500 mb-1">Código *</label>
                                <input type="text" name="codigo" required maxlength="30" class="rounded-md border-gray-300 text-sm shadow-sm uppercase" placeholder="DPTO01"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Nombre *</label>
                                <input type="text" name="nombre" required maxlength="150" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                        </form>
                    @endcan
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Estado</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($departamentos as $d)
                                    <tr><td class="px-4 py-3 font-mono">{{ $d->codigo }}</td><td class="px-4 py-3">{{ $d->nombre }}</td>
                                        <td class="px-4 py-3 text-xs {{ $d->activo ? 'text-green-700' : 'text-gray-400' }}">{{ $d->activo ? 'Activo' : 'Inactivo' }}</td></tr>
                                @empty<tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Sin departamentos.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- CENTROS DE COSTO --}}
                <div x-show="tab==='centros-costo'" x-cloak class="pt-4 space-y-4">
                    @can('contabilidad.gestionar')
                        <form method="POST" action="{{ route('admin.configuracion.centros-costo.store') }}"
                              class="bg-white shadow-sm sm:rounded-lg p-4 flex gap-3 items-end">
                            @csrf
                            <div><label class="block text-xs text-gray-500 mb-1">Código *</label>
                                <input type="text" name="codigo" required maxlength="30" class="rounded-md border-gray-300 text-sm shadow-sm uppercase" placeholder="CC01"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Nombre *</label>
                                <input type="text" name="nombre" required maxlength="150" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                        </form>
                    @endcan
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Estado</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($centrosCostos as $cc)
                                    <tr><td class="px-4 py-3 font-mono">{{ $cc->codigo }}</td><td class="px-4 py-3">{{ $cc->nombre }}</td>
                                        <td class="px-4 py-3 text-xs {{ $cc->activo ? 'text-green-700' : 'text-gray-400' }}">{{ $cc->activo ? 'Activo' : 'Inactivo' }}</td></tr>
                                @empty<tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Sin centros de costo.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- PROYECTOS --}}
                <div x-show="tab==='proyectos'" x-cloak class="pt-4 space-y-4">
                    @can('contabilidad.gestionar')
                        <form method="POST" action="{{ route('admin.configuracion.proyectos.store') }}"
                              class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
                            @csrf
                            <div><label class="block text-xs text-gray-500 mb-1">Código *</label>
                                <input type="text" name="codigo" required maxlength="30" class="rounded-md border-gray-300 text-sm shadow-sm uppercase" placeholder="PROY01"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Nombre *</label>
                                <input type="text" name="nombre" required maxlength="150" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Inicio</label>
                                <input type="date" name="fecha_inicio" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Fin</label>
                                <input type="date" name="fecha_fin" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                        </form>
                    @endcan
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Inicio</th><th class="px-4 py-3">Fin</th><th class="px-4 py-3">Estado</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($proyectos as $p)
                                    <tr><td class="px-4 py-3 font-mono">{{ $p->codigo }}</td><td class="px-4 py-3">{{ $p->nombre }}</td>
                                        <td class="px-4 py-3 text-xs">{{ $p->fecha_inicio?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs">{{ $p->fecha_fin?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs font-medium {{ $p->estado === 'ACTIVO' ? 'text-green-700' : 'text-gray-500' }}">{{ $p->estado }}</td></tr>
                                @empty<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin proyectos.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- MONEDAS --}}
                <div x-show="tab==='monedas'" x-cloak class="pt-4 space-y-4">
                    @can('contabilidad.gestionar')
                        <div class="grid grid-cols-2 gap-4">
                            <form method="POST" action="{{ route('admin.configuracion.monedas.store') }}"
                                  class="bg-white shadow-sm sm:rounded-lg p-4 space-y-3">
                                @csrf
                                <h3 class="text-sm font-semibold text-gray-700">Nueva moneda</h3>
                                <div class="flex gap-2">
                                    <div><label class="block text-xs text-gray-500 mb-1">Código</label>
                                        <input type="text" name="codigo" required maxlength="10" class="rounded-md border-gray-300 text-sm shadow-sm w-24 uppercase" placeholder="USD"></div>
                                    <div><label class="block text-xs text-gray-500 mb-1">Símbolo</label>
                                        <input type="text" name="simbolo" maxlength="5" class="rounded-md border-gray-300 text-sm shadow-sm w-16" placeholder="$"></div>
                                    <div class="flex-1"><label class="block text-xs text-gray-500 mb-1">Nombre</label>
                                        <input type="text" name="nombre" required maxlength="100" class="w-full rounded-md border-gray-300 text-sm shadow-sm"></div>
                                </div>
                                <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Crear</button>
                            </form>
                            <form method="POST" action="{{ route('admin.configuracion.tasas.store') }}"
                                  class="bg-white shadow-sm sm:rounded-lg p-4 space-y-3">
                                @csrf
                                <h3 class="text-sm font-semibold text-gray-700">Registrar tasa de cambio</h3>
                                <div class="flex gap-2 items-end">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Moneda</label>
                                        <select name="moneda_id" required class="rounded-md border-gray-300 text-sm shadow-sm">
                                            <option value="">—</option>
                                            @foreach ($monedas as $m)
                                                <option value="{{ $m->id }}">{{ $m->codigo }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div><label class="block text-xs text-gray-500 mb-1">Fecha</label>
                                        <input type="date" name="fecha" required value="{{ today()->toDateString() }}" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                                    <div><label class="block text-xs text-gray-500 mb-1">Tasa (vs B/.)</label>
                                        <input type="number" name="tasa" required step="0.000001" min="0.000001" class="rounded-md border-gray-300 text-sm shadow-sm w-28" placeholder="1.000000"></div>
                                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Guardar</button>
                                </div>
                            </form>
                        </div>
                    @endcan
                    @foreach ($monedas as $m)
                        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-3">
                                <span class="font-mono font-bold text-gray-700">{{ $m->codigo }}</span>
                                <span class="text-sm text-gray-500">{{ $m->nombre }}</span>
                                @if ($m->simbolo)<span class="text-xs text-gray-400">({{ $m->simbolo }})</span>@endif
                            </div>
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs font-semibold uppercase text-gray-500 bg-gray-50">
                                    <tr><th class="px-4 py-2">Fecha</th><th class="px-4 py-2 text-right">Tasa vs B/.</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse ($m->tasas->take(5) as $t)
                                        <tr><td class="px-4 py-2">{{ $t->fecha->format('d/m/Y') }}</td>
                                            <td class="px-4 py-2 text-right font-mono">{{ number_format((float)$t->tasa, 6) }}</td></tr>
                                    @empty<tr><td colspan="2" class="px-4 py-3 text-center text-gray-400 text-xs">Sin tasas registradas.</td></tr>@endforelse
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>

                {{-- RETENCIONES --}}
                <div x-show="tab==='retenciones'" x-cloak class="pt-4 space-y-4">
                    @can('contabilidad.gestionar')
                        <form method="POST" action="{{ route('admin.configuracion.retenciones.store') }}"
                              class="bg-white shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
                            @csrf
                            <div><label class="block text-xs text-gray-500 mb-1">Código *</label>
                                <input type="text" name="codigo" required maxlength="30" class="rounded-md border-gray-300 text-sm shadow-sm uppercase" placeholder="RET01"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Nombre *</label>
                                <input type="text" name="nombre" required maxlength="150" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                            <div><label class="block text-xs text-gray-500 mb-1">Tipo</label>
                                <select name="tipo" required class="rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="ITBMS">ITBMS</option>
                                    <option value="ISR">ISR</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>
                            <div><label class="block text-xs text-gray-500 mb-1">% *</label>
                                <input type="number" name="porcentaje" required step="0.0001" min="0" max="100" class="rounded-md border-gray-300 text-sm shadow-sm w-24" placeholder="7.0000"></div>
                            <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                        </form>
                    @endcan
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr><th class="px-4 py-3">Código</th><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3 text-right">%</th><th class="px-4 py-3">Estado</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($retenciones as $r)
                                    <tr><td class="px-4 py-3 font-mono">{{ $r->codigo }}</td><td class="px-4 py-3">{{ $r->nombre }}</td>
                                        <td class="px-4 py-3 text-xs">{{ $r->tipo }}</td>
                                        <td class="px-4 py-3 text-right">{{ $r->porcentaje }}%</td>
                                        <td class="px-4 py-3 text-xs {{ $r->activa ? 'text-green-700' : 'text-gray-400' }}">{{ $r->activa ? 'Activa' : 'Inactiva' }}</td></tr>
                                @empty<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin retenciones.</td></tr>@endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
