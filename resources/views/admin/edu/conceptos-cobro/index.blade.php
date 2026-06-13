<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Conceptos de cobro</h2>
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
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Nuevo concepto</h3>
                <form method="POST" action="{{ route('admin.edu.conceptos-cobro.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="cc_institucion_id" value="Institución *" />
                            <select id="cc_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="cc_nombre" value="Nombre *" />
                            <x-text-input id="cc_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="200" />
                        </div>
                        <div>
                            <x-input-label for="cc_tipo" value="Tipo" />
                            <select id="cc_tipo" name="tipo_concepto"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                <option value="matricula" @selected(old('tipo_concepto')=='matricula')>Matrícula</option>
                                <option value="mensualidad" @selected(old('tipo_concepto')=='mensualidad')>Mensualidad</option>
                                <option value="pension" @selected(old('tipo_concepto')=='pension')>Pensión</option>
                                <option value="servicio" @selected(old('tipo_concepto')=='servicio')>Servicio</option>
                                <option value="otro" @selected(old('tipo_concepto')=='otro')>Otro</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="cc_frecuencia" value="Frecuencia" />
                            <select id="cc_frecuencia" name="frecuencia"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguna —</option>
                                <option value="unica" @selected(old('frecuencia')=='unica')>Única</option>
                                <option value="mensual" @selected(old('frecuencia')=='mensual')>Mensual</option>
                                <option value="bimestral" @selected(old('frecuencia')=='bimestral')>Bimestral</option>
                                <option value="trimestral" @selected(old('frecuencia')=='trimestral')>Trimestral</option>
                                <option value="semestral" @selected(old('frecuencia')=='semestral')>Semestral</option>
                                <option value="anual" @selected(old('frecuencia')=='anual')>Anual</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="cc_monto" value="Monto base" />
                            <x-text-input id="cc_monto" name="monto_base" type="number" step="0.01" class="mt-1 block w-full"
                                :value="old('monto_base')" min="0" />
                        </div>
                        <div>
                            <x-input-label for="cc_descripcion" value="Descripción" />
                            <x-text-input id="cc_descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                                :value="old('descripcion')" maxlength="500" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar concepto</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Institución</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Frecuencia</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Monto base</th>
                            @can('edu.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($conceptos as $concepto)
                        <tr x-data="{ edit: false }">
                            <td class="px-4 py-2 font-medium">
                                <span x-show="!edit">{{ $concepto->nombre }}</span>
                                <div x-show="edit" x-cloak>
                                    <form method="POST" action="{{ route('admin.edu.conceptos-cobro.update', $concepto) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="institucion_id" value="{{ $concepto->institucion_id }}">
                                        <input type="hidden" name="tipo_concepto" value="{{ $concepto->tipo_concepto }}">
                                        <input type="hidden" name="frecuencia" value="{{ $concepto->frecuencia }}">
                                        <input type="hidden" name="monto_base" value="{{ $concepto->monto_base }}">
                                        <x-text-input name="nombre" type="text" :value="$concepto->nombre" required class="text-sm py-0.5 px-2" />
                                        <x-primary-button class="py-0.5 text-xs">Guardar</x-primary-button>
                                        <button type="button" @click="edit=false" class="text-xs text-gray-500 hover:underline">Cancelar</button>
                                    </form>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $concepto->institucion?->nombre }}</td>
                            <td class="px-4 py-2 text-center capitalize">{{ $concepto->tipo_concepto ?? '—' }}</td>
                            <td class="px-4 py-2 text-center capitalize">{{ $concepto->frecuencia ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($concepto->monto_base ?? 0, 2) }}</td>
                            @can('edu.gestionar')
                            <td class="px-4 py-2 text-right space-x-2">
                                <button @click="edit=!edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                <form method="POST" action="{{ route('admin.edu.conceptos-cobro.destroy', $concepto) }}" class="inline"
                                      onsubmit="return confirm('¿Eliminar concepto?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin conceptos de cobro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $conceptos->links() }}</div>
        </div>
    </div>
</x-app-layout>
