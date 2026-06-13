<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar pago</h2>
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

            @if ($cuota)
            {{-- Info de la cuota preseleccionada --}}
            <div class="mb-4 rounded-lg bg-indigo-50 p-4 text-sm">
                <div class="flex justify-between">
                    <div>
                        <p class="font-semibold text-indigo-900">
                            {{ $cuota->unidad->edificio->nombre }} / Unidad {{ $cuota->unidad->numero }}
                        </p>
                        <p class="text-indigo-700">{{ $cuota->tipoCuota->nombre }} — Período {{ $cuota->periodo }}</p>
                        @if ($cuota->unidad->propietario)
                            <p class="text-indigo-600">{{ $cuota->unidad->propietario->nombre }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-indigo-600">Monto cuota</p>
                        <p class="text-lg font-bold text-indigo-900">B/. {{ number_format($cuota->monto, 2) }}</p>
                        <p class="text-xs text-indigo-600">Saldo pendiente</p>
                        <p class="text-xl font-bold text-orange-700">B/. {{ number_format($cuota->saldoPendiente(), 2) }}</p>
                    </div>
                </div>
            </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.prh.pagos.store') }}">
                    @csrf

                    @if ($cuota)
                        <input type="hidden" name="cuota_id" value="{{ $cuota->id }}">
                    @else
                    {{-- Búsqueda libre de cuota --}}
                    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Edificio" />
                            <select id="sel_edificio" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">— Seleccione edificio —</option>
                                @foreach ($edificios as $ed)
                                    <option value="{{ $ed->id }}">{{ $ed->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Tipo de cuota" />
                            <select id="sel_tipo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">— Seleccione tipo —</option>
                                @foreach ($tiposCuota as $tc)
                                    <option value="{{ $tc->id }}">{{ $tc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <x-input-label value="ID de cuota *" />
                        <x-text-input name="cuota_id" type="number" class="mt-1 block w-full" :value="old('cuota_id')" required placeholder="Ingrese el ID de la cuota" />
                        <p class="mt-1 text-xs text-gray-400">Puede obtener el ID desde el listado de cuotas.</p>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="fecha_pago" value="Fecha de pago *" />
                            <x-text-input id="fecha_pago" name="fecha_pago" type="date" class="mt-1 block w-full"
                                :value="old('fecha_pago', now()->format('Y-m-d'))" required />
                        </div>
                        <div>
                            <x-input-label for="monto" value="Monto B/. *" />
                            <x-text-input id="monto" name="monto" type="number" step="0.01" min="0.01"
                                class="mt-1 block w-full" :value="old('monto', $cuota?->saldoPendiente())" required />
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="forma_pago" value="Forma de pago *" />
                            <select id="forma_pago" name="forma_pago" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @foreach (\App\Models\PrhPago::FORMAS_PAGO as $fp)
                                    <option value="{{ $fp }}" @selected(old('forma_pago', 'EFECTIVO') === $fp)>{{ $fp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="referencia" value="Referencia / N° cheque / transferencia" />
                            <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                                :value="old('referencia')" maxlength="150" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="notas" value="Notas" />
                        <textarea id="notas" name="notas" rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            maxlength="500">{{ old('notas') }}</textarea>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Guardar pago</x-primary-button>
                        <a href="{{ route('admin.prh.cuotas.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
