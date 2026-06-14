<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo presupuesto</h2>
            <a href="{{ route('admin.presupuestos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Presupuestos</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.presupuestos.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="nombre" value="Nombre *" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                                :value="old('nombre')" required maxlength="150" />
                        </div>
                        <div>
                            <x-input-label for="anio" value="Año *" />
                            <x-text-input id="anio" name="anio" type="number" min="2000" max="2100" class="mt-1 block w-full"
                                :value="old('anio', now()->year)" required />
                        </div>
                        <div>
                            <x-input-label for="escenario_id" value="Escenario" />
                            <select id="escenario_id" name="escenario_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— Sin escenario —</option>
                                @foreach ($escenarios as $e)
                                    <option value="{{ $e->id }}" {{ old('escenario_id') == $e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="version_id" value="Versión" />
                            <select id="version_id" name="version_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— Sin versión —</option>
                                @foreach ($versiones as $v)
                                    <option value="{{ $v->id }}" {{ old('version_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Crear y agregar cuentas</x-primary-button>
                        <a href="{{ route('admin.presupuestos.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
