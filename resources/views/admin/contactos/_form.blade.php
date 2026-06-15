@php($c = $contacto ?? null)
@php($tiposActuales = old('tipos', $c?->tipos->pluck('id')->all() ?? collect($tipos)->where('codigo', $tipoPreseleccionado)->pluck('id')->all()))

<div class="space-y-8">
    <div>
        <h3 class="text-base font-semibold text-gray-900 border-b pb-2">Datos generales</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div>
                <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $c->nombre ?? '') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="tipo_persona" class="block text-sm font-medium text-gray-700">Tipo de persona</label>
                <select id="tipo_persona" name="tipo_persona" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="NATURAL" @selected(old('tipo_persona', $c->tipo_persona ?? '') === 'NATURAL')>Natural (N)</option>
                    <option value="JURIDICA" @selected(old('tipo_persona', $c->tipo_persona ?? 'JURIDICA') === 'JURIDICA')>Jurídica (J)</option>
                    <option value="EXTRANJERO" @selected(old('tipo_persona', $c->tipo_persona ?? '') === 'EXTRANJERO')>Extranjero (E)</option>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label for="identificacion" class="block text-sm font-medium text-gray-700">RUC / Cédula</label>
                    <input id="identificacion" name="identificacion" value="{{ old('identificacion', $c->identificacion ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('identificacion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="dv" class="block text-sm font-medium text-gray-700">DV</label>
                    <input id="dv" name="dv" maxlength="5" value="{{ old('dv', $c->dv ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tipos <span class="text-xs text-gray-400">(uno o más)</span></label>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($tipos as $t)
                        <label class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <input type="checkbox" name="tipos[]" value="{{ $t->id }}" @checked(in_array($t->id, $tiposActuales)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $t->nombre }}
                        </label>
                    @endforeach
                </div>
                @error('tipos') <p class="mt-1 text-sm text-red-600">Selecciona al menos un tipo.</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-base font-semibold text-gray-900 border-b pb-2">Contacto</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $c->email ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono</label>
                <input id="telefono" name="telefono" value="{{ old('telefono', $c->telefono ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="md:col-span-2">
                <label for="direccion" class="block text-sm font-medium text-gray-700">Dirección</label>
                <textarea id="direccion" name="direccion" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('direccion', $c->direccion ?? '') }}</textarea>
            </div>

            <div>
                <label for="provincia" class="block text-sm font-medium text-gray-700">Provincia</label>
                <input id="provincia" name="provincia" value="{{ old('provincia', $c->provincia ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="distrito" class="block text-sm font-medium text-gray-700">Distrito</label>
                <input id="distrito" name="distrito" value="{{ old('distrito', $c->distrito ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="md:col-span-2 cuenta-gasto-wrap">
                <label for="cuenta_gasto_id" class="block text-sm font-medium text-gray-700">Cuenta de gasto por defecto <span class="text-xs text-gray-400">(proveedor, opcional)</span></label>
                @php($cuentaGastoSel = old('cuenta_gasto_id', $c->cuenta_gasto_id ?? ($cuentaGastoDefault ?? '')))
                <select id="cuenta_gasto_id" name="cuenta_gasto_id" class="cuenta-gasto-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Usar gasto por defecto de la compañía —</option>
                    @foreach (($cuentas ?? []) as $cuenta)
                        <option value="{{ $cuenta->id }}" @selected((string) $cuentaGastoSel === (string) $cuenta->id)>{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Se preselecciona en las líneas al registrar facturas por pagar de este proveedor.</p>
                @error('cuenta_gasto_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="activo" class="block text-sm font-medium text-gray-700">Estado</label>
                <select id="activo" name="activo" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="1" @selected(old('activo', ($c->activo ?? true) ? '1' : '0') === '1')>Activo</option>
                    <option value="0" @selected(old('activo', ($c->activo ?? true) ? '1' : '0') === '0')>Inactivo</option>
                </select>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 border-t pt-6">
        <a href="{{ route('admin.contactos.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
        <button class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Guardar</button>
    </div>
</div>

{{-- Selector de cuenta de gasto con búsqueda (TomSelect) --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.bootstrap5.min.css">
<style>
    .cuenta-gasto-wrap .ts-wrapper { width: 100%; }
    .cuenta-gasto-wrap .ts-control {
        min-height: 42px; border-radius: 0.375rem; border-color: #d1d5db;
        box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); padding: 0.375rem 0.75rem;
    }
    .cuenta-gasto-wrap .ts-dropdown {
        background-color: #fff; border: 1px solid #d1d5db; border-radius: 0.375rem;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
        z-index: 60; margin-top: 2px;
    }
    .cuenta-gasto-wrap .ts-dropdown .option { padding: 0.5rem 0.75rem; }
    .cuenta-gasto-wrap .ts-dropdown .option.active { background-color: #005293; color: #fff; }
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js"></script>
<script>
    document.querySelectorAll('.cuenta-gasto-select').forEach(function (el) {
        new TomSelect(el, {
            allowEmptyOption: true,
            placeholder: '— Buscar cuenta por código o nombre —',
            searchField: ['text'],
            maxOptions: 300,
        });
    });
</script>
