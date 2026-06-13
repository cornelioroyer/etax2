<x-app-layout>
    @php
        $colorEstado = \App\Models\TallerOrden::colorEstado($orden->estado);
        $badgeClasses = [
            'blue'    => 'bg-blue-100 text-blue-700',
            'indigo'  => 'bg-indigo-100 text-indigo-700',
            'yellow'  => 'bg-yellow-100 text-yellow-700',
            'cyan'    => 'bg-cyan-100 text-cyan-700',
            'orange'  => 'bg-orange-100 text-orange-700',
            'amber'   => 'bg-amber-100 text-amber-700',
            'purple'  => 'bg-purple-100 text-purple-700',
            'teal'    => 'bg-teal-100 text-teal-700',
            'green'   => 'bg-green-100 text-green-700',
            'emerald' => 'bg-emerald-100 text-emerald-700',
            'gray'    => 'bg-gray-100 text-gray-600',
            'red'     => 'bg-red-100 text-red-700',
        ];
        $colorPrioridad = match($orden->prioridad) {
            'urgente' => 'red',
            'alta'    => 'orange',
            'normal'  => 'blue',
            default   => 'gray',
        };
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="font-bold text-2xl text-gray-800 font-mono">{{ $orden->numero }}</h2>
                    <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClasses[$colorEstado] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ \App\Models\TallerOrden::ESTADOS[$orden->estado] ?? $orden->estado }}
                    </span>
                    <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClasses[$colorPrioridad] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ \App\Models\TallerOrden::PRIORIDADES[$orden->prioridad] ?? $orden->prioridad }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-gray-500">
                    <a href="{{ route('admin.taller.ordenes.index') }}" class="hover:underline">Órdenes</a>
                    &rsaquo; {{ $orden->taller?->nombre }}
                </p>
            </div>
            <div class="flex gap-2 text-sm">
                <a href="{{ route('admin.taller.ordenes.index') }}" class="text-gray-500 hover:text-gray-900">← Órdenes</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.ordenes.edit', $orden) }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">Editar</a>
                @endcan
                <a href="#" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">
                    Imprimir
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash messages --}}
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Datos básicos --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-3 text-sm">
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Cliente</p>
                        <p class="mt-0.5 font-medium">{{ $orden->cliente?->nombre ?? ($orden->cliente_id ? 'ID '.$orden->cliente_id : '—') }}</p>
                        @if ($orden->cliente?->identificacion)
                            <p class="text-xs text-gray-500">{{ $orden->cliente->identificacion }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Equipo</p>
                        <p class="mt-0.5 font-medium">{{ $orden->equipo?->nombre ?? ($orden->equipo_id ? 'Equipo #'.$orden->equipo_id : '—') }}</p>
                        @if ($orden->equipo?->tipoEquipo)
                            <p class="text-xs text-gray-500">{{ $orden->equipo->tipoEquipo->nombre }}</p>
                        @endif
                        @if ($orden->equipo?->numero_serie)
                            <p class="text-xs text-gray-400 font-mono">S/N: {{ $orden->equipo->numero_serie }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Taller / Área</p>
                        <p class="mt-0.5 font-medium">{{ $orden->taller?->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Recepción</p>
                        <p class="mt-0.5">{{ $orden->fecha_recepcion?->format('d/m/Y H:i') ?? '—' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Prometida: {{ $orden->fecha_prometida?->format('d/m/Y H:i') ?? '—' }}</p>
                        @if ($orden->fecha_inicio)
                            <p class="text-xs text-gray-500">Inicio: {{ $orden->fecha_inicio->format('d/m/Y H:i') }}</p>
                        @endif
                        @if ($orden->fecha_fin)
                            <p class="text-xs text-gray-500">Fin: {{ $orden->fecha_fin->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Tipo / Origen</p>
                        <p class="mt-0.5">{{ \App\Models\TallerOrden::TIPOS_SERVICIO[$orden->tipo_servicio] ?? $orden->tipo_servicio }}</p>
                        <p class="text-xs text-gray-500">{{ \App\Models\TallerOrden::ORIGENES[$orden->origen] ?? $orden->origen }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Totales</p>
                        <p class="mt-0.5 font-mono">Subtotal: {{ number_format($orden->subtotal, 2) }}</p>
                        <p class="font-mono font-semibold">Total: {{ number_format($orden->total, 2) }}</p>
                        <p class="text-xs text-gray-500 font-mono">Saldo: {{ number_format($orden->saldo, 2) }}</p>
                    </div>
                </div>

                @if ($orden->sintomas_reportados || $orden->observacion_recepcion)
                    <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                        @if ($orden->sintomas_reportados)
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Síntomas reportados</p>
                                <p class="mt-0.5 text-gray-700">{{ $orden->sintomas_reportados }}</p>
                            </div>
                        @endif
                        @if ($orden->observacion_recepcion)
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Observación de recepción</p>
                                <p class="mt-0.5 text-gray-700">{{ $orden->observacion_recepcion }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($orden->medidor_valor)
                    <div class="mt-3 text-sm">
                        <span class="text-xs font-medium uppercase text-gray-500">Medidor:</span>
                        <span class="font-mono ml-1">{{ number_format($orden->medidor_valor, 2) }} {{ $orden->medidor_unidad }}</span>
                    </div>
                @endif
            </div>

            {{-- Cambiar estado --}}
            @can('taller.gestionar')
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Cambiar estado</h3>
                    <form method="POST" action="{{ route('admin.taller.ordenes.cambiar-estado', $orden) }}"
                        class="flex flex-wrap gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Nuevo estado</label>
                            <select name="estado" required
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerOrden::ESTADOS as $val => $label)
                                    <option value="{{ $val }}" {{ $orden->estado === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 min-w-48">
                            <label class="block text-xs text-gray-600 mb-1">Observación (opcional)</label>
                            <input type="text" name="descripcion" maxlength="500"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                placeholder="Observación del cambio de estado...">
                        </div>
                        <div class="pb-0.5">
                            <x-primary-button>Cambiar estado</x-primary-button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- Sección: Síntomas --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Síntomas</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Síntoma</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            @can('taller.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orden->sintomas as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs font-medium">{{ $s->sintoma?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-700">{{ $s->descripcion ?? '—' }}</td>
                                @can('taller.gestionar')
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('admin.taller.ordenes.sintomas.destroy', [$orden, $s]) }}"
                                            onsubmit="return confirm('¿Eliminar este síntoma?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->can('taller.gestionar') ? 3 : 2 }}"
                                    class="px-4 py-4 text-center text-xs text-gray-400">Sin síntomas registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @can('taller.gestionar')
                    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                        <form method="POST" action="{{ route('admin.taller.ordenes.sintomas.store', $orden) }}"
                            class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Síntoma</label>
                                <select name="sintoma_id"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— Seleccionar —</option>
                                    @foreach ($sintomasDisponibles as $sd)
                                        <option value="{{ $sd->id }}">{{ $sd->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-1 min-w-48">
                                <label class="block text-xs text-gray-600 mb-1">Descripción libre</label>
                                <input type="text" name="descripcion" maxlength="1000"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                    placeholder="O describa el síntoma...">
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Agregar</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Sección: Diagnósticos --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Diagnósticos</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Técnico</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Diagnóstico</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Solución propuesta</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Costo est.</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('taller.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orden->diagnosticos as $d)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs">{{ $d->tecnico?->nombre_publico ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-700 max-w-xs">{{ $d->diagnostico }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600 max-w-xs">{{ $d->solucion_propuesta ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">
                                    {{ $d->precio_estimado ? number_format($d->precio_estimado, 2) : '—' }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">
                                        {{ \App\Models\TallerOrdenDiagnostico::ESTADOS[$d->estado] ?? $d->estado }}
                                    </span>
                                </td>
                                @can('taller.gestionar')
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('admin.taller.ordenes.diagnosticos.destroy', [$orden, $d]) }}"
                                            onsubmit="return confirm('¿Eliminar este diagnóstico?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->can('taller.gestionar') ? 6 : 5 }}"
                                    class="px-4 py-4 text-center text-xs text-gray-400">Sin diagnósticos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @can('taller.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Agregar diagnóstico</p>
                        <form method="POST" action="{{ route('admin.taller.ordenes.diagnosticos.store', $orden) }}"
                            class="space-y-3">
                            @csrf
                            <div class="flex flex-wrap gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Técnico</label>
                                    <select name="tecnico_id"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">— Sin técnico —</option>
                                        @foreach ($tecnicos as $t)
                                            <option value="{{ $t->id }}">{{ $t->nombre_publico }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Costo estimado</label>
                                    <input type="number" name="precio_estimado" step="0.01" min="0"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-28"
                                        placeholder="0.00">
                                </div>
                                <div class="flex items-end gap-2 pb-1">
                                    <input type="hidden" name="requiere_aprobacion" value="0">
                                    <input type="checkbox" id="requiere_aprobacion" name="requiere_aprobacion" value="1"
                                        checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <label for="requiere_aprobacion" class="text-xs text-gray-700">Requiere aprobación</label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Diagnóstico *</label>
                                <textarea name="diagnostico" required rows="2"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    placeholder="Descripción del diagnóstico..."></textarea>
                            </div>
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-600 mb-1">Causa</label>
                                    <input type="text" name="causa" maxlength="500"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                        placeholder="Causa identificada...">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-600 mb-1">Solución propuesta</label>
                                    <input type="text" name="solucion_propuesta" maxlength="500"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                        placeholder="Solución propuesta...">
                                </div>
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Registrar diagnóstico</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Sección: Servicios --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Servicios</h3>
                    <span class="text-xs text-gray-500 font-mono">
                        Total: {{ number_format($orden->servicios->whereNotIn('estado', ['anulado'])->sum('total'), 2) }}
                    </span>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Técnico</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Cant.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Precio unit.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('taller.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orden->servicios as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs">
                                    <div class="font-medium">{{ $s->descripcion }}</div>
                                    @if ($s->servicio)
                                        <div class="text-gray-400">{{ $s->servicio->nombre }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $s->tecnico?->nombre_publico ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($s->cantidad, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($s->precio_unitario, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs font-semibold">{{ number_format($s->total, 2) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">
                                        {{ \App\Models\TallerOrdenServicio::ESTADOS[$s->estado] ?? $s->estado }}
                                    </span>
                                </td>
                                @can('taller.gestionar')
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('admin.taller.ordenes.servicios.destroy', [$orden, $s]) }}"
                                            onsubmit="return confirm('¿Quitar este servicio?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->can('taller.gestionar') ? 7 : 6 }}"
                                    class="px-4 py-4 text-center text-xs text-gray-400">Sin servicios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @can('taller.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Agregar servicio</p>
                        <form method="POST" action="{{ route('admin.taller.ordenes.servicios.store', $orden) }}"
                            class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs text-gray-600 mb-1">Descripción *</label>
                                <input type="text" name="descripcion" required maxlength="500"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                    placeholder="Descripción del servicio...">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Técnico</label>
                                <select name="tecnico_id"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— Sin técnico —</option>
                                    @foreach ($tecnicos as $t)
                                        <option value="{{ $t->id }}">{{ $t->nombre_publico }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Cantidad *</label>
                                <input type="number" name="cantidad" required step="0.01" min="0.01" value="1"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-20">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Precio unit. *</label>
                                <input type="number" name="precio_unitario" required step="0.01" min="0" value="0"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-28">
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Agregar</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Sección: Mano de obra --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Mano de obra</h3>
                    <span class="text-xs text-gray-500 font-mono">
                        Total facturable: {{ number_format($orden->manoObra->where('facturable', true)->sum('precio_total'), 2) }}
                    </span>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Técnico</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Horas</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Precio/h</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Facturable</th>
                            @can('taller.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orden->manoObra as $m)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $m->fecha?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $m->tecnico?->nombre_publico ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-700">{{ $m->descripcion }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($m->horas, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($m->precio_hora, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs font-semibold">{{ number_format($m->precio_total, 2) }}</td>
                                <td class="px-4 py-2 text-center text-xs">
                                    {{ $m->facturable ? 'Sí' : 'No' }}
                                </td>
                                @can('taller.gestionar')
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('admin.taller.ordenes.mano-obra.destroy', [$orden, $m]) }}"
                                            onsubmit="return confirm('¿Eliminar este registro de mano de obra?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->can('taller.gestionar') ? 8 : 7 }}"
                                    class="px-4 py-4 text-center text-xs text-gray-400">Sin mano de obra registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @can('taller.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Registrar mano de obra</p>
                        <form method="POST" action="{{ route('admin.taller.ordenes.mano-obra.store', $orden) }}"
                            class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Técnico</label>
                                <select name="tecnico_id"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— Sin técnico —</option>
                                    @foreach ($tecnicos as $t)
                                        <option value="{{ $t->id }}">{{ $t->nombre_publico }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Fecha</label>
                                <input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs text-gray-600 mb-1">Descripción *</label>
                                <input type="text" name="descripcion" required maxlength="500"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                    placeholder="Trabajo realizado...">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Horas *</label>
                                <input type="number" name="horas" required step="0.25" min="0" value="1"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-20">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Precio/hora *</label>
                                <input type="number" name="precio_hora" required step="0.01" min="0" value="0"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-24">
                            </div>
                            <div class="flex items-end gap-2 pb-1">
                                <input type="hidden" name="facturable" value="0">
                                <input type="checkbox" id="facturable" name="facturable" value="1"
                                    checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <label for="facturable" class="text-xs text-gray-700">Facturable</label>
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Registrar</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Sección: Repuestos --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Repuestos</h3>
                    <span class="text-xs text-gray-500 font-mono">
                        Total: {{ number_format($orden->repuestos->whereNotIn('estado', ['anulado', 'devuelto'])->sum('total'), 2) }}
                    </span>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Item (ID)</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Cant. sol.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Precio unit.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('taller.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orden->repuestos as $r)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono text-xs">{{ $r->item_id }}</td>
                                <td class="px-4 py-2 text-xs text-gray-700">{{ $r->descripcion ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($r->cantidad_solicitada, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($r->precio_unitario, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs font-semibold">{{ number_format($r->total, 2) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">
                                        {{ \App\Models\TallerOrdenRepuesto::ESTADOS[$r->estado] ?? $r->estado }}
                                    </span>
                                </td>
                                @can('taller.gestionar')
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('admin.taller.ordenes.repuestos.destroy', [$orden, $r]) }}"
                                            onsubmit="return confirm('¿Quitar este repuesto?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->can('taller.gestionar') ? 7 : 6 }}"
                                    class="px-4 py-4 text-center text-xs text-gray-400">Sin repuestos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @can('taller.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Agregar repuesto</p>
                        <form method="POST" action="{{ route('admin.taller.ordenes.repuestos.store', $orden) }}"
                            class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">ID del item *</label>
                                <input type="number" name="item_id" required min="1"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-24"
                                    placeholder="ID">
                            </div>
                            <div class="flex-1 min-w-40">
                                <label class="block text-xs text-gray-600 mb-1">Descripción</label>
                                <input type="text" name="descripcion" maxlength="500"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                    placeholder="Descripción del repuesto...">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Cant. solicitada *</label>
                                <input type="number" name="cantidad_solicitada" required step="0.01" min="0.01" value="1"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-24">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Precio unit. *</label>
                                <input type="number" name="precio_unitario" required step="0.01" min="0" value="0"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-28">
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Agregar</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Sección: Historial --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Historial de cambios</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Usuario</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Cambio de estado</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orden->historial as $h)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs text-gray-600 whitespace-nowrap">
                                    {{ $h->created_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $h->created_by ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if ($h->estado_anterior)
                                        <span class="text-gray-500">{{ \App\Models\TallerOrden::ESTADOS[$h->estado_anterior] ?? $h->estado_anterior }}</span>
                                        <span class="text-gray-400 mx-1">→</span>
                                    @endif
                                    @if ($h->estado_nuevo)
                                        <span class="font-medium text-gray-700">{{ \App\Models\TallerOrden::ESTADOS[$h->estado_nuevo] ?? $h->estado_nuevo }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $h->descripcion ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-xs text-gray-400">Sin historial.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Totales finales --}}
            <div class="flex justify-end">
                <div class="bg-white shadow-sm sm:rounded-lg p-4 w-64 text-sm space-y-1">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span class="font-mono">{{ number_format($orden->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Impuesto</span>
                        <span class="font-mono">{{ number_format($orden->impuesto, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-800 border-t pt-1">
                        <span>Total</span>
                        <span class="font-mono">{{ number_format($orden->total, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-500 text-xs">
                        <span>Saldo</span>
                        <span class="font-mono">{{ number_format($orden->saldo, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Sección: Control de calidad --}}
            @php
                $estadosCerrados = ['cerrada', 'cancelada', 'facturada'];
            @endphp
            @if (! in_array($orden->estado, $estadosCerrados))
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700">Control de calidad</h3>
                    </div>
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Técnico</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Resultado</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Observación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($orden->controlCalidad as $cc)
                                @php
                                    $ccBadge = match($cc->resultado) {
                                        'aprobado'            => 'bg-green-100 text-green-700',
                                        'rechazado'           => 'bg-red-100 text-red-700',
                                        'requiere_correccion' => 'bg-orange-100 text-orange-700',
                                        default               => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-xs text-gray-600 whitespace-nowrap">
                                        {{ $cc->fecha?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-xs">{{ $cc->tecnico?->nombre_publico ?? '—' }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $ccBadge }}">
                                            {{ \App\Models\TallerControlCalidad::RESULTADOS[$cc->resultado] ?? $cc->resultado }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-600">{{ $cc->observacion ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-xs text-gray-400">Sin registros de control de calidad.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @can('taller.gestionar')
                        <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                            <p class="text-xs font-semibold text-gray-600 mb-3">Agregar control de calidad</p>
                            <form method="POST" action="{{ route('admin.taller.ordenes.control-calidad.store', $orden) }}"
                                class="flex flex-wrap gap-3 items-end">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Técnico</label>
                                    <select name="tecnico_id"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">— Sin técnico —</option>
                                        @foreach ($tecnicos as $t)
                                            <option value="{{ $t->id }}">{{ $t->nombre_publico }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Resultado *</label>
                                    <select name="resultado" required
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        @foreach (\App\Models\TallerControlCalidad::RESULTADOS as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1 min-w-48">
                                    <label class="block text-xs text-gray-600 mb-1">Observación</label>
                                    <input type="text" name="observacion" maxlength="500"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                        placeholder="Observación del control de calidad...">
                                </div>
                                <div class="pb-0.5">
                                    <x-primary-button>Registrar</x-primary-button>
                                </div>
                            </form>
                        </div>
                    @endcan
                </div>
            @endif

            {{-- Sección: Acta de Entrega --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Acta de entrega</h3>
                </div>
                @if ($orden->entrega)
                    <div class="p-4 text-sm space-y-3">
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Fecha de entrega</p>
                                <p class="mt-0.5">{{ $orden->entrega->fecha_entrega?->format('d/m/Y H:i') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Entregado a (ID contacto)</p>
                                <p class="mt-0.5">{{ $orden->entrega->entregado_a_id ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Documento recibido</p>
                                <p class="mt-0.5">{{ $orden->entrega->documento_recibido ?? '—' }}</p>
                            </div>
                        </div>
                        @if ($orden->entrega->observacion)
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Observación</p>
                                <p class="mt-0.5 text-gray-700">{{ $orden->entrega->observacion }}</p>
                            </div>
                        @endif
                    </div>
                @elseif (in_array($orden->estado, ['lista_entrega', 'control_calidad']))
                    @can('taller.gestionar')
                        <div class="px-4 py-4 bg-gray-50">
                            <p class="text-xs font-semibold text-gray-600 mb-3">Registrar entrega</p>
                            <form method="POST" action="{{ route('admin.taller.ordenes.entrega.store', $orden) }}"
                                class="flex flex-wrap gap-3 items-end">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">ID contacto que recibe</label>
                                    <input type="number" name="entregado_a_id" min="1"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-28"
                                        placeholder="ID">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Documento recibido</label>
                                    <input type="text" name="documento_recibido" maxlength="100"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-40"
                                        placeholder="N° de documento...">
                                </div>
                                <div class="flex-1 min-w-40">
                                    <label class="block text-xs text-gray-600 mb-1">Observación</label>
                                    <input type="text" name="observacion" maxlength="500"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                        placeholder="Observación de la entrega...">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Fecha de entrega</label>
                                    <input type="datetime-local" name="fecha_entrega"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>
                                <div class="pb-0.5">
                                    <x-primary-button>Registrar entrega</x-primary-button>
                                </div>
                            </form>
                        </div>
                    @endcan
                @else
                    <div class="px-4 py-4 text-xs text-gray-400">
                        Sin acta de entrega. El formulario aparece cuando el estado es "Lista para entrega" o "Control de calidad".
                    </div>
                @endif
            </div>

            {{-- Sección: Facturación --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Facturación</h3>
                </div>
                @if ($orden->facturacion)
                    @php
                        $cxcBadge = match($orden->facturacion->estado_cxc) {
                            'pendiente' => 'bg-yellow-100 text-yellow-700',
                            'emitido'   => 'bg-blue-100 text-blue-700',
                            'cobrado'   => 'bg-green-100 text-green-700',
                            'anulado'   => 'bg-red-100 text-red-700',
                            default     => 'bg-gray-100 text-gray-600',
                        };
                        $felBadge = match($orden->facturacion->estado_fel) {
                            'pendiente' => 'bg-yellow-100 text-yellow-700',
                            'emitido'   => 'bg-blue-100 text-blue-700',
                            'aceptado'  => 'bg-green-100 text-green-700',
                            'rechazado' => 'bg-red-100 text-red-700',
                            'anulado'   => 'bg-red-100 text-red-700',
                            default     => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <div class="p-4 text-sm space-y-3">
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Tipo</p>
                                <p class="mt-0.5">{{ \App\Models\TallerFacturacion::TIPOS[$orden->facturacion->tipo_facturacion] ?? $orden->facturacion->tipo_facturacion }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Fecha</p>
                                <p class="mt-0.5">{{ $orden->facturacion->fecha?->format('d/m/Y') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Estado CXC</p>
                                <span class="inline-block mt-0.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $cxcBadge }}">
                                    {{ $orden->facturacion->estado_cxc ?? '—' }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Estado FEL</p>
                                <span class="inline-block mt-0.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $felBadge }}">
                                    {{ $orden->facturacion->estado_fel ?? '—' }}
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Total</p>
                                <p class="mt-0.5 font-mono font-semibold">{{ number_format($orden->facturacion->total, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Pagado</p>
                                <p class="mt-0.5 font-mono text-green-700">{{ number_format($orden->facturacion->pagado, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Saldo</p>
                                <p class="mt-0.5 font-mono {{ $orden->facturacion->saldo > 0 ? 'text-red-600' : 'text-gray-600' }}">
                                    {{ number_format($orden->facturacion->saldo, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                @elseif (in_array($orden->estado, ['lista_entrega', 'entregada']))
                    @can('taller.gestionar')
                        <div class="px-4 py-4 bg-gray-50">
                            <p class="text-xs font-semibold text-gray-600 mb-3">Generar facturación</p>
                            <form method="POST" action="{{ route('admin.taller.ordenes.facturacion.store', $orden) }}"
                                class="flex flex-wrap gap-3 items-end">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Tipo de facturación *</label>
                                    <select name="tipo_facturacion" required
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        @foreach (\App\Models\TallerFacturacion::TIPOS as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1 min-w-48">
                                    <label class="block text-xs text-gray-600 mb-1">Observación</label>
                                    <input type="text" name="observacion" maxlength="500"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                        placeholder="Observación...">
                                </div>
                                <div class="pb-0.5">
                                    <x-primary-button>Generar facturación</x-primary-button>
                                </div>
                            </form>
                        </div>
                    @endcan
                @else
                    <div class="px-4 py-4 text-xs text-gray-400">
                        Sin registro de facturación. El formulario aparece cuando el estado es "Lista para entrega" o "Entregada".
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
