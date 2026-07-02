<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Planilla {{ $planilla->numero }}</h2>
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

            <div class="text-sm">
                <a href="{{ route('admin.nomina.planillas.index') }}" class="text-indigo-600 hover:underline">← Volver a planillas</a>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm sm:grid-cols-3">
                        <div><span class="text-gray-400">Período:</span> {{ $planilla->periodo?->etiqueta() }}</div>
                        <div><span class="text-gray-400">Fecha contable:</span> {{ $planilla->fecha->format('d/m/Y') }}</div>
                        <div>
                            <span class="text-gray-400">Estado:</span>
                            <span class="font-semibold">{{ $planilla->estado }}</span>
                        </div>
                        <div><span class="text-gray-400">Ingresos:</span> B/. {{ number_format((float) $planilla->total_ingresos, 2) }}</div>
                        <div><span class="text-gray-400">Deducciones:</span> B/. {{ number_format((float) $planilla->total_deducciones, 2) }}</div>
                        <div><span class="text-gray-400">Neto a pagar:</span> <b>B/. {{ number_format((float) $planilla->total_neto, 2) }}</b></div>
                        <div><span class="text-gray-400">Costo patronal:</span> B/. {{ number_format((float) $planilla->total_patronal, 2) }}</div>
                        @if ($planilla->asiento)
                            <div>
                                <span class="text-gray-400">Asiento:</span>
                                <a href="{{ route('admin.asientos.show', $planilla->asiento) }}" class="text-indigo-600 hover:underline">{{ $planilla->asiento->numero }}</a>
                            </div>
                        @endif
                    </div>

                    @can('nomina.gestionar')
                    <div class="flex flex-wrap gap-2">
                        @if ($planilla->esBorrador() || $planilla->esProcesada())
                            <form method="POST" action="{{ route('admin.nomina.planillas.recalcular', $planilla) }}">
                                @csrf
                                <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700">Recalcular</button>
                            </form>
                        @endif
                        @if ($planilla->esProcesada())
                            <form method="POST" action="{{ route('admin.nomina.planillas.contabilizar', $planilla) }}"
                                  onsubmit="return confirm('¿Contabilizar la planilla? Se posteará el asiento de diario.')">
                                @csrf
                                <button class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color:#0d2d5e">Contabilizar</button>
                            </form>
                        @endif
                        @unless ($planilla->estaAnulada())
                            <form method="POST" action="{{ route('admin.nomina.planillas.anular', $planilla) }}"
                                  onsubmit="return confirm('¿Anular la planilla? {{ $planilla->asiento_id ? 'El asiento será reversado.' : '' }}')">
                                @csrf
                                <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm text-red-600">Anular</button>
                            </form>
                        @endunless
                    </div>
                    @endcan
                </div>
            </div>

            @foreach ($movimientos as $empleadoId => $movs)
                @php
                    $empleado = $movs->first()->empleado;
                    $ingresos = $movs->filter(fn ($m) => $m->concepto->tipo === 'INGRESO')->sum(fn ($m) => (float) $m->monto);
                    $deducciones = $movs->filter(fn ($m) => $m->concepto->tipo === 'DEDUCCION')->sum(fn ($m) => (float) $m->monto);
                @endphp
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between bg-gray-50 px-4 py-2">
                        <div class="text-sm font-semibold">{{ $empleado->codigo }} — {{ trim($empleado->nombre.' '.$empleado->apellido) }}</div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="font-mono">Neto: <b>B/. {{ number_format($ingresos - $deducciones, 2) }}</b></span>
                            <a href="{{ route('admin.nomina.planillas.recibo', [$planilla, $empleadoId]) }}" target="_blank"
                               class="text-xs text-indigo-600 hover:underline">Recibo →</a>
                        </div>
                    </div>
                    <table class="min-w-full text-sm divide-y divide-gray-100">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($movs as $m)
                                <tr>
                                    <td class="w-20 px-4 py-1.5 font-mono text-xs text-gray-500">{{ $m->concepto->codigo }}</td>
                                    <td class="px-4 py-1.5">
                                        {{ $m->concepto->descripcion }}
                                        @if ($m->concepto->tipo === 'PATRONAL')<span class="ml-1 text-[10px] text-gray-400">(patrono)</span>@endif
                                        @if ($m->descripcion)<span class="ml-1 text-xs text-gray-400">{{ $m->descripcion }}</span>@endif
                                    </td>
                                    <td class="w-24 px-4 py-1.5 text-right font-mono text-xs text-gray-400">
                                        {{ $m->cantidad ? number_format((float) $m->cantidad, 2) : '' }}
                                    </td>
                                    <td class="w-32 px-4 py-1.5 text-right font-mono {{ $m->concepto->tipo === 'DEDUCCION' ? 'text-red-600' : ($m->concepto->tipo === 'PATRONAL' ? 'text-gray-400' : '') }}">
                                        {{ $m->concepto->tipo === 'DEDUCCION' ? '−' : '' }}{{ number_format((float) $m->monto, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
