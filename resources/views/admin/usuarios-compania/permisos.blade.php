<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Permisos de {{ $usuario->name }}
            <span class="text-base font-normal text-gray-500">en {{ $compania->nombre }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-700">
                <strong>{{ $usuario->email }}</strong> &mdash;
                Rol: <span class="font-semibold">{{ $rolNombre === 'admin_compania' ? 'Administrador de compañía' : ($rolNombre === 'usuario' ? 'Usuario' : ($rolNombre ? ucfirst(str_replace('_', ' ', $rolNombre)) : 'Sin rol')) }}</span>
                <br>
                <span class="text-blue-500">Los permisos marcados con fondo gris vienen del rol y no se pueden quitar aquí. Los permisos adicionales se guardan individualmente.</span>
            </div>

            <form method="POST" action="{{ route('admin.usuarios-compania.permisos.update', $usuario) }}">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    @php
                        $etiquetas = [
                            'activos'           => 'Activos',
                            'bancos'            => 'Bancos',
                            'companias'         => 'Compañías',
                            'compras'           => 'Compras',
                            'contabilidad'      => 'Contabilidad',
                            'contactos'         => 'Contactos',
                            'cxc'               => 'Cuentas por Cobrar (CxC)',
                            'cxp'               => 'Cuentas por Pagar (CxP)',
                            'ia'                => 'Inteligencia Artificial',
                            'inventario'        => 'Inventario',
                            'reportes'          => 'Reportes',
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
                    @endphp

                    @foreach ($grupos as $modulo => $permisos)
                        <div class="rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-700">{{ $etiquetas[$modulo] ?? ucfirst($modulo) }}</h3>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach ($permisos as $permiso)
                                    @php
                                        $delRol   = in_array($permiso->name, $permisosDelRol);
                                        $directo  = in_array($permiso->name, $permisosDirectos);
                                        $checked  = $delRol || $directo;
                                        $sufijo   = implode('.', array_slice(explode('.', $permiso->name), 1));
                                        $etiqueta = $accionEtiqueta[$sufijo] ?? ucfirst(str_replace('.', ' ', $sufijo));
                                    @endphp
                                    <label class="flex items-center gap-3 px-4 py-3 cursor-pointer {{ $delRol ? 'bg-gray-50' : 'hover:bg-indigo-50' }}">
                                        <input
                                            type="checkbox"
                                            name="permisos[]"
                                            value="{{ $permiso->id }}"
                                            @checked($checked)
                                            @disabled($delRol)
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50"
                                        >
                                        <span class="text-sm {{ $delRol ? 'text-gray-400' : 'text-gray-800' }}">
                                            {{ $etiqueta }}
                                            @if ($delRol)
                                                <span class="ml-1 text-xs text-gray-400">(del rol)</span>
                                            @elseif ($directo)
                                                <span class="ml-1 text-xs text-indigo-500">(extra)</span>
                                            @endif
                                        </span>
                                        <span class="ml-auto text-xs text-gray-300 font-mono">{{ $permiso->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button type="submit" class="rounded-md bg-gray-900 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                        Guardar permisos
                    </button>
                    <a href="{{ route('admin.usuarios-compania.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Cancelar
                    </a>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
