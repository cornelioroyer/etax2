<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo asiento de diario</h2></x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if ($cuentas->isEmpty())
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    La compañía no tiene cuentas de movimiento activas.
                    <a class="font-semibold underline" href="{{ route('admin.cuentas.index') }}">Configura el plan de cuentas</a> primero.
                </div>
            @else
                <form method="POST" action="{{ route('admin.asientos.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    @csrf
                    @include('admin.asientos._form')
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
