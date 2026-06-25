<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Asiento {{ $asiento->numero }}
                @if ($asiento->estado === 'POSTEADO')
                    <span class="ml-2 inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 align-middle">Posteado</span>
                @elseif ($asiento->estado === 'BORRADOR')
                    <span class="ml-2 inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 align-middle">Borrador</span>
                @else
                    <span class="ml-2 inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 align-middle">Anulado</span>
                @endif
            </h2>
            <div class="flex items-center gap-2">
                @can('contabilidad.crear')
                    <a href="{{ route('admin.asientos.copiar', $asiento) }}"
                       class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Duplicar</a>
                    <a href="{{ route('admin.asientos-recurrentes.desde-asiento', $asiento) }}"
                       class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Hacer recurrente</a>
                @endcan
                @if ($asiento->esBorrador())
                    @can('contabilidad.editar')
                        <form method="POST" action="{{ route('admin.asientos.postear', $asiento) }}">
                            @csrf
                            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Postear</button>
                        </form>
                        <a href="{{ route('admin.asientos.edit', $asiento) }}"
                           class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Editar</a>
                    @endcan
                    @can('contabilidad.eliminar')
                        <form method="POST" action="{{ route('admin.asientos.destroy', $asiento) }}"
                              onsubmit="return confirm('¿Eliminar el borrador {{ $asiento->numero }}?');">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Eliminar</button>
                        </form>
                    @endcan
                @elseif ($asiento->esPosteado())
                    @can('contabilidad.editar')
                        @if ($asiento->esManual())
                            <a href="{{ route('admin.asientos.edit', $asiento) }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Editar</a>
                        @elseif ($urlOrigen = $asiento->urlOrigen())
                            <a href="{{ $urlOrigen }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Editar en {{ $asiento->nombreModuloOrigen() }} →</a>
                        @endif
                        <form method="POST" action="{{ route('admin.asientos.anular', $asiento) }}"
                              onsubmit="return confirm('¿Anular el asiento {{ $asiento->numero }}? Esta acción revierte su efecto en los saldos.');">
                            @csrf
                            <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Anular</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
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

            {{-- Cabecera --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="font-medium text-gray-500">Fecha</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $asiento->fecha->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Período</dt>
                        <dd class="mt-0.5 text-gray-900">
                            {{ $asiento->periodo ? sprintf('%04d-%02d (%s)', $asiento->periodo->anio, $asiento->periodo->mes, ucfirst(strtolower($asiento->periodo->estado))) : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Referencia</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $asiento->referencia ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Posteado</dt>
                        <dd class="mt-0.5 text-gray-900">
                            @if ($asiento->fecha_posteo)
                                @fechaHora($asiento->fecha_posteo)
                                @if ($asiento->posteadoPor) por {{ $asiento->posteadoPor->name }} @endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="col-span-2 sm:col-span-4">
                        <dt class="font-medium text-gray-500">Descripción</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $asiento->descripcion ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Detalle --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 w-10">#</th>
                                <th class="px-4 py-3">Cuenta</th>
                                <th class="px-4 py-3 hidden md:table-cell">Descripción</th>
                                <th class="px-4 py-3 text-right">Débito</th>
                                <th class="px-4 py-3 text-right">Crédito</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($asiento->detalle as $linea)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500">{{ $linea->linea }}</td>
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-xs text-gray-500">{{ $linea->cuenta?->codigo }}</span>
                                        {{ $linea->cuenta?->nombre }}
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell text-gray-600">{{ $linea->descripcion }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ (float) $linea->debito > 0 ? 'B/. '.number_format((float) $linea->debito, 2) : '' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ (float) $linea->credito > 0 ? 'B/. '.number_format((float) $linea->credito, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 text-sm font-semibold">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right text-gray-600">Totales</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $asiento->total_debito, 2) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">B/. {{ number_format((float) $asiento->total_credito, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div>
                <a href="{{ route('admin.asientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
            </div>
        </div>
    </div>
</x-app-layout>
