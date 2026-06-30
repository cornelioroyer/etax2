@php
    $nombreRol = function (?string $rol) {
        if (! $rol) {
            return 'Sin rol';
        }
        return match ($rol) {
            'admin_compania' => 'Administrador de compañía',
            'usuario' => 'Usuario',
            default => ucfirst(str_replace('_', ' ', $rol)),
        };
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Roles y compañías &mdash; {{ $user->name }}
            <span class="text-base font-normal text-gray-500">{{ $user->email }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-700">
                Acceso de plataforma:
                <span class="font-semibold">{{ $user->is_admin ? 'Super administrador' : 'Usuario' }}</span>
                &mdash; Estado: <span class="font-semibold">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span>.
                <div class="mt-1 text-blue-600">El rol real es <span class="font-semibold">por compañía</span>: un usuario puede tener un rol distinto en cada empresa.</div>
            </div>

            {{-- Roles globales (aplican a todas las compañías) --}}
            @if ($rolesGlobales->isNotEmpty())
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    <div class="font-semibold">Roles globales (todas las compañías)</div>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($rolesGlobales as $rol)
                            <li>{{ $nombreRol($rol) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Alta: dar acceso (rol) en una compañía donde aún no lo tiene --}}
            @if ($companiasDisponibles->isNotEmpty())
                <form method="POST" action="{{ route('admin.users.roles.asignar', $user) }}" class="rounded-lg bg-white p-4 shadow-sm">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">Dar acceso a una compañía</h3>
                    <div class="grid gap-3 md:grid-cols-3">
                        <select name="compania_id" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Compañía —</option>
                            @foreach ($companiasDisponibles as $c)
                                <option value="{{ $c->id }}" @selected(old('compania_id') == $c->id)>{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        <select name="rol" required class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($rolesAsignables as $rol)
                                <option value="{{ $rol->name }}" @selected(old('rol', 'usuario') === $rol->name)>{{ $rol->etiqueta() }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Dar acceso</button>
                    </div>
                </form>
            @endif

            {{-- Roles por compañía --}}
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Compañía</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rol</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($rolesPorCompania as $fila)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $fila->compania }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <form method="POST" action="{{ route('admin.users.roles.asignar', $user) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="compania_id" value="{{ $fila->compania_id }}">
                                        <select name="rol" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach ($rolesAsignables as $rol)
                                                <option value="{{ $rol->name }}" @selected($fila->rol === $rol->name)>{{ $rol->etiqueta() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <form method="POST" action="{{ route('admin.users.roles.quitar', [$user, $fila->compania_id]) }}" class="inline" onsubmit="return confirm('¿Quitar el acceso de este usuario a {{ $fila->compania }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:text-red-900">Quitar acceso</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">Este usuario no tiene roles asignados en ninguna compañía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Volver a usuarios</a>
            </div>
        </div>
    </div>
</x-app-layout>
