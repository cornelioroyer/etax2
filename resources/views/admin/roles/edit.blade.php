<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar rol <span class="text-base font-normal text-gray-500">{{ $role->etiqueta() }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                @csrf
                @method('PUT')
                @include('admin.roles._form', ['role' => $role])
            </form>
        </div>
    </div>
</x-app-layout>
