<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Permisos del rol
            <span class="text-base font-normal text-gray-500">{{ $role->etiqueta() }}</span>
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
                <span class="font-mono">{{ $role->name }}</span> &mdash;
                Marca las acciones que este rol puede ejecutar en cada opción. Los permisos
                son <strong>globales</strong>: el cambio aplica a todos los usuarios que tengan
                este rol en cualquier compañía. Las casillas con <span class="font-semibold">&mdash;</span>
                son acciones reservadas de plataforma y no se pueden otorgar aquí.
            </div>

            @if (! empty($otrosPermisos))
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    Este rol tiene <strong>{{ count($otrosPermisos) }}</strong> permiso(s) que no se
                    administran en esta matriz (permisos de módulo heredados u otros). <strong>No se modifican</strong>
                    al guardar aquí:
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($otrosPermisos as $p)
                            <span class="inline-flex rounded bg-amber-100 px-2 py-0.5 font-mono text-xs">{{ $p }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.roles.permisos.update', $role) }}">
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
                                            <th class="px-3 py-2 text-center font-medium">Todos</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($grupo['opciones'] as $op)
                                            <tr class="hover:bg-gray-50" data-fila-permisos>
                                                <td class="px-4 py-2 text-gray-800">{{ $op['etiqueta'] }}</td>
                                                @foreach ($op['acciones'] as $accion)
                                                    @php
                                                        $tiene = in_array($accion['name'], $permisosDelRol, true);
                                                    @endphp
                                                    <td class="px-3 py-2 text-center">
                                                        @if ($accion['reservado'] || ! $accion['id'])
                                                            <span class="text-gray-300">&mdash;</span>
                                                        @else
                                                            <input type="checkbox" name="permisos[]" value="{{ $accion['name'] }}"
                                                                   @checked($tiene)
                                                                   data-permiso
                                                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox" data-todos
                                                           title="Marcar/desmarcar todas las acciones de esta fila"
                                                           class="h-4 w-4 rounded border-gray-400 text-indigo-600 focus:ring-indigo-500">
                                                </td>
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
                    <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Cancelar
                    </a>
                </div>
            </form>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('tr[data-fila-permisos]').forEach(function (fila) {
                var todos  = fila.querySelector('input[data-todos]');
                var checks = fila.querySelectorAll('input[data-permiso]');

                if (! todos) {
                    return;
                }

                // Si la fila no tiene acciones marcables, deshabilita el "Todos".
                if (checks.length === 0) {
                    todos.disabled = true;
                    return;
                }

                function sincronizarTodos() {
                    var marcadas = Array.prototype.filter.call(checks, function (c) { return c.checked; }).length;
                    todos.checked       = marcadas === checks.length;
                    todos.indeterminate = marcadas > 0 && marcadas < checks.length;
                }

                todos.addEventListener('change', function () {
                    checks.forEach(function (c) { c.checked = todos.checked; });
                    todos.indeterminate = false;
                });

                checks.forEach(function (c) {
                    c.addEventListener('change', sincronizarTodos);
                });

                sincronizarTodos();
            });
        });
    </script>
</x-app-layout>
