<div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-5">
    {{-- Identificación --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-gray-700">Código <span class="text-red-500">*</span></label>
            <input type="text" name="codigo" value="{{ old('codigo', $item->codigo ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm uppercase" required>
            @error('codigo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" value="{{ old('nombre', $item->nombre ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" required>
            @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Descripción</label>
        <textarea name="descripcion" rows="2"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">{{ old('descripcion', $item->descripcion ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-gray-700">Tipo <span class="text-red-500">*</span></label>
            <select name="tipo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm" required>
                <option value="PRODUCTO" @selected(old('tipo', $item->tipo ?? '') === 'PRODUCTO')>Producto</option>
                <option value="SERVICIO" @selected(old('tipo', $item->tipo ?? '') === 'SERVICIO')>Servicio</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Categoría</label>
            <select name="categoria_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                <option value="">Sin categoría</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat->id }}" @selected(old('categoria_id', $item->categoria_id ?? '') == $cat->id)>{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Unidad de medida</label>
            <select name="unidad_medida_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                <option value="">Unidad</option>
                @foreach ($unidades as $u)
                    <option value="{{ $u->id }}" @selected(old('unidad_medida_id', $item->unidad_medida_id ?? '') == $u->id)>{{ $u->codigo }} — {{ $u->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Precios e impuesto --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-gray-700">Precio de venta</label>
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 text-sm">B/.</span>
                <input type="number" name="precio_venta" value="{{ old('precio_venta', isset($item) ? number_format((float) $item->precio_venta, 2, '.', '') : '') }}"
                    step="0.0001" min="0" class="block w-full rounded-md border-gray-300 pl-9 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Costo</label>
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 text-sm">B/.</span>
                <input type="number" name="costo" value="{{ old('costo', isset($item) ? number_format((float) $item->costo, 2, '.', '') : '') }}"
                    step="0.0001" min="0" class="block w-full rounded-md border-gray-300 pl-9 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">ITBMS por defecto</label>
            <select name="impuesto_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                <option value="">Exento</option>
                @foreach ($impuestos as $imp)
                    <option value="{{ $imp->id }}" @selected(old('impuesto_id', $item->impuesto_id ?? '') == $imp->id)>{{ $imp->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Cuentas contables --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Cuenta de ingreso</label>
            <select name="cuenta_ingreso_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                <option value="">Sin cuenta</option>
                @foreach ($cuentas as $c)
                    <option value="{{ $c->id }}" @selected(old('cuenta_ingreso_id', $item->cuenta_ingreso_id ?? '') == $c->id)>{{ $c->codigo }} — {{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Cuenta de gasto/costo</label>
            <select name="cuenta_gasto_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 text-sm">
                <option value="">Sin cuenta</option>
                @foreach ($cuentas as $c)
                    <option value="{{ $c->id }}" @selected(old('cuenta_gasto_id', $item->cuenta_gasto_id ?? '') == $c->id)>{{ $c->codigo }} — {{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
