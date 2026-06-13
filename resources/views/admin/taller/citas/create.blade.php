<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva cita</h2>
            <a href="{{ route('admin.taller.citas.index') }}" class="text-sm text-gray-500 hover:text-gray-900">← Citas</a>
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
                <form method="POST" action="{{ route('admin.taller.citas.store') }}" class="space-y-4">
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
                    </div>

                    @if ($sucursales->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
                            <select name="sucursal_id"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                <option value="">— Sin sucursal —</option>
                                @foreach ($sucursales as $s)
                                    <option value="{{ $s->id }}" {{ old('sucursal_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="sucursal_id" value="">
                    @endif

                    @if ($areas->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                            <select name="area_id"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                <option value="">— Sin área —</option>
                                @foreach ($areas as $a)
                                    <option value="{{ $a->id }}" {{ old('area_id') == $a->id ? 'selected' : '' }}>{{ $a->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="area_id" value="">
                    @endif

                    @if ($tecnicos->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Técnico</label>
                            <select name="tecnico_id"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                <option value="">— Sin técnico —</option>
                                @foreach ($tecnicos as $t)
                                    <option value="{{ $t->id }}" {{ old('tecnico_id') == $t->id ? 'selected' : '' }}>{{ $t->nombre_publico }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="tecnico_id" value="">
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID del cliente</label>
                        <x-text-input type="number" name="cliente_id" class="w-full" min="1"
                            placeholder="ID numérico del cliente" :value="old('cliente_id')" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID del equipo (opcional)</label>
                        <x-text-input type="number" name="equipo_id" class="w-full" min="1"
                            placeholder="ID numérico del equipo" :value="old('equipo_id')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha / hora inicio *</label>
                            <x-text-input type="datetime-local" name="fecha_inicio" class="w-full" required
                                :value="old('fecha_inicio')" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha / hora fin *</label>
                            <x-text-input type="datetime-local" name="fecha_fin" class="w-full" required
                                :value="old('fecha_fin')" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                        <textarea name="motivo" rows="3"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Motivo de la cita...">{{ old('motivo') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado *</label>
                        <select name="estado" required
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                            @foreach (\App\Models\TallerCita::ESTADOS as $val => $label)
                                <option value="{{ $val }}" {{ old('estado', 'programada') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Registrar cita</x-primary-button>
                        <a href="{{ route('admin.taller.citas.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
