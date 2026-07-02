<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nueva planilla</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="mb-4 text-sm text-gray-500">
                    Al crear la planilla se calcula de inmediato: salario de cada empleado del período,
                    novedades vigentes, CSS, Seguro Educativo, ISR y cuotas patronales.
                    Queda <b>procesada</b> para tu revisión — nada toca la contabilidad hasta que la contabilices.
                </p>
                <form method="POST" action="{{ route('admin.nomina.planillas.store') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <x-input-label value="Período de pago *" />
                            <select name="periodo_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                <option value="">— elegir período —</option>
                                @foreach ($periodos as $p)
                                    <option value="{{ $p->id }}" @selected((int) old('periodo_id') === $p->id)>{{ $p->etiqueta() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Fecha contable *" />
                            <x-text-input name="fecha" type="date" class="mt-1 block w-full"
                                value="{{ old('fecha', now()->toDateString()) }}" required />
                            <p class="mt-1 text-xs text-gray-400">Fecha del asiento al contabilizar (debe caer en un período contable abierto).</p>
                        </div>
                        <div>
                            <x-input-label value="Descripción" />
                            <x-text-input name="descripcion" type="text" class="mt-1 block w-full"
                                value="{{ old('descripcion') }}" maxlength="300" />
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Crear y calcular</x-primary-button>
                        <a href="{{ route('admin.nomina.planillas.index') }}"
                           class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
