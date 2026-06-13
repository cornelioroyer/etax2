<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Configuración — Educación</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Selector de institución --}}
            <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                <form method="GET" action="{{ route('admin.edu.configuracion.index') }}" class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label for="inst_sel" value="Institución" />
                        <select id="inst_sel" name="institucion_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            onchange="this.form.submit()">
                            <option value="">— seleccione —</option>
                            @foreach ($instituciones as $i)
                                <option value="{{ $i->id }}" @selected($i->id == $institucionId)>{{ $i->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            @if ($institucionId)
            @can('edu.gestionar')
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-4 text-sm font-semibold text-gray-700">Parámetros de cobro</h3>
                <form method="POST" action="{{ route('admin.edu.configuracion.update') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="institucion_id" value="{{ $institucionId }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="dia_venc" value="Día de vencimiento mensualidad (1-31)" />
                            <x-text-input id="dia_venc" name="dia_vencimiento_mensualidad" type="number"
                                class="mt-1 block w-full" :value="old('dia_vencimiento_mensualidad', $config?->dia_vencimiento_mensualidad)"
                                min="1" max="31" />
                        </div>
                        <div>
                            <x-input-label value="Tipo de recargo por mora" />
                            <select name="tipo_recargo_mora"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                <option value="fijo" @selected(old('tipo_recargo_mora', $config?->tipo_recargo_mora) === 'fijo')>Monto fijo</option>
                                <option value="porcentaje" @selected(old('tipo_recargo_mora', $config?->tipo_recargo_mora) === 'porcentaje')>Porcentaje</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="recargo_fijo" value="Recargo monto fijo" />
                            <x-text-input id="recargo_fijo" name="recargo_monto_fijo" type="number" step="0.01"
                                class="mt-1 block w-full" :value="old('recargo_monto_fijo', $config?->recargo_monto_fijo)" min="0" />
                        </div>
                        <div>
                            <x-input-label for="recargo_pct" value="Recargo porcentaje (%)" />
                            <x-text-input id="recargo_pct" name="recargo_porcentaje" type="number" step="0.01"
                                class="mt-1 block w-full" :value="old('recargo_porcentaje', $config?->recargo_porcentaje)" min="0" max="100" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="generar_cargos_automaticos" value="0">
                                <input type="checkbox" name="generar_cargos_automaticos" value="1"
                                    {{ old('generar_cargos_automaticos', $config?->generar_cargos_automaticos) ? 'checked' : '' }}
                                    class="rounded border-gray-300">
                                Generar cargos automáticamente
                            </label>
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button>Guardar configuración</x-primary-button>
                    </div>
                </form>
            </div>
            @endcan

            @if (!auth()->user()->can('edu.gestionar'))
            <div class="bg-white p-6 shadow-sm sm:rounded-lg text-sm text-gray-600">
                <p>Día vencimiento: <strong>{{ $config?->dia_vencimiento_mensualidad ?? '—' }}</strong></p>
                <p>Tipo recargo mora: <strong>{{ $config?->tipo_recargo_mora ?? '—' }}</strong></p>
            </div>
            @endif
            @endif
        </div>
    </div>
</x-app-layout>
