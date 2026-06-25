<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar plantilla recurrente</h2></x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if ($plantilla->ocurrencias_generadas > 0)
                <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-800">
                    Esta plantilla ya generó {{ $plantilla->ocurrencias_generadas }} asiento(s). Los cambios solo afectan a los <strong>próximos</strong> vencimientos; los asientos ya generados no se tocan.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.asientos-recurrentes.update', $plantilla) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                @method('PUT')
                @include('admin.asientos-recurrentes._form')
            </form>
        </div>
    </div>
</x-app-layout>
