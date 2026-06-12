<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar contacto — {{ $contacto->nombre }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.contactos.update', $contacto) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf @method('PUT')
                @include('admin.contactos._form')
            </form>
        </div>
    </div>
</x-app-layout>
