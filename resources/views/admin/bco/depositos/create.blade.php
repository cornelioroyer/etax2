<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo depósito</h2>
            <a href="{{ route('admin.bco.depositos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
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

                <form method="POST" action="{{ route('admin.bco.depositos.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta bancaria *</label>
                        <select name="cuenta_bancaria_id" required class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Seleccionar…</option>
                            @foreach ($cuentas as $c)
                                <option value="{{ $c->id }}" @selected(old('cuenta_bancaria_id') == $c->id)>
                                    {{ $c->nombre }} — {{ $c->banco?->nombre }}
                                </option>
                            @endforeach
                        </select>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Referencia / No. depósito</label>
                        <input type="text" name="referencia" value="{{ old('referencia') }}" maxlength="100"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="Ej: DEP-001234">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta contable origen (para asiento)</label>
                        <p class="text-xs text-gray-400 mb-1">Opcional. Si se indica, genera asiento DR Banco / CR esta cuenta (ej. Caja).</p>
                        <input type="number" name="cuenta_origen_id" value="{{ old('cuenta_origen_id') }}"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="ID de cuenta contable">
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.bco.depositos.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancelar</a>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Guardar depósito</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
