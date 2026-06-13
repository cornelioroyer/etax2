<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva transferencia entre cuentas</h2>
            <a href="{{ route('admin.bco.transferencias.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.bco.transferencias.store') }}">
                @csrf
                <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cuenta origen <span class="text-red-500">*</span></label>
                        <select name="cuenta_origen_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                            <option value="">Seleccionar…</option>
                            @foreach ($cuentas as $c)
                                <option value="{{ $c->id }}" @selected(old('cuenta_origen_id') == $c->id)>{{ $c->banco?->nombre }} — {{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cuenta destino <span class="text-red-500">*</span></label>
                        <select name="cuenta_destino_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                            <option value="">Seleccionar…</option>
                            @foreach ($cuentas as $c)
                                <option value="{{ $c->id }}" @selected(old('cuenta_destino_id') == $c->id)>{{ $c->banco?->nombre }} — {{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha <span class="text-red-500">*</span></label>
                            <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Monto (B/.) <span class="text-red-500">*</span></label>
                            <input type="number" name="monto" value="{{ old('monto') }}" step="0.01" min="0.01"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm text-right" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Referencia (opcional)</label>
                        <input type="text" name="referencia" value="{{ old('referencia') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                    </div>
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800">Registrar transferencia</button>
                    <a href="{{ route('admin.bco.transferencias.index') }}" class="rounded-md border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
