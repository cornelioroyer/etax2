<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Permisos de {{ $usuario->name }}
            <span class="text-base font-normal text-gray-500">en {{ $compania->nombre }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-700">
                <strong>{{ $usuario->email }}</strong> &mdash;
                Rol: <span class="font-semibold">{{ $rolNombre === 'admin_compania' ? 'Administrador de compañía' : ($rolNombre === 'usuario' ? 'Usuario' : ($rolNombre ? ucfirst(str_replace('_', ' ', $rolNombre)) : 'Sin rol')) }}</span>
                <div class="mt-1 text-blue-600">
                    Cada opción tiene 6 acciones. Las casillas con <span class="font-semibold">fondo gris</span> ya las da el rol:
                    márcalas para <span class="font-semibold text-red-600">denegárselas</span> solo a este usuario. Las casillas
                    en blanco son <span class="font-semibold">extras</span>: márcalas para agregárselas además del rol. Todo aplica solo a esta compañía.
                </div>
            </div>

            <form method="POST" action="{{ route('admin.usuarios-compania.permisos.update', $usuario) }}">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    @foreach ($matriz as $grupo)
                        <div class="rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="bg-gray-100 px-4 py-2 border-b border-gray-200">
                                <h3 class="text-sm font-bold text-gray-700">{{ $grupo['titulo'] }}</h3>
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
                                                <td class="px-4 py-2 text-gray-800">{{ $op['etiqueta'] }}</td>
                                                @foreach ($op['acciones'] as $accion)
                                                    @php
                                                        $delRol   = in_array($accion['name'], $permisosDelRol, true);
                                                        $directo  = in_array($accion['name'], $permisosDirectos, true);
                                                        $denegado = in_array($accion['name'], $permisosDenegados, true);
                                                    @endphp
                                                    <td class="px-3 py-2 text-center {{ $delRol ? ($denegado ? 'bg-red-50' : 'bg-gray-50') : '' }}">
                                                        @if ($accion['reservado'] || ! $accion['id'])
                                                            <span class="text-gray-300">&mdash;</span>
                                                        @elseif ($delRol)
                                                            {{-- Permiso heredado del rol: marcar = denegar a este usuario --}}
                                                            <input type="checkbox" name="denegados[]" value="{{ $accion['id'] }}"
                                                                   @checked($denegado) title="Del rol — marcar para denegar"
                                                                   class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                                        @else
                                                            {{-- Permiso fuera del rol: marcar = agregar como extra --}}
                                                            <input type="checkbox" name="permisos[]" value="{{ $accion['id'] }}"
                                                                   @checked($directo) title="Extra — marcar para agregar"
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
