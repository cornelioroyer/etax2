<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo movimiento bancario</h2>
            <a href="{{ route('admin.bco.movimientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.bco.movimientos.store') }}">
                @csrf
                <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Cuenta bancaria <span class="text-red-500">*</span></label>
                            <select name="cuenta_bancaria_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                <option value="">Seleccionar…</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_bancaria_id', request('cuenta_id')) == $c->id)>
                                        {{ $c->banco?->nombre }} — {{ $c->nombre }} ({{ $c->numero_cuenta }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha <span class="text-red-500">*</span></label>
                            <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo <span class="text-red-500">*</span></label>
                            <select name="tipo_movimiento" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                @foreach (\App\Models\BcoMovimiento::TIPOS as $k => $v)
                                    <option value="{{ $k }}" @selected(old('tipo_movimiento') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Descripción <span class="text-red-500">*</span></label>
                        <input type="text" name="descripcion" value="{{ old('descripcion') }}" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Referencia</label>
                            <input type="text" name="referencia" value="{{ old('referencia') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" placeholder="No. cheque, TRF…">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Débito (salida)</label>
                            <input type="number" name="debito" value="{{ old('debito', 0) }}" step="0.01" min="0"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm text-right">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Crédito (entrada)</label>
                            <input type="number" name="credito" value="{{ old('credito', 0) }}" step="0.01" min="0"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm text-right">
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contacto (opcional)</label>
                            <select name="contacto_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                <option value="">—</option>
                                @foreach ($contactos as $c)
                                    <option value="{{ $c->id }}" @selected(old('contacto_id') == $c->id)>{{ $c->codigo }} — {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cuenta contable contrapartida</label>
                            <select name="cuenta_contable_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                                <option value="">— Sin asiento automático —</option>
                                @foreach ($cuentasContables as $cc)
                                    <option value="{{ $cc->id }}" @selected(old('cuenta_contable_id') == $cc->id)>{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Si se selecciona, se genera asiento automático.</p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800">Registrar movimiento</button>
                    <a href="{{ route('admin.bco.movimientos.index') }}" class="rounded-md border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
