<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva zona</h2></x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.zonas.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                @include('admin.zonas._form')
            </form>
        </div>
    </div>
</x-app-layout>
