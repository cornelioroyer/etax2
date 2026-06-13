<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Períodos contables</h2>
        </div>
    </x-slot>

    @php
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                  7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        $puedeEditar = Auth::user()->is_admin || Auth::user()->can('contabilidad.editar');
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="GET" class="flex items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                <label for="anio" class="text-sm font-medium text-gray-700">Año</label>
                <select id="anio" name="anio" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($anios as $a)
                        <option value="{{ $a }}" @selected($a === $anio)>{{ $a }}</option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500">Los asientos solo se pueden postear en períodos abiertos. Un período que nunca se ha usado se crea abierto automáticamente.</p>
            </form>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Mes</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Asientos posteados</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Borradores</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Cierre</th>
                            @if ($puedeEditar)
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($meses as $mes => $nombre)
                            @php
                                $periodo = $periodos->get($mes);
                                $posteados = (int) ($asientosPorMes->get($mes)[\App\Models\Asiento::ESTADO_POSTEADO] ?? 0);
                                $borradores = (int) ($asientosPorMes->get($mes)[\App\Models\Asiento::ESTADO_BORRADOR] ?? 0);
                                $abierto = ! $periodo || $periodo->estaAbierto();
                                $confirmarForzar = old('mes_confirmar') == $mes;
                                $cierre = $periodo ? $cierres->get($periodo->id) : null;
                                $inicio = $periodo?->fecha_inicio ?? \Carbon\Carbon::create($anio, $mes, 1);
                                $fin = $periodo?->fecha_fin ?? $inicio->copy()->endOfMonth();
                            @endphp
                            <tr>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900">{{ $nombre }} {{ $anio }}</span>
                                    <span class="block text-xs text-gray-400">{{ $inicio->format('d/m/Y') }} – {{ $fin->format('d/m/Y') }}</span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-700">{{ $posteados ?: '—' }}</td>
                                <td class="px-6 py-4 text-center text-sm {{ $borradores ? 'text-amber-600 font-medium' : 'text-gray-700' }}">{{ $borradores ?: '—' }}</td>
                                <td class="px-6 py-4">
                                    @if ($abierto)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">ABIERTO</span>
                                        @unless ($periodo)
                                            <span class="ms-1 text-xs text-gray-400">(sin uso)</span>
                                        @endunless
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">CERRADO</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if ($periodo && ! $abierto)
                                        @fechaHora($periodo->fecha_cierre)
                                        @if ($periodo->cerradoPor)
                                            <span class="block text-xs">{{ $periodo->cerradoPor->name }}</span>
                                        @endif
                                        @if ($cierre?->observacion)
                                            <span class="block text-xs italic text-gray-400">{{ $cierre->observacion }}</span>
                                        @endif
                                    @elseif ($cierre && $cierre->estado === \App\Models\CierreContable::ESTADO_REABIERTO)
                                        <span class="text-xs text-amber-600">Reabierto</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                @if ($puedeEditar)
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        @if ($abierto)
                                            <form method="POST" action="{{ route('admin.periodos.cerrar') }}" class="inline-flex items-center justify-end gap-2"
                                                  @unless ($confirmarForzar) onsubmit="return confirm('¿Cerrar {{ $nombre }} {{ $anio }}? Ya no se podrán postear asientos en ese mes.')" @endunless>
                                                @csrf
                                                <input type="hidden" name="anio" value="{{ $anio }}">
                                                <input type="hidden" name="mes" value="{{ $mes }}">
                                                @if ($confirmarForzar)
                                                    <label class="inline-flex items-center gap-1 text-xs text-amber-700">
                                                        <input type="checkbox" name="forzar" value="1" class="rounded border-gray-300">
                                                        cerrar de todos modos
                                                    </label>
                                                @endif
                                                <input name="observacion" maxlength="500" placeholder="Observación (opcional)"
                                                       class="w-44 rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <button class="text-red-600 hover:text-red-900">Cerrar</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.periodos.reabrir', $periodo) }}" class="inline-flex items-center justify-end gap-2">
                                                @csrf
                                                <input name="motivo" required minlength="5" maxlength="500" placeholder="Motivo de reapertura"
                                                       class="w-48 rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <button class="text-indigo-600 hover:text-indigo-900">Reabrir</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
