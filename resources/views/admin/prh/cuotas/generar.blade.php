<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Generar cuotas</h2>
            <a href="{{ route('admin.prh.cuotas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Cuotas</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="mb-4 text-sm text-gray-600">
                    Genera una cuota por cada unidad activa del edificio seleccionado para el período indicado.
                    Las unidades que ya tengan cuota del mismo tipo y período serán omitidas.
                </p>

                <form method="POST" action="{{ route('admin.prh.cuotas.procesarGenerar') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="edificio_id" value="Edificio *" />
                            <select id="edificio_id" name="edificio_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— Seleccione —</option>
                                @foreach ($edificios as $ed)
                                    <option value="{{ $ed->id }}" @selected(old('edificio_id') == $ed->id)>{{ $ed->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="tipo_cuota_id" value="Tipo de cuota *" />
                            <select id="tipo_cuota_id" name="tipo_cuota_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— Seleccione —</option>
                                @foreach ($tiposCuota as $tc)
                                    <option value="{{ $tc->id }}" @selected(old('tipo_cuota_id') == $tc->id)>
                                        {{ $tc->nombre }} — B/. {{ number_format($tc->monto_base, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="periodo" value="Período (YYYY-MM) *" />
                            <x-text-input id="periodo" name="periodo" type="text" class="mt-1 block w-full"
                                :value="old('periodo', now()->format('Y-m'))" required maxlength="7" placeholder="2026-06" />
                        </div>
                        <div>
                            <x-input-label for="fecha_emision" value="Fecha de emisión *" />
                            <x-text-input id="fecha_emision" name="fecha_emision" type="date" class="mt-1 block w-full"
                                :value="old('fecha_emision', now()->format('Y-m-d'))" required />
                        </div>
                        <div>
                            <x-input-label for="fecha_vencimiento" value="Fecha de vencimiento *" />
                            <x-text-input id="fecha_vencimiento" name="fecha_vencimiento" type="date" class="mt-1 block w-full"
                                :value="old('fecha_vencimiento', now()->endOfMonth()->format('Y-m-d'))" required />
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="usar_coeficiente" value="0">
                            <input type="checkbox" name="usar_coeficiente" value="1" class="rounded border-gray-300 text-indigo-600"
                                {{ old('usar_coeficiente') ? 'checked' : '' }}>
                            Calcular monto proporcional según coeficiente de cada unidad
                        </label>
                        <p class="mt-1 ml-6 text-xs text-gray-400">Si está marcado: monto = monto_base × coeficiente. Si el coeficiente es 0, se usa el monto base.</p>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Generar cuotas</x-primary-button>
                        <a href="{{ route('admin.prh.cuotas.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
