@php
    $role = $role ?? null;
    $esProtegido = $role && $role->esProtegido();
    $nombreActual = old('name', $role?->name);
    $descripcionActual = old('descripcion', $role?->descripcion);
@endphp

<div class="space-y-6">
    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-lg bg-white p-4 shadow-sm space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Nombre del rol</label>
            @if ($esProtegido)
                <input type="text" value="{{ $role->etiqueta() }}" disabled
                       class="mt-1 block w-full rounded-md border-gray-200 bg-gray-100 text-gray-500 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">Este es un rol base del sistema: su nombre no se puede cambiar.</p>
            @else
                <input type="text" name="name" value="{{ $nombreActual }}" required maxlength="100"
                       placeholder="Ej.: Cajero, Contador, Solo lectura"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-500">Se guarda como clave técnica en minúsculas (ej.: «Cajero General» → <span class="font-mono">cajero_general</span>).</p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Descripción (opcional)</label>
            <input type="text" name="descripcion" value="{{ $descripcionActual }}" maxlength="255"
                   placeholder="Ej.: Registra cobros y maneja la caja"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div id="permisos" class="space-y-4" style="scroll-margin-top: 6rem;">
        <h3 class="text-sm font-semibold text-gray-700">Permisos del rol</h3>
        <p class="text-xs text-gray-500">Marca las acciones que tendrá el rol en cada opción. Las acciones reservadas de plataforma se muestran como «&mdash;».</p>

        @foreach ($matriz as $grupo)
            <div class="rounded-lg bg-white shadow-sm overflow-hidden" x-data="{}">
                <div class="flex items-center justify-between bg-gray-100 px-4 py-2 border-b border-gray-200">
                    <h4 class="text-sm font-bold text-gray-700">{{ $grupo['titulo'] }}</h4>
                    <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800"
                            @click="$root.querySelectorAll('input[type=checkbox]:not(:disabled)').forEach(c => c.checked = true)">Marcar todo</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Opción</th>
                                @foreach (\App\Support\MatrizPermisos::ACCIONES as $etiqueta)
                                    <th class="px-3 py-2 text-center font-medium">{{ $etiqueta }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($grupo['opciones'] as $op)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-gray-800">
                                        <div class="flex items-center justify-between gap-2">
                                            <span>{{ $op['etiqueta'] }}</span>
                                            <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800 whitespace-nowrap"
                                                    @click="(() => { const cbs = $el.closest('tr').querySelectorAll('input[type=checkbox]:not(:disabled)'); const todas = cbs.length && [...cbs].every(c => c.checked); cbs.forEach(c => c.checked = !todas); })()">Todos</button>
                                        </div>
                                    </td>
                                    @foreach ($op['acciones'] as $accion)
                                        <td class="px-3 py-2 text-center">
                                            @if ($accion['reservado'] || ! $accion['id'])
                                                <span class="text-gray-300">&mdash;</span>
                                            @else
                                                <input type="checkbox" name="permisos[]" value="{{ $accion['name'] }}"
                                                       @checked(in_array($accion['name'], $permisosDelRol, true))
                                                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex items-center gap-4">
        <button type="submit" class="rounded-md bg-gray-900 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700">
            {{ $role ? 'Guardar cambios' : 'Crear rol' }}
        </button>
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
    </div>
</div>
