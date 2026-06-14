<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo escenario</h2>
            <a href="{{ route('admin.presupuestos.escenarios.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Escenarios</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.presupuestos.escenarios.store') }}">
                    @csrf
                    <div>
                        <x-input-label for="nombre" value="Nombre del escenario *" />
                        <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full"
                            :value="old('nombre')" required maxlength="150" placeholder="Ej.: Base 2026, Conservador..." />
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Guardar escenario</x-primary-button>
                        <a href="{{ route('admin.presupuestos.escenarios.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
