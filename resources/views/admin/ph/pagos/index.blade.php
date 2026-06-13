<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pagos de cuotas</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.ph.cuotas.index') }}" class="text-gray-500 hover:text-gray-900">← Cuotas</a>
                @can('ph.gestionar')
                    <a href="{{ route('admin.ph.pagos.create') }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Registrar pago</a>
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
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div>
                    <x-input-label value="Edificio" />
                    <select name="edificio_id" class="mt-1 block rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Todos</option>
                        @foreach ($edificios as $ed)
                            <option value="{{ $ed->id }}" @selected($edificioId == $ed->id)>{{ $ed->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Período" />
                    <x-text-input name="periodo" type="text" class="mt-1 block w-28" :value="$periodo" placeholder="2026-06" maxlength="7" />
                </div>
                <x-primary-button>Filtrar</x-primary-button>
                <a href="{{ route('admin.ph.pagos.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Limpiar</a>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha pago</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Edificio / Unidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Propietario</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Período</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo cuota</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Monto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Forma</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Referencia</th>
                            @can('ph.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($pagos as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">{{ $p->fecha_pago->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-medium">{{ $p->cuota->unidad->edificio->nombre }}</span>
                                    <span class="text-gray-500"> / {{ $p->cuota->unidad->numero }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->cuota->unidad->propietario?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono">{{ $p->cuota->periodo }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->cuota->tipoCuota->nombre }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold text-green-700">B/. {{ number_format($p->monto, 2) }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->forma_pago }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $p->referencia ?? '—' }}</td>
                                @can('ph.gestionar')
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('admin.ph.pagos.destroy', $p) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar este pago? Se ajustará el saldo de la cuota.')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-gray-400">Sin pagos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($pagos->isNotEmpty())
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-xs font-semibold text-gray-600">Total (página)</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold text-green-700">B/. {{ number_format($pagos->sum('monto'), 2) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            {{ $pagos->links() }}
        </div>
    </div>
</x-app-layout>
