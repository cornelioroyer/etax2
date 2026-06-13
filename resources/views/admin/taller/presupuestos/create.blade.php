<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo presupuesto</h2>
            <a href="{{ route('admin.taller.presupuestos.index') }}" class="text-sm text-gray-500 hover:text-gray-900">← Presupuestos</a>
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
                <form method="POST" action="{{ route('admin.taller.presupuestos.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Taller *</label>
                        <select name="taller_id" required onchange="this.form.submit()"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                            <option value="">— Seleccionar taller —</option>
                            @foreach ($talleres as $t)
                                <option value="{{ $t->id }}" {{ $tallerId == $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Al seleccionar el taller la página recargará para mostrar equipos disponibles.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID del cliente</label>
                        <x-text-input type="number" name="cliente_id" class="w-full" min="1"
                            placeholder="ID numérico del cliente (contacto)" :value="old('cliente_id')" />
                    </div>

                    @if ($equipos->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Equipo (opcional)</label>
                            <select name="equipo_id"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                <option value="">— Sin equipo —</option>
                                @foreach ($equipos as $e)
                                    <option value="{{ $e->id }}" {{ old('equipo_id') == $e->id ? 'selected' : '' }}>
                                        {{ $e->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="equipo_id" value="">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID del equipo (opcional)</label>
                            <x-text-input type="number" name="equipo_id" class="w-full" min="1"
                                placeholder="ID numérico del equipo" :value="old('equipo_id')" />
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="descripcion" rows="3"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Descripción del presupuesto...">{{ old('descripcion') }}</textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                            <x-text-input type="date" name="fecha" class="w-full"
                                :value="old('fecha', now()->format('Y-m-d'))" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de vencimiento</label>
                            <x-text-input type="date" name="fecha_vencimiento" class="w-full"
                                :value="old('fecha_vencimiento')" />
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Crear presupuesto</x-primary-button>
                        <a href="{{ route('admin.taller.presupuestos.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
