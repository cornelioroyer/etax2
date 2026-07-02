<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planilla — Novedades (ingresos / deducciones / horas)</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            @can('nomina.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg" x-data="{ tipoRegistro: '{{ old('tipo_registro', 'VARIABLE') }}' }">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Registrar novedad</h3>
                <p class="mb-3 text-xs text-gray-400">
                    Las horas del período de un empleado <b>por hora</b> se registran con el concepto <b>03 — Salario Regular</b>
                    (cantidad = horas). Lo demás (horas extra, comisiones, préstamos) va con su concepto y monto.
                </p>
                <form method="POST" action="{{ route('admin.nomina.novedades.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label value="Empleado *" />
                            <select name="empleado_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                <option value="">— elegir —</option>
                                @foreach ($empleados as $e)
                                    <option value="{{ $e->id }}" @selected((int) old('empleado_id') === $e->id)>
                                        {{ $e->codigo }} — {{ trim($e->nombre.' '.$e->apellido) }}{{ $e->tipo_salario === 'POR_HORA' ? ' (por hora)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Concepto *" />
                            <select name="concepto_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                <option value="">— elegir —</option>
                                @foreach ($conceptos as $c)
                                    <option value="{{ $c->id }}" @selected((int) old('concepto_id') === $c->id)>
                                        {{ $c->codigo }} — {{ $c->descripcion }} ({{ $c->tipo === 'INGRESO' ? '+' : '−' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Aplicación *" />
                            <select name="tipo_registro" x-model="tipoRegistro" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                <option value="VARIABLE">Variable (un período)</option>
                                <option value="FIJA">Fija (cada período)</option>
                            </select>
                        </div>
                        <div x-show="tipoRegistro === 'VARIABLE'">
                            <x-input-label value="Período *" />
                            <select name="periodo_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                <option value="">— elegir —</option>
                                @foreach ($periodos as $p)
                                    <option value="{{ $p->id }}" @selected((int) old('periodo_id') === $p->id)>{{ $p->etiqueta() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Cantidad (horas)" />
                            <x-text-input name="cantidad" type="number" step="0.25" min="0" class="mt-1 block w-full"
                                value="{{ old('cantidad') }}" />
                        </div>
                        <div>
                            <x-input-label value="Monto B/." />
                            <x-text-input name="monto" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                value="{{ old('monto') }}" />
                        </div>
                        <div x-show="tipoRegistro === 'FIJA'" x-cloak>
                            <x-input-label value="Vigente desde" />
                            <x-text-input name="vigente_desde" type="date" class="mt-1 block w-full" value="{{ old('vigente_desde') }}" />
                        </div>
                        <div x-show="tipoRegistro === 'FIJA'" x-cloak>
                            <x-input-label value="Vigente hasta" />
                            <x-text-input name="vigente_hasta" type="date" class="mt-1 block w-full" value="{{ old('vigente_hasta') }}" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Descripción" />
                            <x-text-input name="descripcion" type="text" class="mt-1 block w-full" value="{{ old('descripcion') }}" maxlength="300" />
                        </div>
                    </div>
                    <div class="mt-4"><x-primary-button>Registrar novedad</x-primary-button></div>
                </form>
            </div>
            @endcan

            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-center gap-2 text-sm">
                    <select name="empleado_id" class="rounded-md border-gray-300 text-sm">
                        <option value="">Todos los empleados</option>
                        @foreach ($empleados as $e)
                            <option value="{{ $e->id }}" @selected((int) request('empleado_id') === $e->id)>{{ $e->codigo }} — {{ trim($e->nombre.' '.$e->apellido) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md border border-gray-300 bg-white px-3 py-2">Filtrar</button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Empleado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Concepto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Aplicación</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Cantidad</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Monto</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Activa</th>
                            @can('nomina.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr class="{{ $item->activo ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2">{{ $item->empleado->codigo }} — {{ trim($item->empleado->nombre.' '.$item->empleado->apellido) }}</td>
                                <td class="px-4 py-2">{{ $item->concepto->codigo }} — {{ $item->concepto->descripcion }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    {{ $item->tipo_registro === 'VARIABLE' ? ($item->periodo?->etiqueta() ?? 'Variable') : 'Fija' }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono">{{ $item->cantidad ? number_format((float) $item->cantidad, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ (float) $item->monto > 0 ? 'B/. '.number_format((float) $item->monto, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-center">{{ $item->activo ? '✓' : '—' }}</td>
                                @can('nomina.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <form method="POST" action="{{ route('admin.nomina.novedades.toggle', $item) }}" class="inline">
                                        @csrf
                                        <button class="text-xs text-gray-500 hover:underline">{{ $item->activo ? 'Desactivar' : 'Reactivar' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.nomina.novedades.destroy', $item) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar novedad?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin novedades registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links() }}
        </div>
    </div>
</x-app-layout>
