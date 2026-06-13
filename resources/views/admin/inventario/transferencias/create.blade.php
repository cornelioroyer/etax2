<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva transferencia</h2>
            <a href="{{ route('admin.inventario.transferencias.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.inventario.transferencias.store') }}" x-data="{ lineas: [{ item_id: '', cantidad: '', costo: '' }] }">
                    @csrf

                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Almacén origen *</label>
                            <select name="almacen_origen_id" required class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">Seleccionar…</option>
                                @foreach ($almacenes as $al)
                                    <option value="{{ $al->id }}" @selected(old('almacen_origen_id') == $al->id)>{{ $al->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Almacén destino *</label>
                            <select name="almacen_destino_id" required class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">Seleccionar…</option>
                                @foreach ($almacenes as $al)
                                    <option value="{{ $al->id }}" @selected(old('almacen_destino_id') == $al->id)>{{ $al->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
                            <input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-700">Productos a transferir</h3>
                            <button type="button" @click="lineas.push({ item_id: '', cantidad: '', costo: '' })"
                                    class="text-xs text-blue-600 hover:underline">+ Agregar línea</button>
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2">Producto</th>
                                    <th class="px-3 py-2 w-32">Cantidad</th>
                                    <th class="px-3 py-2 w-36">Costo unitario</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(linea, idx) in lineas" :key="idx">
                                    <tr class="border-b border-gray-100">
                                        <td class="px-2 py-2">
                                            <select :name="`items[${idx}][item_id]`" x-model="linea.item_id" required class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                                                <option value="">Seleccionar…</option>
                                                @foreach ($items as $it)
                                                    <option value="{{ $it->id }}">{{ $it->codigo }} – {{ $it->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${idx}][cantidad]`" x-model="linea.cantidad"
                                                   step="0.0001" min="0.0001" required placeholder="0"
                                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm text-right">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${idx}][costo]`" x-model="linea.costo"
                                                   step="0.0001" min="0" placeholder="0.00"
                                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm text-right">
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="if(lineas.length > 1) lineas.splice(idx, 1)"
                                                    class="text-red-400 hover:text-red-600 text-xs">✕</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.inventario.transferencias.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancelar</a>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Aplicar transferencia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
