@php
    $val = fn (string $campo, $def = '') => old($campo, $item->{$campo} ?? $def);
    $params = old('ruta_params', is_array($item->ruta_params ?? null) ? json_encode($item->ruta_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <label for="etiqueta" class="block text-sm font-medium text-gray-700">Etiqueta <span class="text-red-500">*</span></label>
            <input id="etiqueta" name="etiqueta" value="{{ $val('etiqueta') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-gray-400">Texto visible en el menú.</p>
            @error('etiqueta') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="clave" class="block text-sm font-medium text-gray-700">Clave <span class="text-red-500">*</span></label>
            <input id="clave" name="clave" value="{{ $val('clave') }}" required class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-gray-400">Identificador único, ej. <span class="font-mono">compras.ordenes</span>.</p>
            @error('clave') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="parent_id" class="block text-sm font-medium text-gray-700">Opción padre</label>
            <select id="parent_id" name="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— (raíz) —</option>
                @foreach ($padres as $id => $etiqueta)
                    <option value="{{ $id }}" @selected((string) $val('parent_id') === (string) $id)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            @error('parent_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="modulo" class="block text-sm font-medium text-gray-700">Módulo</label>
            <input id="modulo" name="modulo" value="{{ $val('modulo') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-gray-400">Agrupador opcional, ej. <span class="font-mono">compras</span>.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <label for="ruta_nombre" class="block text-sm font-medium text-gray-700">Nombre de ruta</label>
            <input id="ruta_nombre" name="ruta_nombre" list="lista-rutas" value="{{ $val('ruta_nombre') }}" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <datalist id="lista-rutas">
                @foreach ($rutas as $r)
                    <option value="{{ $r }}"></option>
                @endforeach
            </datalist>
            <p class="mt-1 text-xs text-gray-400">Déjelo vacío si es solo un grupo contenedor.</p>
            @error('ruta_nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="ruta_params" class="block text-sm font-medium text-gray-700">Parámetros de ruta (JSON)</label>
            <input id="ruta_params" name="ruta_params" value="{{ $params }}" placeholder='{"tipo":"PROVEEDOR"}' class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('ruta_params') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="ruta_activa_patron" class="block text-sm font-medium text-gray-700">Patrón de ruta activa</label>
            <input id="ruta_activa_patron" name="ruta_activa_patron" value="{{ $val('ruta_activa_patron') }}" placeholder="admin.compras.ordenes.*" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-gray-400">Resalta la opción. Varios patrones separados por espacio.</p>
        </div>

        <div>
            <label for="dispatch_evento" class="block text-sm font-medium text-gray-700">Evento dispatch (Alpine)</label>
            <input id="dispatch_evento" name="dispatch_evento" value="{{ $val('dispatch_evento') }}" placeholder="open-help" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-gray-400">Para opciones que disparan un evento en vez de navegar.</p>
        </div>

        <div>
            <label for="activa_query_key" class="block text-sm font-medium text-gray-700">Query key activa</label>
            <input id="activa_query_key" name="activa_query_key" value="{{ $val('activa_query_key') }}" placeholder="tipo" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="activa_query_val" class="block text-sm font-medium text-gray-700">Query value activa</label>
            <input id="activa_query_val" name="activa_query_val" value="{{ $val('activa_query_val') }}" placeholder="PROVEEDOR" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="permiso" class="block text-sm font-medium text-gray-700">Permiso</label>
            <input id="permiso" name="permiso" list="lista-permisos" value="{{ $val('permiso') }}" placeholder="compras.ver" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <datalist id="lista-permisos">
                @foreach ($permisos as $p)
                    <option value="{{ $p }}"></option>
                @endforeach
            </datalist>
            <p class="mt-1 text-xs text-gray-400">Vacío = visible para todos los que vean su grupo.</p>
        </div>
    </div>

    <div>
        <label for="icono" class="block text-sm font-medium text-gray-700">Ícono (path SVG)</label>
        <textarea id="icono" name="icono" rows="2" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="M3 12l9-9 9 9...">{{ $val('icono') }}</textarea>
        <p class="mt-1 text-xs text-gray-400">Solo el atributo <span class="font-mono">d</span> del SVG. Relevante en opciones raíz.</p>
    </div>

    <div class="flex flex-wrap gap-6">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="solo_admin" value="0">
            <input type="checkbox" name="solo_admin" value="1" @checked($val('solo_admin', false)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-gray-700">Solo super-admin</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" value="1" @checked($val('activo', true)) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-gray-700">Activa</span>
        </label>
    </div>

    <div class="flex items-center justify-end gap-3 border-t pt-6">
        <a href="{{ route('admin.menu-items.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Guardar</button>
    </div>
</div>
