<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva plantilla de cuentas</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.plantillas-cuentas.store') }}">
                    @csrf
                    @include('admin.plantillas-cuentas._form')

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('admin.plantillas-cuentas.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Crear plantilla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
