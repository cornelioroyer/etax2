<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $equipo->nombre ?? $equipo->codigo ?? 'Equipo #'.$equipo->id }}
                </h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    <a href="{{ route('admin.taller.equipos.index') }}" class="hover:underline">Equipos</a>
                    &rsaquo; {{ $equipo->taller->nombre }}
                    &nbsp;·&nbsp;
                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $equipo->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $equipo->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </p>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.equipos.index') }}" class="text-gray-500 hover:text-gray-900">← Equipos</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.equipos.edit', $equipo) }}"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">Editar</a>
                @endcan
            </div>
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

            {{-- Datos básicos --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Datos del equipo</h3>
                <div class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-3 text-sm">
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Código</p>
                        <p class="mt-0.5 font-mono">{{ $equipo->codigo ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Nombre</p>
                        <p class="mt-0.5">{{ $equipo->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Taller</p>
                        <p class="mt-0.5">{{ $equipo->taller->nombre }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Tipo</p>
                        <p class="mt-0.5">{{ $equipo->tipoEquipo?->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Marca</p>
                        <p class="mt-0.5">{{ $equipo->marca?->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Modelo</p>
                        <p class="mt-0.5">{{ $equipo->modelo?->nombre ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Número de serie</p>
                        <p class="mt-0.5 font-mono">{{ $equipo->numero_serie ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Placa</p>
                        <p class="mt-0.5 font-mono">{{ $equipo->placa ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">VIN / Chasis</p>
                        <p class="mt-0.5 font-mono">{{ $equipo->vin ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Año</p>
                        <p class="mt-0.5">{{ $equipo->anio ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-500">Color</p>
                        <p class="mt-0.5">{{ $equipo->color ?? '—' }}</p>
                    </div>
                    @if ($equipo->descripcion)
                        <div class="col-span-2 sm:col-span-3">
                            <p class="text-xs font-medium uppercase text-gray-500">Descripción</p>
                            <p class="mt-0.5 text-gray-700">{{ $equipo->descripcion }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Clientes / Propietarios --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Clientes / Propietarios</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Relación</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Principal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Desde</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('taller.gestionar')
                                <th></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($equipo->clientes as $ce)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ $ce->cliente?->nombre ?? 'ID '.$ce->cliente_id }}</div>
                                    @if ($ce->cliente?->identificacion)
                                        <div class="text-xs text-gray-500">{{ $ce->cliente->identificacion }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    {{ \App\Models\TallerClienteEquipo::RELACIONES[$ce->relacion] ?? $ce->relacion }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if ($ce->principal)
                                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-700">Principal</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600">
                                    {{ $ce->fecha_inicio?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $ce->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $ce->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                @can('taller.gestionar')
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('admin.taller.equipos.clientes.destroy', [$equipo, $ce]) }}"
                                            onsubmit="return confirm('¿Eliminar esta relación?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->can('taller.gestionar') ? 6 : 5 }}"
                                    class="px-4 py-6 text-center text-gray-400 text-sm">
                                    Sin clientes asociados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @can('taller.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Agregar cliente</p>
                        <form method="POST" action="{{ route('admin.taller.equipos.clientes.store', $equipo) }}"
                            class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">ID del contacto</label>
                                <input type="number" name="cliente_id" placeholder="ID numérico" required min="1"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-36">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Relación</label>
                                <select name="relacion" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach (\App\Models\TallerClienteEquipo::RELACIONES as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Desde</label>
                                <input type="date" name="fecha_inicio"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div class="flex items-center gap-2 pb-1">
                                <input type="checkbox" id="principal" name="principal" value="1"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <label for="principal" class="text-xs text-gray-700">Principal</label>
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Agregar</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Historial de mediciones --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Historial de mediciones</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Valor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Unidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Observación</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($mediciones as $med)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-xs text-gray-700">
                                    {{ $med->fecha?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs font-medium">{{ $med->tipo_medicion }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format($med->valor, 2) }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $med->unidad ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ $med->observacion ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $med->created_by ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">Sin mediciones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @can('taller.gestionar')
                    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 mb-3">Registrar medición</p>
                        <form method="POST" action="{{ route('admin.taller.equipos.mediciones.store', $equipo) }}"
                            class="flex flex-wrap gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Tipo de medición *</label>
                                <input type="text" name="tipo_medicion" placeholder="Ej: Kilometraje, Horas" required maxlength="100"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-44">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Valor *</label>
                                <input type="number" name="valor" step="0.0001" required
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-28">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Unidad</label>
                                <input type="text" name="unidad" placeholder="km, hrs, etc." maxlength="50"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-24">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Fecha</label>
                                <input type="datetime-local" name="fecha"
                                    value="{{ now()->format('Y-m-d\TH:i') }}"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Observación</label>
                                <input type="text" name="observacion" maxlength="500"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-48">
                            </div>
                            <div class="pb-0.5">
                                <x-primary-button>Registrar</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endcan
            </div>

            {{-- Zona de peligro --}}
            @can('taller.gestionar')
                <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <h3 class="text-sm font-semibold text-red-700 mb-2">Zona de peligro</h3>
                    <form method="POST" action="{{ route('admin.taller.equipos.destroy', $equipo) }}"
                          onsubmit="return confirm('¿Eliminar el equipo {{ addslashes($equipo->nombre ?? 'este equipo') }}?')">
                        @csrf @method('DELETE')
                        <button class="rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">
                            Eliminar equipo
                        </button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
