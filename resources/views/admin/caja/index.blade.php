<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Caja menuda</h2>
            <x-help-button module="caja" />
        </div>
    </x-slot>

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

            {{-- Alta de caja --}}
            @can('caja.gestionar')
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Nueva caja</h3>
                    <form method="POST" action="{{ route('admin.caja.store') }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div>
                                <x-input-label for="codigo" value="Código *" />
                                <x-text-input id="codigo" name="codigo" type="text" class="mt-1 block w-full" :value="old('codigo')" placeholder="CAJA01" required />
                            </div>
                            <div>
                                <x-input-label for="nombre" value="Nombre *" />
                                <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre')" required />
                            </div>
                            <div class="sm:col-span-2">
                                <x-buscador-contacto name="cuenta_contable_id" label="Cuenta de efectivo (GL)" :opciones="$cuentas"
                                    :selected="old('cuenta_contable_id')" placeholder="Buscar cuenta por código o nombre" empty-label="— Cuenta contable —" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Crear caja</button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- Listado --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Cuenta efectivo</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cajas as $caja)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('admin.caja.show', $caja) }}" class="text-blue-700 hover:underline">{{ $caja->codigo }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $caja->nombre }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $caja->cuentaContable?->codigo ?? '— sin cuenta —' }}</td>
                                <td class="px-4 py-3">
                                    @if ($caja->activa)
                                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Activa</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Inactiva</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">No hay cajas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
