<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva orden de trabajo</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    <a href="{{ route('admin.taller.ordenes.index') }}" class="hover:underline">Órdenes</a> &rsaquo; Nueva
                </p>
            </div>
            <a href="{{ route('admin.taller.ordenes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.taller.ordenes.store') }}">
                    @csrf

                    {{-- Taller --}}
                    <div>
                        <x-input-label for="taller_id" value="Taller *" />
                        <select id="taller_id" name="taller_id" required
                            onchange="this.form.submit()"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Seleccione un taller —</option>
                            @foreach ($talleres as $t)
                                <option value="{{ $t->id }}" {{ old('taller_id', $tallerId) == $t->id ? 'selected' : '' }}>
                                    {{ $t->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Al seleccionar el taller se cargan los equipos disponibles.</p>
                    </div>

                    @if ($taller)
                        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Columna izquierda --}}
                            <div class="space-y-4">
                                {{-- Equipo --}}
                                <div>
                                    <x-input-label for="equipo_id" value="Equipo" />
                                    <select id="equipo_id" name="equipo_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">— Sin equipo —</option>
                                        @foreach ($equipos as $eq)
                                            <option value="{{ $eq->id }}" {{ old('equipo_id') == $eq->id ? 'selected' : '' }}>
                                                {{ $eq->nombre ?? $eq->codigo ?? 'Equipo #'.$eq->id }}
                                                @if ($eq->numero_serie) (S/N: {{ $eq->numero_serie }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Cliente --}}
                                <div>
                                    <x-input-label for="cliente_id" value="ID del cliente" />
                                    <x-text-input id="cliente_id" name="cliente_id" type="number" class="mt-1 block w-full"
                                        :value="old('cliente_id')" min="1" placeholder="ID numérico del contacto" />
                                    <p class="mt-1 text-xs text-gray-500">Ingrese el ID numérico del contacto/cliente.</p>
                                </div>

                                {{-- Tipo de servicio --}}
                                <div>
                                    <x-input-label for="tipo_servicio" value="Tipo de servicio *" />
                                    <select id="tipo_servicio" name="tipo_servicio" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        @foreach (\App\Models\TallerOrden::TIPOS_SERVICIO as $val => $label)
                                            <option value="{{ $val }}" {{ old('tipo_servicio', 'reparacion') === $val ? 'selected' : '' }}>
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
                                            <option value="{{ $val }}" {{ old('origen', 'mostrador') === $val ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Prioridad --}}
                                <div>
                                    <x-input-label for="prioridad" value="Prioridad *" />
                                    <select id="prioridad" name="prioridad" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        @foreach (\App\Models\TallerOrden::PRIORIDADES as $val => $label)
                                            <option value="{{ $val }}" {{ old('prioridad', 'normal') === $val ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Columna derecha --}}
                            <div class="space-y-4">
                                {{-- Fecha prometida --}}
                                <div>
                                    <x-input-label for="fecha_prometida" value="Fecha prometida de entrega" />
                                    <x-text-input id="fecha_prometida" name="fecha_prometida" type="datetime-local"
                                        class="mt-1 block w-full" :value="old('fecha_prometida')" />
                                </div>

                                {{-- Síntomas reportados --}}
                                <div>
                                    <x-input-label for="sintomas_reportados" value="Síntomas reportados" />
                                    <textarea id="sintomas_reportados" name="sintomas_reportados" rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="Describa los síntomas que reporta el cliente...">{{ old('sintomas_reportados') }}</textarea>
                                </div>

                                {{-- Observación de recepción --}}
                                <div>
                                    <x-input-label for="observacion_recepcion" value="Observación de recepción" />
                                    <textarea id="observacion_recepcion" name="observacion_recepcion" rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="Estado del equipo al momento de la recepción...">{{ old('observacion_recepcion') }}</textarea>
                                </div>

                                {{-- Medidor --}}
                                <div>
                                    <x-input-label value="Medidor (opcional)" />
                                    <div class="mt-1 flex gap-2">
                                        <x-text-input name="medidor_valor" type="number" step="0.0001"
                                            class="w-32" :value="old('medidor_valor')" placeholder="Valor" />
                                        <x-text-input name="medidor_unidad" type="text" maxlength="50"
                                            class="flex-1" :value="old('medidor_unidad')" placeholder="Unidad (km, hrs, etc.)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <x-primary-button>Crear orden de trabajo</x-primary-button>
                            <a href="{{ route('admin.taller.ordenes.index') }}"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Seleccione un taller para continuar con el formulario.</p>
                    @endif
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
