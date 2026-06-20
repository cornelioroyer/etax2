<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo cheque</h2>
            <a href="{{ route('admin.bco.cheques.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.bco.cheques.store') }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta bancaria *</label>
                            <select name="cuenta_bancaria_id" required class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Seleccionar…</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_bancaria_id') == $c->id)>{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. Cheque *</label>
                            <input type="text" name="numero_cheque" value="{{ old('numero_cheque') }}" required maxlength="50"
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="Ej: 0001234">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
                            <input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Monto *</label>
                            <input type="number" name="monto" value="{{ old('monto') }}" step="0.01" min="0.01" required
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="0.00">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Beneficiario</label>
                        <select name="beneficiario_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">Sin asignar</option>
                            @foreach ($contactos as $ct)
                                <option value="{{ $ct->id }}" @selected(old('beneficiario_id') == $ct->id)>{{ $ct->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta contable (contrapartida)
                            <span class="font-normal text-gray-400">— gasto o pasivo que se debita</span>
                        </label>
                        <select name="cuenta_contable_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">Sin asiento contable</option>
                            @foreach ($cuentasContables as $c)
                                <option value="{{ $c->id }}" @selected(old('cuenta_contable_id') == $c->id)>{{ $c->codigo }} — {{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Si se selecciona, se genera el asiento Dr Contrapartida / Cr Banco. Requiere que la cuenta bancaria tenga cuenta GL configurada.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.bco.cheques.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancelar</a>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Emitir cheque</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
