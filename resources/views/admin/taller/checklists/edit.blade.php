<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Checklist — {{ $checklist->nombre }}</h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.checklists.show', $checklist) }}" class="text-gray-500 hover:text-gray-900">← Ver checklist</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Datos generales del checklist --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Datos generales</h3>
                <form method="POST" action="{{ route('admin.taller.checklists.update', $checklist) }}">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="tipo_equipo_id" value="Tipo de equipo" />
                            <select id="tipo_equipo_id" name="tipo_equipo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— General —</option>
                                @foreach ($tiposEquipo as $te)
                                    <option value="{{ $te->id }}" {{ old('tipo_equipo_id', $checklist->tipo_equipo_id) == $te->id ? 'selected' : '' }}>{{ $te->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="tipo_checklist" value="Tipo de checklist *" />
                            <select id="tipo_checklist" name="tipo_checklist" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerChecklist::TIPOS as $val => $label)
                                    <option value="{{ $val }}" {{ old('tipo_checklist', $checklist->tipo_checklist) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="codigo" value="Código *" />
                            <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full"
                                value="{{ old('codigo', $checklist->codigo) }}" required maxlength="30" />
                        </div>
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                value="{{ old('nombre', $checklist->nombre) }}" required maxlength="200" />
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" id="activo" name="activo" value="1" class="rounded border-gray-300 text-indigo-600"
                            {{ old('activo', $checklist->activo) ? 'checked' : '' }}>
                        <x-input-label for="activo" value="Activo" class="mb-0" />
                    </div>
                    <div class="mt-4 flex gap-3">
                        <x-primary-button>Actualizar datos</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Ítems del checklist --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Ítems ({{ $checklist->detalles->count() }})</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Orden</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo resp.</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Oblig.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($checklist->detalles as $d)
                            <tr>
                                <td class="px-4 py-2 text-right font-mono text-xs text-gray-500">{{ $d->orden }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $d->codigo }}</td>
                                <td class="px-4 py-2">{{ $d->descripcion }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ \App\Models\TallerChecklistDetalle::TIPOS_RESPUESTA[$d->tipo_respuesta] ?? $d->tipo_respuesta }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="{{ $d->obligatorio ? 'text-red-600 font-semibold' : 'text-gray-400' }} text-xs">
                                        {{ $d->obligatorio ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @can('taller.gestionar')
                                        <form method="POST"
                                              action="{{ route('admin.taller.checklists.detalles.destroy', [$checklist, $d]) }}"
                                              class="inline"
                                              onsubmit="return confirm('¿Eliminar este ítem?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-600 hover:underline">Quitar</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-4 text-center text-gray-400 text-sm">Sin ítems aún.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Agregar nuevo ítem --}}
            @can('taller.gestionar')
            <div class="bg-white p-5 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Agregar ítem</h3>
                <form method="POST" action="{{ route('admin.taller.checklists.detalles.store', $checklist) }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="d_codigo" value="Código *" />
                            <x-text-input id="d_codigo" name="codigo" type="text" class="mt-1 block w-full"
                                :value="old('codigo')" required maxlength="30" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="d_descripcion" value="Descripción *" />
                            <x-text-input id="d_descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                                :value="old('descripcion')" required maxlength="500" />
                        </div>
                        <div>
                            <x-input-label for="d_tipo_respuesta" value="Tipo de respuesta *" />
                            <select id="d_tipo_respuesta" name="tipo_respuesta" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerChecklistDetalle::TIPOS_RESPUESTA as $val => $label)
                                    <option value="{{ $val }}" {{ old('tipo_respuesta') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="d_orden" value="Orden" />
                            <x-text-input id="d_orden" name="orden" type="number" min="0" class="mt-1 block w-full"
                                :value="old('orden')" placeholder="Auto" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="obligatorio" value="0">
                                <input type="checkbox" name="obligatorio" value="1" class="rounded border-gray-300 text-indigo-600"
                                    {{ old('obligatorio') ? 'checked' : '' }}>
                                Obligatorio
                            </label>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>+ Agregar ítem</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan
        </div>
    </div>
</x-app-layout>
