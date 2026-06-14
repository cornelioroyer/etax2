<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-800">Cierre anual del ejercicio</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    Salda Ingresos, Costos y Gastos contra Superávit Acumulado en el período de ajuste.
                </p>
            </div>
            <form method="GET" action="{{ route('admin.cierre-anual.index') }}" class="flex items-end gap-2">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Año</label>
                    <select name="anio" onchange="this.form.submit()"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($anios as $a)
                            <option value="{{ $a }}" {{ $a == $anio ? 'selected' : '' }}>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
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

            {{-- Estado del cierre --}}
            @if ($asiento)
                <div class="rounded-md bg-gray-100 border border-gray-300 p-4 flex items-center justify-between gap-4">
                    <div class="text-sm text-gray-700">
                        <span class="font-semibold">Ejercicio {{ $anio }} cerrado.</span>
                        Asiento <span class="font-mono">{{ $asiento->numero }}</span>
                        posteado el {{ $asiento->fecha_posteo?->format('d/m/Y H:i') }} en el período de ajuste.
                    </div>
                    @can('contabilidad.editar')
                        <form method="POST" action="{{ route('admin.cierre-anual.reversar') }}"
                            onsubmit="return confirm('¿Reversar el cierre del ejercicio {{ $anio }}? El asiento se anulará y los saldos se revertirán.')">
                            @csrf
                            <input type="hidden" name="anio" value="{{ $anio }}">
                            <button type="submit"
                                class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">
                                Reversar cierre
                            </button>
                        </form>
                    @endcan
                </div>
            @elseif ($borradores > 0)
                <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4 text-sm text-yellow-800">
                    El año {{ $anio }} tiene <span class="font-semibold">{{ $borradores }}</span>
                    asiento(s) en borrador. El resultado mostrado solo considera asientos posteados.
                </div>
            @endif

            {{-- Resumen del resultado --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @php
                    $tarjetas = [
                        ['Ingresos', $preview['ingresos'], 'text-gray-800'],
                        ['Costos', $preview['costos'], 'text-gray-800'],
                        ['Gastos', $preview['gastos'], 'text-gray-800'],
                        [$preview['utilidad'] >= 0 ? 'Utilidad' : 'Pérdida', $preview['utilidad'],
                            $preview['utilidad'] >= 0 ? 'text-green-700' : 'text-red-700'],
                    ];
                @endphp
                @foreach ($tarjetas as [$rotulo, $valor, $color])
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500">{{ $rotulo }}</div>
                        <div class="mt-1 text-lg font-mono font-semibold {{ $color }}">{{ number_format($valor, 2) }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Previsualización del asiento de cierre --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">
                        {{ $asiento ? 'Asiento de cierre posteado' : 'Asiento de cierre (previsualización)' }}
                    </h3>
                    @can('contabilidad.editar')
                        @if (! $asiento)
                            <form method="POST" action="{{ route('admin.cierre-anual.cerrar') }}"
                                onsubmit="return confirm('¿Cerrar el ejercicio {{ $anio }}? Se posteará el asiento de cierre en el período de ajuste.')">
                                @csrf
                                <input type="hidden" name="anio" value="{{ $anio }}">
                                <button type="submit"
                                    @disabled(empty($preview['lineas']))
                                    class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                    Cerrar ejercicio {{ $anio }}
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold uppercase text-gray-500 text-xs">Cuenta</th>
                                <th class="px-4 py-2 text-right font-semibold uppercase text-gray-500 text-xs">Débito</th>
                                <th class="px-4 py-2 text-right font-semibold uppercase text-gray-500 text-xs">Crédito</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($preview['lineas'] as $l)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <span class="font-mono font-semibold">{{ $l['codigo'] }}</span>
                                        <span class="text-gray-600">{{ $l['nombre'] }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $l['debito'] ? number_format($l['debito'], 2) : '' }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $l['credito'] ? number_format($l['credito'], 2) : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-400">
                                        Sin movimientos de resultado posteados en {{ $anio }}.
                                    </td>
                                </tr>
                            @endforelse
                            @if (! empty($preview['lineas']))
                                <tr class="bg-indigo-50">
                                    <td class="px-4 py-2 font-semibold text-indigo-900">
                                        Superávit Acumulado —
                                        {{ $preview['utilidad'] >= 0 ? 'utilidad' : 'pérdida' }} del ejercicio
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $preview['cierre']['debito'] ? number_format($preview['cierre']['debito'], 2) : '' }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $preview['cierre']['credito'] ? number_format($preview['cierre']['credito'], 2) : '' }}</td>
                                </tr>
                            @endif
                        </tbody>
                        @if (! empty($preview['lineas']))
                            <tfoot class="bg-gray-50 font-semibold">
                                <tr>
                                    <td class="px-4 py-2 text-right text-gray-700">Totales</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ number_format($preview['total_debito'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ number_format($preview['total_credito'], 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
