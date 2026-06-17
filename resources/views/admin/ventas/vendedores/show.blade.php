<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $vendedor->nombre ?? $vendedor->contacto?->nombre ?? $vendedor->codigo }}</h2>
            <a href="{{ route('admin.ventas.vendedores.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Código</span>
                        <p class="font-mono font-bold">{{ $vendedor->codigo }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Estado</span>
                        <p class="{{ $vendedor->activo ? 'text-green-700 font-medium' : 'text-gray-400' }}">{{ $vendedor->activo ? 'Activo' : 'Inactivo' }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.ventas.vendedores.update', $vendedor) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="nombre" class="block text-sm text-gray-500 mb-1">Nombre</label>
                        <input type="text" name="nombre" id="nombre" maxlength="200"
                               value="{{ old('nombre', $vendedor->nombre) }}"
                               placeholder="{{ $vendedor->contacto?->nombre ?? $vendedor->codigo }}"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-400">Si lo dejas vacío se usará el nombre del contacto vinculado.</p>
                    </div>
                    <div>
                        <label for="contacto_id" class="block text-sm text-gray-500 mb-1">Contacto</label>
                        <select name="contacto_id" id="contacto_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">— Sin contacto —</option>
                            @foreach ($contactos as $c)
                                <option value="{{ $c->id }}" @selected($vendedor->contacto_id == $c->id)>{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">Guardar</button>
                </form>

                <form method="POST" action="{{ route('admin.ventas.vendedores.toggle', $vendedor) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md border {{ $vendedor->activo ? 'border-red-300 text-red-700 hover:bg-red-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                        {{ $vendedor->activo ? 'Desactivar' : 'Activar' }}
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Comisiones</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Factura</th>
                            <th class="px-4 py-3 text-right">%</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($comisiones as $c)
                            <tr>
                                <td class="px-4 py-3 font-mono">{{ $c->factura?->numero ?? "#{$c->factura_id}" }}</td>
                                <td class="px-4 py-3 text-right">{{ $c->porcentaje }}%</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format((float)$c->monto, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-medium {{ $c->estado === 'PAGADA' ? 'text-green-700' : ($c->estado === 'ANULADA' ? 'text-red-600' : 'text-yellow-600') }}">{{ $c->estado }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin comisiones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $comisiones->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
