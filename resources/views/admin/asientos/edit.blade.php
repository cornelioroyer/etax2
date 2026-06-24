<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ ($reemitir ?? false) ? "Re-emitir asiento {$asiento->numero}" : "Editar borrador {$asiento->numero}" }}
    </h2></x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($reemitir ?? false)
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    El asiento <strong>{{ $asiento->numero }}</strong> está posteado. Al guardar se <strong>anulará</strong>
                    y se creará uno <strong>nuevo posteado</strong> con los cambios; el número original queda en el historial.
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.asientos.update', $asiento) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                @method('PUT')
                @include('admin.asientos._form')
            </form>
        </div>
    </div>
</x-app-layout>
