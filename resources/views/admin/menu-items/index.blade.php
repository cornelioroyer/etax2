<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Menú del sistema</h2>
            <a href="{{ route('admin.menu-items.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Nueva opción</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-700">
                Estas opciones alimentan el menú lateral. La visibilidad real depende del permiso de cada usuario y la compañía activa. Total: {{ $arbol->count() }} opciones.
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Opción</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Destino / permiso</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Orden</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($arbol as $item)
                            <tr class="{{ $item->activo ? '' : 'bg-gray-50 text-gray-400' }}">
                                <td class="px-6 py-3">
                                    <div class="flex items-center" style="padding-left: {{ $item->_depth * 1.5 }}rem">
                                        @if ($item->_depth > 0)
                                            <span class="mr-2 text-gray-300">└</span>
                                        @endif
                                        <span class="font-medium {{ $item->activo ? 'text-gray-900' : 'text-gray-400' }}">{{ $item->etiqueta }}</span>
                                        @unless ($item->activo)
                                            <span class="ml-2 rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-600">Inactiva</span>
                                        @endunless
                                        @if ($item->solo_admin)
                                            <span class="ml-2 rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-purple-700">Solo admin</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-400" style="padding-left: {{ $item->_depth * 1.5 + ($item->_depth > 0 ? 1.5 : 0) }}rem">{{ $item->clave }}</div>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    @if ($item->ruta_nombre)
                                        <span class="font-mono text-xs">{{ $item->ruta_nombre }}</span>
                                    @elseif ($item->dispatch_evento)
                                        <span class="text-xs italic">evento: {{ $item->dispatch_evento }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">— grupo —</span>
                                    @endif
                                    @if ($item->permiso)
                                        <div class="mt-0.5"><span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">{{ $item->permiso }}</span></div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        @unless ($item->_isFirst)
                                            <form method="POST" action="{{ route('admin.menu-items.mover', $item) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up">
                                                <button class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="Subir">▲</button>
                                            </form>
                                        @endunless
                                        @unless ($item->_isLast)
                                            <form method="POST" action="{{ route('admin.menu-items.mover', $item) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down">
                                                <button class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="Bajar">▼</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-medium">
                                    <a href="{{ route('admin.menu-items.edit', $item) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    <form method="POST" action="{{ route('admin.menu-items.toggle', $item) }}" class="inline">
                                        @csrf
                                        <button class="ms-3 text-gray-600 hover:text-gray-900">{{ $item->activo ? 'Inactivar' : 'Activar' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.menu-items.destroy', $item) }}" class="inline" onsubmit="return confirm('¿Eliminar esta opción del menú?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ms-3 text-red-600 hover:text-red-900">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No hay opciones de menú. <a href="{{ route('admin.menu-items.create') }}" class="text-indigo-600 underline">Crear la primera</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
