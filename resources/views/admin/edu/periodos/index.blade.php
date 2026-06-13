<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Períodos Académicos</h2>
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

            @can('edu.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo período académico</h3>
                <form method="POST" action="{{ route('admin.edu.periodos.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label for="per_institucion_id" value="Institución *" />
                            <select id="per_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="per_codigo" value="Código *" />
                            <x-text-input id="per_codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="per_nombre" value="Nombre *" />
                            <x-text-input id="per_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="per_anio" value="Año *" />
                            <x-text-input id="per_anio" name="anio" type="number" class="mt-1 block w-full"
                                :value="old('anio', date('Y'))" required min="2000" max="2099" />
                        </div>
                        <div>
                            <x-input-label for="per_fecha_inicio" value="Fecha inicio *" />
                            <x-text-input id="per_fecha_inicio" name="fecha_inicio" type="date" class="mt-1 block w-full"
                                :value="old('fecha_inicio')" required />
                        </div>
                        <div>
                            <x-input-label for="per_fecha_fin" value="Fecha fin *" />
                            <x-text-input id="per_fecha_fin" name="fecha_fin" type="date" class="mt-1 block w-full"
                                :value="old('fecha_fin')" required />
                        </div>
                        <div>
                            <x-input-label for="per_estado" value="Estado *" />
                            <select id="per_estado" name="estado"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="abierto" @selected(old('estado','abierto')=='abierto')>Abierto</option>
                                <option value="cerrado" @selected(old('estado')=='cerrado')>Cerrado</option>
                                <option value="archivado" @selected(old('estado')=='archivado')>Archivado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar período</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Año</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Inicio</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fin</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Estado</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($periodos as $per)
                            <tr x-data="{ edit: false }">
                                <td class="px-4 py-2 font-mono">{{ $per->codigo }}</td>
                                <td class="px-4 py-2 font-medium">{{ $per->nombre }}</td>
                                <td class="px-4 py-2 text-center">{{ $per->anio }}</td>
                                <td class="px-4 py-2">{{ $per->fecha_inicio?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">{{ $per->fecha_fin?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $per->institucion?->nombre }}</td>
                                <td class="px-4 py-2 text-center">
                                    @php $colors = ['abierto'=>'bg-green-100 text-green-800','cerrado'=>'bg-yellow-100 text-yellow-800','archivado'=>'bg-gray-100 text-gray-600']; @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $colors[$per->estado] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst($per->estado) }}
                                    </span>
                                </td>
                                @can('edu.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    <form method="POST" action="{{ route('admin.edu.periodos.destroy', $per) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar período?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                            @can('edu.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="8" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.edu.periodos.update', $per) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                            <div>
                                                <x-input-label value="Código *" />
                                                <x-text-input name="codigo" type="text" class="mt-1 block w-full"
                                                    value="{{ $per->codigo }}" required maxlength="30" />
                                            </div>
                                            <div>
                                                <x-input-label value="Nombre *" />
                                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                                    value="{{ $per->nombre }}" required maxlength="200" />
                                            </div>
                                            <div>
                                                <x-input-label value="Año *" />
                                                <x-text-input name="anio" type="number" class="mt-1 block w-full"
                                                    value="{{ $per->anio }}" required min="2000" max="2099" />
                                            </div>
                                            <div>
                                                <x-input-label value="Estado *" />
                                                <select name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                                                    <option value="abierto" @selected($per->estado=='abierto')>Abierto</option>
                                                    <option value="cerrado" @selected($per->estado=='cerrado')>Cerrado</option>
                                                    <option value="archivado" @selected($per->estado=='archivado')>Archivado</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Fecha inicio *" />
                                                <x-text-input name="fecha_inicio" type="date" class="mt-1 block w-full"
                                                    value="{{ $per->fecha_inicio?->format('Y-m-d') }}" required />
                                            </div>
                                            <div>
                                                <x-input-label value="Fecha fin *" />
                                                <x-text-input name="fecha_fin" type="date" class="mt-1 block w-full"
                                                    value="{{ $per->fecha_fin?->format('Y-m-d') }}" required />
                                            </div>
                                        </div>
                                        <div class="mt-3 flex gap-2">
                                            <x-primary-button>Actualizar</x-primary-button>
                                            <button type="button" @click="edit = false"
                                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">Sin períodos académicos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $periodos->links() }}</div>
        </div>
    </div>
</x-app-layout>
