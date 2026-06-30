<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Usuarios de {{ $compania->nombre }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @can('usuarios_compania.gestionar')
            <form method="POST" action="{{ route('admin.usuarios-compania.store') }}" class="rounded-lg bg-white p-4 shadow-sm">
                @csrf
                <h3 class="mb-3 text-sm font-semibold text-gray-900">Dar acceso a un usuario</h3>
                <div class="grid gap-3 md:grid-cols-5">
                    <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <input name="name" value="{{ old('name') }}" placeholder="Nombre (si es nuevo)" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <input name="password" type="password" placeholder="Contraseña (si es nuevo)" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <select name="rol" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($roles as $rol)
                            <option value="{{ $rol->name }}" @selected(old('rol', 'usuario') === $rol->name)>{{ $rol->etiqueta() }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Dar acceso</button>
                </div>
                <p class="mt-2 text-xs text-gray-500">Si el email ya existe en la plataforma, solo se le da acceso a esta compañía. Si no existe, indica nombre y contraseña para crearlo.</p>
            </form>
            @endcan

            @if (auth()->user()->is_admin)
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 shadow-sm">
                <h3 class="mb-1 text-sm font-semibold text-amber-900">Acceso a TODAS las compañías (global)</h3>
                <p class="mb-3 text-xs text-amber-700">Otorga un rol que aplica en todas las compañías, presentes y futuras. Solo super administradores. Úsalo con cuidado: da acceso transversal.</p>
                <form method="POST" action="{{ route('admin.usuarios-compania.global.store') }}">
                    @csrf
                    <div class="grid gap-3 md:grid-cols-4">
                        <input name="email" type="email" value="{{ old('email') }}" placeholder="Email del usuario (ya existente)" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:col-span-2">
                        <select name="rol" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($roles as $rol)
                                <option value="{{ $rol->name }}" @selected(old('rol') === $rol->name)>{{ $rol->etiqueta() }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Dar acceso global</button>
                    </div>
                </form>

                @if ($usuariosGlobales->isNotEmpty())
                    <table class="mt-4 min-w-full divide-y divide-amber-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-amber-700">
                                <th class="py-2 pr-4">Usuario</th>
                                <th class="py-2 pr-4">Rol global</th>
                                <th class="py-2 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            @foreach ($usuariosGlobales as $g)
                                <tr>
                                    <td class="py-2 pr-4"><span class="font-medium text-gray-900">{{ $g->name }}</span> <span class="text-gray-500">({{ $g->email }})</span></td>
                                    <td class="py-2 pr-4">{{ optional($roles->firstWhere('name', $g->rol))->etiqueta() ?? $g->rol }}</td>
                                    <td class="py-2 text-right">
                                        <form method="POST" action="{{ route('admin.usuarios-compania.global.destroy', $g->id) }}" class="inline" onsubmit="return confirm('Quitar el acceso global (todas las compañías) de este usuario?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:text-red-900">Quitar acceso global</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @endif

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Usuario</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Rol en esta compañía</th>
                            <th class="hidden px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 md:table-cell">Estado</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($usuarios as $u)
                            <tr>
                                <td class="px-4 py-4 sm:px-6">
                                    <div class="font-medium text-gray-900">{{ $u->name }}</div>
                                    <div class="max-w-40 truncate text-sm text-gray-500 sm:max-w-none">{{ $u->email }}</div>
                                </td>
                                <td class="px-4 py-4 sm:px-6">
                                    @if ($u->id === auth()->id() || ! auth()->user()->can('usuarios_compania.gestionar'))
                                        <span class="text-sm text-gray-700">{{ optional($roles->firstWhere('name', $u->rol))->etiqueta() ?? $u->rol }}</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.usuarios-compania.update', $u->id) }}" class="inline">
                                            @csrf
                                            @method('PUT')
                                            <select name="rol" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($roles as $rol)
                                                    <option value="{{ $rol->name }}" @selected($u->rol === $rol->name)>{{ $rol->etiqueta() }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                                <td class="hidden px-6 py-4 md:table-cell">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $u->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $u->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right text-sm font-medium sm:px-6">
                                    @if ($u->id !== auth()->id() && auth()->user()->can('usuarios_compania.gestionar'))
                                        <a href="{{ route('admin.usuarios-compania.permisos.edit', $u->id) }}" class="mr-4 text-indigo-600 hover:text-indigo-900">Permisos</a>
                                        <form method="POST" action="{{ route('admin.usuarios-compania.destroy', $u->id) }}" class="inline" onsubmit="return confirm('Quitar el acceso de este usuario a la compañía?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:text-red-900">Quitar acceso</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Ningún usuario tiene acceso a esta compañía todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
