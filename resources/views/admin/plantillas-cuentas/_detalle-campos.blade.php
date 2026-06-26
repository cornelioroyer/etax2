@php($d = $detalle ?? null)
@php($claves = $claves ?? [])
<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Código <span class="text-red-500">*</span></label>
            <input type="text" name="codigo" value="{{ old('codigo', $d->codigo ?? '') }}" required maxlength="50"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="sm:col-span-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" value="{{ old('nombre', $d->nombre ?? '') }}" required maxlength="200"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta padre</label>
            <select name="codigo_padre" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">— Sin padre (nivel 1) —</option>
                @foreach ($padres as $p)
                    <option value="{{ $p->codigo }}" @selected(old('codigo_padre', $d->codigo_padre ?? '') === $p->codigo)>{{ $p->codigo }} — {{ $p->nombre }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-slate-400">El nivel se calcula a partir del padre.</p>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de cuenta <span class="text-red-500">*</span></label>
            <select name="tipo_cuenta_codigo" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                @foreach ($tipos as $t)
                    <option value="{{ $t->codigo }}"
                            data-naturaleza="{{ $t->naturaleza }}"
                            @selected(old('tipo_cuenta_codigo', $d->tipo_cuenta_codigo ?? '') === $t->codigo)>{{ $t->codigo }} ({{ $t->naturaleza === 'DEBITO' ? 'Débito' : 'Crédito' }})</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Naturaleza <span class="text-red-500">*</span></label>
            <select name="naturaleza" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                @foreach (['DEBITO' => 'Débito', 'CREDITO' => 'Crédito'] as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('naturaleza', $d->naturaleza ?? 'DEBITO') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-slate-400">Usa la contraria al tipo para contra-cuentas (ej. depreciación acumulada).</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Clave por defecto</label>
            <input type="text" name="clave_default" value="{{ old('clave_default', $d->clave_default ?? '') }}" maxlength="100" list="claves-default"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono uppercase focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="ej. CXC, VENTAS, BANCO_DEFAULT">
            <datalist id="claves-default">
                @foreach ($claves as $c)
                    <option value="{{ $c }}"></option>
                @endforeach
            </datalist>
            <p class="mt-1 text-[11px] text-slate-400">Mapea esta cuenta a una clave de cuenta por defecto. Vacío = ninguna.</p>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Renglón ISR</label>
            <input type="number" name="renglon_isr" value="{{ old('renglon_isr', $d->renglon_isr ?? '') }}" min="0"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="Formulario 2 DGI">
        </div>
        <div class="sm:col-span-2 flex flex-col justify-center gap-2 pt-5">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="permite_movimiento" value="0">
                <input type="checkbox" name="permite_movimiento" value="1" {{ old('permite_movimiento', $d->permite_movimiento ?? true) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-[#0d2d5e] focus:ring-blue-500">
                Permite movimiento (cuenta hoja)
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="conciliable" value="0">
                <input type="checkbox" name="conciliable" value="1" {{ old('conciliable', $d->conciliable ?? false) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-[#0d2d5e] focus:ring-blue-500">
                Conciliable (bancos)
            </label>
        </div>
    </div>
</div>
