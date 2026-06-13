<x-app-layout>
    @php
        $badgeColor = match($presupuesto->estado) {
            'borrador'   => 'bg-gray-100 text-gray-600',
            'enviado'    => 'bg-blue-100 text-blue-700',
            'aprobado'   => 'bg-green-100 text-green-700',
            'rechazado'  => 'bg-red-100 text-red-700',
            'vencido'    => 'bg-orange-100 text-orange-700',
            'convertido' => 'bg-indigo-100 text-indigo-700',
            'anulado'    => 'bg-gray-100 text-gray-400',
            default      => 'bg-gray-100 text-gray-600',
        };
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="font-bold text-2xl text-gray-800 font-mono">{{ $presupuesto->numero }}</h2>
                    <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $badgeColor }}">
                        {{ \App\Models\TallerPresupuesto::ESTADOS[$presupuesto->estado] ?? $presupuesto->estado }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-gray-500">
                    <a href="{{ route('admin.taller.presupuestos.index') }}" class="hover:underline">Presupuestos</a>
                    &rsaquo; {{ $presupuesto->taller?->nombre }}
                </p>
            </div>
            <div class="flex gap-2 text-sm">
                <a href="{{ route('admin.taller.presupuestos.index') }}" class="text-gray-500 hover:text-gray-900">← Presupuestos</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.presupuestos.edit', $presupuesto) }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">Editar</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash --}}
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
                        <p class="text-xs font-medium uppercase text-gray-500">Taller</p>
                        <p class="mt-0.5 font-medium">{{ $presupuesto->taller?->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Cliente</p>
                        <p class="mt-0.5 font-medium">{{ $presupuesto->cliente?->nombre ?? ($presupuesto->cliente_id ? 'ID '.$presupuesto->cliente_id : '—') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Equipo</p>
                        <p class="mt-0.5">{{ $presupuesto->equipo?->nombre ?? ($presupuesto->equipo_id ? 'Equipo #'.$presupuesto->equipo_id : '—') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Fecha</p>
                        <p class="mt-0.5">{{ $presupuesto->fecha?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Vencimiento</p>
                        <p class="mt-0.5">{{ $presupuesto->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Total</p>
                        <p class="mt-0.5 font-mono font-semibold">{{ number_format($presupuesto->total, 2) }}</p>
                    </div>
                </div>
                @if ($presupuesto->descripcion)
                    <div class="mt-4 pt-4 border-t border-gray-100 text-sm">
                        <p class="text-xs font-medium uppercase text-gray-500">Descripción</p>
                        <p class="mt-0.5 text-gray-700">{{ $presupuesto->descripcion }}</p>
                    </div>
                @endif
            </div>

            {{-- Cambiar estado --}}
            @can('taller.gestionar')
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Cambiar estado</h3>
                    <form method="POST" action="{{ route('admin.taller.presupuestos.cambiar-estado', $presupuesto) }}"
                        class="flex flex-wrap gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Nuevo estado</label>
                            <select name="estado" required
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerPresupuesto::ESTADOS as $val => $label)
                                    <option value="{{ $val }}" {{ $presupuesto->estado === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pb-0.5">
                            <x-primary-button>Cambiar estado</x-primary-button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- Líneas del presupuesto --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Líneas del presupuesto</h3>
                    <span class="text-xs text-gray-500 font-mono">
                        Total: {{ number_format($presupuesto->detalles->sum('total'), 2) }}
                    </span>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Cant.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Precio unit.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Dcto.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                            @can('taller.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($presupuesto->detalles as $d)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs capitalize">{{ str_replace('_', ' ', $d->tipo_linea) }}</td>
                                <td class="px-4 py-2 text-xs text-gray-700">{{ $d->descripcion }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($d->cantidad, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($d->precio_unitario, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($d->descuento, 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs font-semibold">{{ number_format($d->total, 2) }}</td>
                                @can('taller.gestionar')
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('admin.taller.presupuestos.detalles.destroy', [$presupuesto, $d]) }}"
                                            onsubmit="return confirm('¿Eliminar esta línea?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->can('taller.gestionar') ? 7 : 6 }}"
                                    class="px-4 py-4 text-center text-xs text-gray-400">Sin líneas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @can('taller.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Agregar línea</p>
                        <form method="POST" action="{{ route('admin.taller.presupuestos.detalles.store', $presupuesto) }}"
                            class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Tipo *</label>
                                <select name="tipo_linea" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="servicio">Servicio</option>
                                    <option value="repuesto">Repuesto</option>
                                    <option value="mano_obra">Mano de obra</option>
                                    <option value="externo">Externo</option>
                                    <option value="descuento">Descuento</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                            <div class="flex-1 min-w-48">
                                <label class="block text-xs text-gray-600 mb-1">Descripción *</label>
                                <input type="text" name="descripcion" required maxlength="1000"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full"
                                    placeholder="Descripción de la línea...">
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
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Descuento</label>
                                <input type="number" name="descuento" step="0.01" min="0" value="0"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-24">
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Agregar</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Totales --}}
            <div class="flex justify-end">
                <div class="bg-white shadow-sm sm:rounded-lg p-4 w-64 text-sm space-y-1">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span class="font-mono">{{ number_format($presupuesto->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Impuesto</span>
                        <span class="font-mono">{{ number_format($presupuesto->impuesto, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-800 border-t pt-1">
                        <span>Total</span>
                        <span class="font-mono">{{ number_format($presupuesto->total, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Zona peligro --}}
            @can('taller.gestionar')
                @if ($presupuesto->estado === 'borrador')
                    <div class="bg-white shadow-sm sm:rounded-lg p-4 border border-red-200">
                        <h3 class="text-sm font-semibold text-red-700 mb-2">Zona de peligro</h3>
                        <form method="POST" action="{{ route('admin.taller.presupuestos.destroy', $presupuesto) }}"
                            onsubmit="return confirm('¿Eliminar este presupuesto? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">
                                Eliminar presupuesto
                            </button>
                        </form>
                    </div>
                @endif
            @endcan

        </div>
    </div>
</x-app-layout>
