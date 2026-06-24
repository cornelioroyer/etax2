<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $esCredito ? 'Nueva nota de crédito' : 'Nueva nota de débito' }} — CxC
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="rounded-md bg-{{ $esCredito ? 'emerald' : 'amber' }}-50 p-4 text-sm text-{{ $esCredito ? 'emerald' : 'amber' }}-800">
                @if ($esCredito)
                    La <strong>nota de crédito</strong> reduce la deuda del cliente y se aplica a una factura con saldo.
                @else
                    La <strong>nota de débito</strong> aumenta la deuda del cliente y genera un saldo cobrable.
                @endif
            </div>

            {{-- Paso 1 (solo NC): elegir cliente para cargar sus facturas con saldo --}}
            @if ($esCredito)
                <form method="GET" action="{{ route('admin.cxc.notas.create') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <input type="hidden" name="tipo" value="{{ $tipo }}">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-64 flex-1">
                            <x-buscador-contacto name="cliente_id" label="Cliente *" submit-on-select
                                placeholder="— Selecciona el cliente —"
                                :opciones="$clientes" :selected="$clienteId" />
                        </div>
                        <p class="pb-2 text-xs text-gray-500">Al elegir el cliente se cargan sus facturas con saldo.</p>
                    </div>
                </form>
            @endif

            @if ($esCredito && $clienteId && $facturas->isEmpty())
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    El cliente no tiene facturas con saldo pendiente para aplicar una nota de crédito.
                </div>
            @endif

            @if (! $esCredito || ($clienteId && $facturas->isNotEmpty()))
                <form method="POST" action="{{ route('admin.cxc.notas.store', ['tipo' => $tipo]) }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                    @csrf

                    @if ($esCredito)
                        <input type="hidden" name="cliente_id" value="{{ $clienteId }}">
                        <div>
                            <x-input-label for="factura_id" value="Factura a la que se aplica *" />
                            <select id="factura_id" name="factura_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($facturas as $f)
                                    <option value="{{ $f->id }}" data-saldo="{{ (float) $f->saldo }}" @selected(old('factura_id') == $f->id)>{{ $f->numero }} · {{ $f->fecha->format('d/m/Y') }} · saldo B/. {{ number_format((float) $f->saldo, 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <x-buscador-contacto name="cliente_id" label="Cliente *" required
                                placeholder="— Selecciona el cliente —"
                                :opciones="$clientes" :selected="old('cliente_id')" />
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="fecha" value="Fecha *" />
                            <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" :value="old('fecha', now()->format('Y-m-d'))" />
                        </div>
                        <div>
                            <x-input-label for="cuenta_id" :value="$esCredito ? 'Cuenta de contrapartida (descuento/devolución) *' : 'Cuenta de ingreso/cargo *'" />
                            <select id="cuenta_id" name="cuenta_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($cuentas as $cta)
                                    <option value="{{ $cta->id }}" @selected(old('cuenta_id', $cuentaSugeridaId) == $cta->id)>{{ $cta->codigo }} — {{ $cta->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="concepto" value="Concepto / motivo *" />
                        <x-text-input id="concepto" name="concepto" type="text" class="mt-1 block w-full" :value="old('concepto')" maxlength="500" placeholder="Ej. Devolución de mercancía, descuento por pronto pago…" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" x-data="{ monto: {{ (float) old('monto', 0) }}, tasa: {{ (int) old('tasa_itbms', 0) }} }">
                        <div>
                            <x-input-label for="monto" value="Monto base (sin ITBMS) *" />
                            <x-text-input id="monto" name="monto" type="number" step="0.01" min="0.01" class="mt-1 block w-full" x-model.number="monto" :value="old('monto')" />
                        </div>
                        <div>
                            <x-input-label for="tasa_itbms" value="ITBMS" />
                            <select id="tasa_itbms" name="tasa_itbms" x-model.number="tasa" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (\App\Http\Controllers\Admin\CxcNotaController::TASAS_ITBMS as $t)
                                    <option value="{{ $t }}" @selected(old('tasa_itbms', 0) == $t)>{{ $t }}%</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Total" />
                            <div class="mt-1 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                B/. <span x-text="(monto * (1 + tasa/100)).toFixed(2)">0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="{{ route('admin.cxc.notas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Guardar {{ $esCredito ? 'nota de crédito' : 'nota de débito' }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
