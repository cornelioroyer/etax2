<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Roles del sistema</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-700">
                Los roles son <strong>globales</strong>: se definen aquí una sola vez y se asignan a los usuarios
                por compañía desde <em>Accesos por compañía</em>. El nivel <strong>super administrador</strong>
                no es un rol: se otorga con el indicador de administrador en <em>Usuarios</em>.
            </div>

            <div class="flex justify-end">
                <a href="{{ route('admin.roles.create') }}"
                   class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    + Nuevo rol
                </a>
            </div>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Rol</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre técnico</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Permisos</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Usuarios</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($roles as $rol)
                            <tr>
                                <td class="px-4 py-4 sm:px-6">
                                    <div class="font-medium text-gray-900">{{ $rol->etiqueta() }}</div>
                                    @if ($rol->esProtegido())
                                        <span class="mt-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Rol base</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-mono text-xs text-gray-400">{{ $rol->name }}</td>
                                <td class="px-4 py-4 text-center text-sm text-gray-700">{{ $rol->permissions_count }}</td>
                                <td class="px-4 py-4 text-center text-sm text-gray-700">{{ $usuariosPorRol[$rol->id] ?? 0 }}</td>
                                <td class="px-4 py-4 text-right text-sm font-medium sm:px-6">
                                    <a href="{{ route('admin.roles.edit', $rol) }}#permisos" class="mr-4 text-indigo-600 hover:text-indigo-900" title="Asignar o quitar permisos de este rol">Permisos</a>
                                    <a href="{{ route('admin.roles.edit', $rol) }}" class="mr-4 text-indigo-600 hover:text-indigo-900">Editar</a>
                                    @if (! $rol->esProtegido())
                                        <form method="POST" action="{{ route('admin.roles.destroy', $rol) }}" class="inline"
                                              onsubmit="return confirm('¿Eliminar este rol? Solo es posible si no está asignado a ningún usuario.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:text-red-900">Eliminar</button>
                                        </form>
                                    @else
                                        <span class="text-gray-300">Eliminar</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No hay roles definidos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
