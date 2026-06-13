<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar orden {{ $orden->numero }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    <a href="{{ route('admin.taller.ordenes.index') }}" class="hover:underline">Órdenes</a>
                    &rsaquo; <a href="{{ route('admin.taller.ordenes.show', $orden) }}" class="hover:underline">{{ $orden->numero }}</a>
                    &rsaquo; Editar
                </p>
            </div>
            <a href="{{ route('admin.taller.ordenes.show', $orden) }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.taller.ordenes.update', $orden) }}">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Prioridad --}}
                        <div>
                            <x-input-label for="prioridad" value="Prioridad *" />
                            <select id="prioridad" name="prioridad" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerOrden::PRIORIDADES as $val => $label)
                                    <option value="{{ $val }}" {{ old('prioridad', $orden->prioridad) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tipo de servicio --}}
                        <div>
                            <x-input-label for="tipo_servicio" value="Tipo de servicio *" />
                            <select id="tipo_servicio" name="tipo_servicio" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerOrden::TIPOS_SERVICIO as $val => $label)
                                    <option value="{{ $val }}" {{ old('tipo_servicio', $orden->tipo_servicio) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Origen --}}
                        <div>
                            <x-input-label for="origen" value="Origen *" />
                            <select id="origen" name="origen" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\TallerOrden::ORIGENES as $val => $label)
                                    <option value="{{ $val }}" {{ old('origen', $orden->origen) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Fecha prometida --}}
                        <div>
                            <x-input-label for="fecha_prometida" value="Fecha prometida de entrega" />
                            <x-text-input id="fecha_prometida" name="fecha_prometida" type="datetime-local"
                                class="mt-1 block w-full"
                                :value="old('fecha_prometida', $orden->fecha_prometida?->format('Y-m-d\TH:i'))" />
                        </div>

                        {{-- Garantía días --}}
                        <div>
                            <x-input-label for="garantia_dias" value="Días de garantía" />
                            <x-text-input id="garantia_dias" name="garantia_dias" type="number" class="mt-1 block w-full"
                                :value="old('garantia_dias', $orden->garantia_dias)" min="0" />
                        </div>

                        {{-- Medidor --}}
                        <div>
                            <x-input-label value="Medidor" />
                            <div class="mt-1 flex gap-2">
                                <x-text-input name="medidor_valor" type="number" step="0.0001"
                                    class="w-32" :value="old('medidor_valor', $orden->medidor_valor)" placeholder="Valor" />
                                <x-text-input name="medidor_unidad" type="text" maxlength="50"
                                    class="flex-1" :value="old('medidor_unidad', $orden->medidor_unidad)" placeholder="Unidad (km, hrs...)" />
                            </div>
                        </div>
                    </div>

                    {{-- Síntomas reportados --}}
                    <div class="mt-4">
                        <x-input-label for="sintomas_reportados" value="Síntomas reportados" />
                        <textarea id="sintomas_reportados" name="sintomas_reportados" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('sintomas_reportados', $orden->sintomas_reportados) }}</textarea>
                    </div>

                    {{-- Observación de recepción --}}
                    <div class="mt-4">
                        <x-input-label for="observacion_recepcion" value="Observación de recepción" />
                        <textarea id="observacion_recepcion" name="observacion_recepcion" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('observacion_recepcion', $orden->observacion_recepcion) }}</textarea>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Guardar cambios</x-primary-button>
                        <a href="{{ route('admin.taller.ordenes.show', $orden) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
