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
                <span class="text-blue-500">
                    Los permisos con fondo gris vienen del rol. Marca la casilla para <strong>denegárselos</strong> solo a este usuario
                    (sin afectar a los demás ni cambiar el rol). Los permisos sin fondo son <strong>extras</strong>: márcalos para agregárselos
                    además de su rol. Todo aplica únicamente a esta compañía.
                </span>
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
                                        $denegado = in_array($permiso->name, $permisosDenegados);
                                        $sufijo   = implode('.', array_slice(explode('.', $permiso->name), 1));
                                        $etiqueta = $accionEtiqueta[$sufijo] ?? ucfirst(str_replace('.', ' ', $sufijo));
                                    @endphp
                                    @if ($delRol)
                                        {{-- Permiso heredado del rol: se puede DENEGAR puntualmente a este usuario --}}
                                        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer {{ $denegado ? 'bg-red-50' : 'bg-gray-50' }}">
                                            <input
                                                type="checkbox"
                                                name="denegados[]"
                                                value="{{ $permiso->id }}"
                                                @checked($denegado)
                                                class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                            >
                                            <span class="text-sm {{ $denegado ? 'text-red-600 line-through' : 'text-gray-700' }}">
                                                {{ $etiqueta }}
                                                @if ($denegado)
                                                    <span class="ml-1 text-xs text-red-500 no-underline">(denegado a este usuario)</span>
                                                @else
                                                    <span class="ml-1 text-xs text-gray-400">(del rol — marcar para denegar)</span>
                                                @endif
                                            </span>
                                            <span class="ml-auto text-xs text-gray-300 font-mono">{{ $permiso->name }}</span>
                                        </label>
                                    @else
                                        {{-- Permiso fuera del rol: se puede AGREGAR como extra --}}
                                        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-indigo-50">
                                            <input
                                                type="checkbox"
                                                name="permisos[]"
                                                value="{{ $permiso->id }}"
                                                @checked($directo)
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            >
                                            <span class="text-sm text-gray-800">
                                                {{ $etiqueta }}
                                                @if ($directo)
                                                    <span class="ml-1 text-xs text-indigo-500">(extra)</span>
                                                @endif
                                            </span>
                                            <span class="ml-auto text-xs text-gray-300 font-mono">{{ $permiso->name }}</span>
                                        </label>
                                    @endif
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
