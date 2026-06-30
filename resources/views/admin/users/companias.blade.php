<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Compañías del usuario &mdash; {{ $user->name }}
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
                Aquí defines a qué compañías tiene acceso el usuario. Al <span class="font-semibold">agregar</span> una compañía se le da el rol base <span class="font-semibold">«Usuario»</span>; para más permisos, ajústalos en
                <a href="{{ route('admin.users.roles', $user) }}" class="font-semibold underline">Roles</a>.
            </div>

            @if ($user->is_admin)
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    Este usuario es <span class="font-semibold">Super administrador</span>: tiene acceso a <span class="font-semibold">todas</span> las compañías, independientemente de la lista de abajo.
                </div>
            @elseif ($tieneAsignacionGlobal)
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    Este usuario tiene una <span class="font-semibold">asignación global</span> (rol en todas las compañías). Accede a todas las empresas, presentes y futuras.
                </div>
            @endif

            {{-- Alta: dar acceso a una compañía donde aún no lo tiene --}}
            @if ($companiasDisponibles->isNotEmpty())
                <form method="POST" action="{{ route('admin.users.companias.agregar', $user) }}" class="rounded-lg bg-white p-4 shadow-sm">
                    @csrf
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">Agregar compañía</h3>
                    <div class="grid gap-3 md:grid-cols-3">
                        <select name="compania_id" required class="md:col-span-2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Selecciona una compañía —</option>
                            @foreach ($companiasDisponibles as $c)
                                <option value="{{ $c->id }}" @selected(old('compania_id') == $c->id)>{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Agregar</button>
                    </div>
                </form>
            @endif

            {{-- Compañías con acceso --}}
            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Compañía</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($companiasConAcceso as $fila)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $fila->compania }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <a href="{{ route('admin.users.roles', $user) }}" class="text-sky-600 hover:text-sky-900" title="Ver/ajustar roles de esta compañía">Roles</a>
                                    <form method="POST" action="{{ route('admin.users.companias.quitar', [$user, $fila->compania_id]) }}" class="inline" onsubmit="return confirm('¿Quitar el acceso de este usuario a {{ $fila->compania }}? Se eliminarán todos sus roles en esa compañía.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ms-3 text-red-600 hover:text-red-900">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-6 py-8 text-center text-gray-500">Este usuario no tiene acceso a ninguna compañía.</td></tr>
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
