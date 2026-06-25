<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva plantilla de factura recurrente</h2></x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if ($proveedores->isEmpty() || $cuentas->isEmpty())
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    Necesitas al menos un <strong>proveedor</strong> y cuentas de movimiento activas.
                    <a class="font-semibold underline" href="{{ route('admin.contactos.index', ['tipo' => 'PROVEEDOR']) }}">Configura proveedores</a>
                    y el <a class="font-semibold underline" href="{{ route('admin.cuentas.index') }}">plan de cuentas</a> primero.
                </div>
            @else
                <form method="POST" action="{{ route('admin.cxp.recurrentes.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    @csrf
                    @include('admin.cxp.recurrentes._form')
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
