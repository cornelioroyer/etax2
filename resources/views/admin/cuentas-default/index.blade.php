<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuentas por defecto</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-lg bg-white shadow-sm p-6">
                <p class="text-sm text-gray-500 mb-6">
                    Estas cuentas se usan como valores por defecto al registrar documentos de CxC, CxP y otros módulos.
                    Deja en blanco las que no apliquen.
                </p>

                <form method="POST" action="{{ route('admin.cuentas-default.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        @foreach ($claves as $clave => $descripcion)
                            @php $actual = $defaults->get($clave) @endphp
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center border-b border-gray-100 pb-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">{{ $descripcion }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $clave }}</p>
                                </div>
                                <select name="defaults[{{ $clave }}]"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                                    <option value="">— Sin asignar —</option>
                                    @foreach ($cuentas as $cuenta)
                                        <option value="{{ $cuenta->id }}"
                                            @selected($actual && $actual->cuenta_id === $cuenta->id)>
                                            {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>

                    @can('contabilidad.editar')
                        <div class="mt-6 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                Guardar cambios
                            </button>
                        </div>
                    @endcan
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
