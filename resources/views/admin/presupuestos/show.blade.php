<x-app-layout>
    @php
        $badge = match($presupuesto->estado) {
            \App\Models\BudgetPresupuesto::ESTADO_APROBADO => 'bg-green-100 text-green-700',
            \App\Models\BudgetPresupuesto::ESTADO_CERRADO  => 'bg-gray-200 text-gray-600',
            default => 'bg-yellow-100 text-yellow-700',
        };
        $totalGeneral = $presupuesto->detalle->sum('monto_total');
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="font-bold text-2xl text-gray-800">{{ $presupuesto->nombre }}</h2>
                    <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">
                        {{ \App\Models\BudgetPresupuesto::ESTADOS[$presupuesto->estado] ?? $presupuesto->estado }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-gray-500">
                    <a href="{{ route('admin.presupuestos.index') }}" class="hover:underline">Presupuestos</a>
                    &rsaquo; {{ $presupuesto->escenario?->nombre }} &rsaquo; {{ $presupuesto->anio }}
                </p>
            </div>
            <div class="flex gap-2 text-sm">
                <a href="{{ route('admin.presupuestos.index') }}" class="text-gray-500 hover:text-gray-900">← Presupuestos</a>
                @can('presupuestos.gestionar')
                    <a href="{{ route('admin.presupuestos.edit', $presupuesto) }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">Editar</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Cambiar estado --}}
            @can('presupuestos.gestionar')
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Cambiar estado</h3>
                    <form method="POST" action="{{ route('admin.presupuestos.cambiar-estado', $presupuesto) }}"
                        class="flex flex-wrap gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Nuevo estado</label>
                            <select name="estado" required
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\BudgetPresupuesto::ESTADOS as $val => $label)
                                    <option value="{{ $val }}" {{ $presupuesto->estado === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pb-0.5"><x-primary-button>Cambiar estado</x-primary-button></div>
                    </form>
                </div>
            @endcan

            {{-- Detalle: cuenta x 12 meses --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Detalle por cuenta</h3>
                    <span class="text-xs text-gray-500 font-mono">Total: {{ number_format($totalGeneral, 2) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold uppercase text-gray-500 sticky left-0 bg-gray-50">Cuenta</th>
                                @foreach ($meses as $col => $label)
                                    <th class="px-2 py-2 text-right font-semibold uppercase text-gray-500">{{ $label }}</th>
                                @endforeach
                                <th class="px-3 py-2 text-right font-semibold uppercase text-gray-500">Total</th>
                                @can('presupuestos.gestionar')<th></th>@endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($presupuesto->detalle as $d)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 sticky left-0 bg-white">
                                        <span class="font-mono font-semibold">{{ $d->cuenta?->codigo }}</span>
                                        <span class="text-gray-600">{{ $d->cuenta?->nombre }}</span>
                                    </td>
                                    @foreach (array_keys($meses) as $col)
                                        <td class="px-2 py-2 text-right font-mono">{{ number_format($d->$col, 2) }}</td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format($d->monto_total, 2) }}</td>
                                    @can('presupuestos.gestionar')
                                        <td class="px-3 py-2 text-right">
                                            <form method="POST" action="{{ route('admin.presupuestos.detalle.destroy', [$presupuesto, $d]) }}"
                                                onsubmit="return confirm('¿Quitar esta cuenta del presupuesto?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline">Quitar</button>
                                            </form>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 14 + (Auth::user()->can('presupuestos.gestionar') ? 1 : 0) }}"
                                        class="px-4 py-6 text-center text-gray-400">Sin cuentas en este presupuesto.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($presupuesto->detalle->isNotEmpty())
                            <tfoot class="bg-gray-50 font-semibold">
                                <tr>
                                    <td class="px-3 py-2 sticky left-0 bg-gray-50 text-gray-700">Totales</td>
                                    @foreach (array_keys($meses) as $col)
                                        <td class="px-2 py-2 text-right font-mono">{{ number_format($totalesMes[$col], 2) }}</td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format($totalGeneral, 2) }}</td>
                                    @can('presupuestos.gestionar')<td></td>@endcan
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Agregar cuenta --}}
                @can('presupuestos.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Agregar cuenta</p>
                        @if ($cuentas->isEmpty())
                            <p class="text-xs text-gray-400">No hay cuentas de movimiento activas en esta compañía.</p>
                        @else
                            <form method="POST" action="{{ route('admin.presupuestos.detalle.store', $presupuesto) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Cuenta contable *</label>
                                    <select name="cuenta_id" required
                                        class="w-full sm:w-2/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">— Seleccione cuenta —</option>
                                        @foreach ($cuentas as $c)
                                            <option value="{{ $c->id }}">{{ $c->codigo }} · {{ $c->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-12 gap-2">
                                    @foreach ($meses as $col => $label)
                                        <div>
                                            <label class="block text-[11px] text-gray-500 mb-0.5">{{ $label }}</label>
                                            <input type="number" name="{{ $col }}" step="0.01" value="0"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-right">
                                        </div>
                                    @endforeach
                                </div>
                                <div><x-primary-button>Agregar cuenta</x-primary-button></div>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>

            {{-- Zona peligro --}}
            @can('presupuestos.gestionar')
                @if ($presupuesto->estado === \App\Models\BudgetPresupuesto::ESTADO_BORRADOR)
                    <div class="bg-white shadow-sm sm:rounded-lg p-4 border border-red-200">
                        <h3 class="text-sm font-semibold text-red-700 mb-2">Zona de peligro</h3>
                        <form method="POST" action="{{ route('admin.presupuestos.destroy', $presupuesto) }}"
                            onsubmit="return confirm('¿Eliminar este presupuesto? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">
                                Eliminar presupuesto
                            </button>
                        </form>
                    </div>
                @endif
            @endcan

        </div>
    </div>
</x-app-layout>
