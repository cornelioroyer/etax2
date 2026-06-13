<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar presupuesto {{ $presupuesto->numero }}</h2>
            <a href="{{ route('admin.taller.presupuestos.show', $presupuesto) }}" class="text-sm text-gray-500 hover:text-gray-900">← Ver presupuesto</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 mb-4">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.taller.presupuestos.update', $presupuesto) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Taller</label>
                        <p class="text-sm text-gray-600 bg-gray-50 rounded-md px-3 py-2">{{ $presupuesto->taller?->nombre ?? '—' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID del cliente</label>
                        <x-text-input type="number" name="cliente_id" class="w-full" min="1"
                            placeholder="ID numérico del cliente" :value="old('cliente_id', $presupuesto->cliente_id)" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID del equipo (opcional)</label>
                        <x-text-input type="number" name="equipo_id" class="w-full" min="1"
                            placeholder="ID numérico del equipo" :value="old('equipo_id', $presupuesto->equipo_id)" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="descripcion" rows="3"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Descripción del presupuesto...">{{ old('descripcion', $presupuesto->descripcion) }}</textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                            <x-text-input type="date" name="fecha" class="w-full"
                                :value="old('fecha', $presupuesto->fecha?->format('Y-m-d'))" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de vencimiento</label>
                            <x-text-input type="date" name="fecha_vencimiento" class="w-full"
                                :value="old('fecha_vencimiento', $presupuesto->fecha_vencimiento?->format('Y-m-d'))" />
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Guardar cambios</x-primary-button>
                        <a href="{{ route('admin.taller.presupuestos.show', $presupuesto) }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
