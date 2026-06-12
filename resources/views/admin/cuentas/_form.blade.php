@php($q = $cuenta ?? null)

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="codigo" class="block text-sm font-medium text-gray-700">Código</label>
        <input id="codigo" name="codigo" value="{{ old('codigo', $q->codigo ?? '') }}" required placeholder="ej. 10108" class="mt-1 block w-full rounded-md border-gray-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('codigo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
        <input id="nombre" name="nombre" value="{{ old('nombre', $q->nombre ?? '') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="cuenta_padre_id" class="block text-sm font-medium text-gray-700">Cuenta padre</label>
        <select id="cuenta_padre_id" name="cuenta_padre_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">— Sin padre (nivel 1) —</option>
            @foreach ($padres as $padre)
                <option value="{{ $padre->id }}" @selected(old('cuenta_padre_id', $q->cuenta_padre_id ?? null) == $padre->id)>{{ $padre->codigo }} — {{ $padre->nombre }}</option>
            @endforeach
        </select>
        @error('cuenta_padre_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="tipo_cuenta_id" class="block text-sm font-medium text-gray-700">Tipo de cuenta</label>
        <select id="tipo_cuenta_id" name="tipo_cuenta_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($tipos as $tipo)
                <option value="{{ $tipo->id }}" data-naturaleza="{{ $tipo->naturaleza }}" @selected(old('tipo_cuenta_id', $q->tipo_cuenta_id ?? null) == $tipo->id)>{{ $tipo->nombre }}</option>
            @endforeach
        </select>
        @error('tipo_cuenta_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="naturaleza" class="block text-sm font-medium text-gray-700">Naturaleza</label>
        <select id="naturaleza" name="naturaleza" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="DEBITO" @selected(old('naturaleza', $q->naturaleza ?? 'DEBITO') === 'DEBITO')>Débito</option>
            <option value="CREDITO" @selected(old('naturaleza', $q->naturaleza ?? '') === 'CREDITO')>Crédito</option>
        </select>
        @error('naturaleza') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col justify-end gap-2 pb-1">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="permite_movimiento" value="0">
            <input type="checkbox" name="permite_movimiento" value="1" @checked(old('permite_movimiento', $q->permite_movimiento ?? true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Permite movimientos (desmarcar si es cuenta de título)
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="conciliable" value="0">
            <input type="checkbox" name="conciliable" value="1" @checked(old('conciliable', $q->conciliable ?? false)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Conciliable (bancos, tarjetas de crédito, préstamos)
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="activa" value="0">
            <input type="checkbox" name="activa" value="1" @checked(old('activa', $q->activa ?? true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Activa
        </label>
    </div>
</div>

<script>
    // Al elegir tipo, sugerir su naturaleza
    document.getElementById('tipo_cuenta_id').addEventListener('change', function () {
        const nat = this.selectedOptions[0]?.dataset.naturaleza;
        if (nat) document.getElementById('naturaleza').value = nat;
    });
</script>

<div class="mt-6 flex items-center justify-end gap-3 border-t pt-6">
    <a href="{{ route('admin.cuentas.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
    <button class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Guardar</button>
</div>
