<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo rol</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.roles.store') }}">
                @csrf
                @include('admin.roles._form', ['role' => null])
            </form>
        </div>
    </div>
</x-app-layout>
