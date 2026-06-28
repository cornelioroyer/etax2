@php
    $role = $role ?? null;
    $esProtegido = $role && $role->esProtegido();
    $nombreActual = old('name', $role?->name);
    $descripcionActual = old('descripcion', $role?->descripcion);

    $etiquetas = [
        'activos'           => 'Activos',
        'bancos'            => 'Bancos',
        'caja'              => 'Caja',
        'companias'         => 'Compañías',
        'compras'           => 'Compras',
        'contabilidad'      => 'Contabilidad',
        'contactos'         => 'Contactos',
        'cxc'               => 'Cuentas por Cobrar (CxC)',
        'cxp'               => 'Cuentas por Pagar (CxP)',
        'dimensiones'       => 'Dimensiones',
        'edu'               => 'Educación',
        'fel'               => 'Facturación Electrónica',
        'ia'                => 'Inteligencia Artificial',
        'inventario'        => 'Inventario',
        'ph'                => 'Propiedad Horizontal',
        'presupuestos'      => 'Presupuestos',
        'reportes'          => 'Reportes',
        'respaldos'         => 'Respaldos',
        'seguridad'         => 'Seguridad',
        'taller'            => 'Taller',
        'usuarios_compania' => 'Usuarios de compañía',
        'ventas'            => 'Ventas',
        'zonas'             => 'Zonas',
    ];
    $accionEtiqueta = [
        'ver'                      => 'Ver',
        'crear'                    => 'Crear',
        'editar'                   => 'Editar',
        'eliminar'                 => 'Eliminar',
        'gestionar'                => 'Gestionar (crear, editar, anular)',
        'campo.facturacion_fiscal' => 'Campo: Facturación Fiscal',
    ];
    // Etiquetas por permiso completo: desambiguan las filas dentro de grupos que
    // reúnen varios prefijos (p. ej. "Seguridad" mezcla usuarios_compania y respaldos).
    $permisoEtiqueta = [
        'usuarios_compania.ver'       => 'Usuarios de compañía: ver',
        'usuarios_compania.gestionar' => 'Usuarios de compañía: gestionar (crear, editar, anular)',
        'respaldos.gestionar'         => 'Respaldos: gestionar',
    ];
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

    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-gray-700">Permisos del rol</h3>

        @foreach ($grupos as $modulo => $permisos)
            <div class="rounded-lg bg-white shadow-sm overflow-hidden" x-data="{}">
                <div class="flex items-center justify-between bg-gray-50 px-4 py-2 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700">{{ $etiquetas[$modulo] ?? ucfirst($modulo) }}</h4>
                    <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800"
                            @click="$root.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = true)">Marcar todo</button>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($permisos as $permiso)
                        @php
                            $checked  = in_array($permiso->name, $permisosDelRol, true);
                            $sufijo   = implode('.', array_slice(explode('.', $permiso->name), 1));
                            $etiqueta = $permisoEtiqueta[$permiso->name]
                                ?? $accionEtiqueta[$sufijo]
                                ?? ucfirst(str_replace('.', ' ', $sufijo));
                        @endphp
                        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-indigo-50">
                            <input type="checkbox" name="permisos[]" value="{{ $permiso->name }}" @checked($checked)
                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-800">{{ $etiqueta }}</span>
                            <span class="ml-auto text-xs text-gray-300 font-mono">{{ $permiso->name }}</span>
                        </label>
                    @endforeach
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
