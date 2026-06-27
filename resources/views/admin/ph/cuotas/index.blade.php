<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuotas</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.ph.edificios.index') }}" class="text-gray-500 hover:text-gray-900">Edificios</a>
                @can('ph.gestionar')
                    <a href="{{ route('admin.ph.cuotas.generar') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">Generar cuotas</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Filtros --}}
            <form method="GET" class="bg-white p-4 shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <x-buscador-contacto name="edificio_id" label="Edificio" :opciones="$edificios"
                            :selected="$edificioId" placeholder="Todos — buscar" empty-label="Todos" width="w-56" compact />
                    </div>
                    <div>
                        <x-input-label value="Tipo de cuota" />
                        <select name="tipo_cuota_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Todos</option>
                            @foreach ($tiposCuota as $tc)
                                <option value="{{ $tc->id }}" @selected($tipoCuotaId == $tc->id)>{{ $tc->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Período (YYYY-MM)" />
                        <x-text-input name="periodo" type="text" class="mt-1 block w-full" :value="$periodo" placeholder="2026-06" maxlength="7" />
                    </div>
                    <div>
                        <x-input-label value="Estado" />
                        <select name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option value="PENDIENTE" @selected($estado === 'PENDIENTE')>Pendiente</option>
                            <option value="PAGADO" @selected($estado === 'PAGADO')>Pagado</option>
                            <option value="VENCIDO" @selected($estado === 'VENCIDO')>Vencido</option>
                            <option value="ANULADO" @selected($estado === 'ANULADO')>Anulado</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <x-primary-button>Filtrar</x-primary-button>
                    <a href="{{ route('admin.ph.cuotas.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
                </div>
            </form>

            {{-- Tabla --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Período</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Edificio / Unidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Propietario</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Monto</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Pagado</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Saldo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Vencimiento</th>
                            @can('ph.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cuotas as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono">{{ $c->periodo }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-medium">{{ $c->unidad->edificio->nombre }}</span>
                                    <span class="text-gray-500"> / {{ $c->unidad->numero }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $c->unidad->propietario?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $c->tipoCuota->nombre }}</td>
                                <td class="px-4 py-2 text-right font-mono">B/. {{ number_format($c->monto, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-green-700">B/. {{ number_format($c->monto_pagado, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold {{ $c->saldoPendiente() > 0 ? 'text-orange-700' : 'text-gray-400' }}">
                                    B/. {{ number_format($c->saldoPendiente(), 2) }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @php
                                        $estadoClases = match($c->estado) {
                                            'PAGADO'   => 'bg-green-100 text-green-700',
                                            'VENCIDO'  => 'bg-red-100 text-red-700',
                                            'ANULADO'  => 'bg-gray-100 text-gray-400 line-through',
                                            default    => 'bg-yellow-100 text-yellow-700',
                                        };
                                    @endphp
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $estadoClases }}">
                                        {{ $c->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $c->fecha_vencimiento->format('d/m/Y') }}</td>
                                @can('ph.gestionar')
                                <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                    @if ($c->estado !== 'ANULADO' && $c->estado !== 'PAGADO')
                                        <a href="{{ route('admin.ph.pagos.create', ['cuota_id' => $c->id]) }}"
                                            class="text-xs text-green-700 hover:underline">Registrar pago</a>
                                        <form method="POST" action="{{ route('admin.ph.cuotas.anular', $c) }}" class="inline"
                                              onsubmit="return confirm('¿Anular esta cuota?')">
                                            @csrf @method('PATCH')
                                            <button class="text-xs text-red-600 hover:underline">Anular</button>
                                        </form>
                                    @endif
                                </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                                    Sin cuotas.
                                    @can('ph.gestionar')
                                        <a href="{{ route('admin.ph.cuotas.generar') }}" class="text-indigo-600 hover:underline">Generar cuotas</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($cuotas->isNotEmpty())
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-xs font-semibold text-gray-600">Totales (página)</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold">B/. {{ number_format($cuotas->sum('monto'), 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold text-green-700">B/. {{ number_format($cuotas->sum('monto_pagado'), 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold text-orange-700">
                                B/. {{ number_format($cuotas->sum(fn($c) => $c->saldoPendiente()), 2) }}
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            {{ $cuotas->links() }}
        </div>
    </div>
</x-app-layout>
