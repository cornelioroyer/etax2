<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo vendedor</h2>
            <a href="{{ route('admin.ventas.vendedores.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-lg mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.ventas.vendedores.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código *</label>
                        <input type="text" name="codigo" value="{{ old('codigo') }}" required maxlength="30"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm uppercase" placeholder="Ej: VEND01">
                    </div>
                    <div>
                        <x-buscador-contacto name="contacto_id" label="Contacto (opcional)"
                            :opciones="$contactos" :selected="old('contacto_id')"
                            placeholder="Buscar por nombre o código" empty-label="Sin asignar" mostrar-ruc />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.ventas.vendedores.index') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancelar</a>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Crear vendedor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
