<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $edificio->nombre }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ $edificio->codigo }} · {{ $edificio->direccion }}</p>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.ph.edificios.index') }}" class="text-gray-500 hover:text-gray-900">← Edificios</a>
                @can('ph.gestionar')
                    <a href="{{ route('admin.ph.edificios.edit', $edificio) }}" class="text-gray-600 hover:text-gray-900">Editar</a>
                    <a href="{{ route('admin.ph.edificios.unidades.create', $edificio) }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">+ Nueva unidad</a>
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

            {{-- Estadísticas --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Total unidades</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $edificio->unidades->count() }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Con propietario</p>
                    <p class="mt-1 text-2xl font-bold text-green-700">{{ $edificio->unidades->whereNotNull('propietario_id')->count() }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Activas</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-700">{{ $edificio->unidades->where('activo', true)->count() }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase text-gray-500">Coef. total</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($edificio->unidades->sum('coeficiente') * 100, 2) }}%</p>
                </div>
            </div>

            {{-- Lista de unidades --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Unidades</h3>
                    <div class="flex gap-3 text-xs text-gray-500">
                        <a href="{{ route('admin.ph.cuotas.index', ['edificio_id' => $edificio->id]) }}" class="text-indigo-600 hover:underline">Ver cuotas</a>
                        <a href="{{ route('admin.ph.pagos.index', ['edificio_id' => $edificio->id]) }}" class="text-indigo-600 hover:underline">Ver pagos</a>
                    </div>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Número</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Piso</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Área m²</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Coeficiente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Propietario</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('ph.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($edificio->unidades as $u)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-mono">{{ $u->codigo }}</td>
                                <td class="px-4 py-2 font-semibold">{{ $u->numero }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $u->tipo }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $u->piso ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $u->area_m2 ? number_format($u->area_m2, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format($u->coeficiente * 100, 4) }}%</td>
                                <td class="px-4 py-2">{{ $u->propietario?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $u->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $u->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                @can('ph.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <a href="{{ route('admin.ph.edificios.unidades.edit', [$edificio, $u]) }}" class="text-xs text-gray-600 hover:underline">Editar</a>
                                    <form method="POST" action="{{ route('admin.ph.edificios.unidades.destroy', [$edificio, $u]) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar unidad {{ $u->numero }}?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-400">
                                    Sin unidades.
                                    @can('ph.gestionar')
                                        <a href="{{ route('admin.ph.edificios.unidades.create', $edificio) }}" class="text-indigo-600 hover:underline">Agregar la primera</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('ph.gestionar')
            {{-- Peligro: eliminar edificio --}}
            @if ($edificio->unidades->isEmpty())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <h3 class="text-sm font-semibold text-red-700 mb-2">Zona de peligro</h3>
                <form method="POST" action="{{ route('admin.ph.edificios.destroy', $edificio) }}"
                      onsubmit="return confirm('¿Eliminar el edificio {{ $edificio->nombre }}?')">
                    @csrf @method('DELETE')
                    <button class="rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">Eliminar edificio</button>
                </form>
            </div>
            @endif
            @endcan
        </div>
    </div>
</x-app-layout>
