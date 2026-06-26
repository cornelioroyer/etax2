<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Plantillas de plan de cuentas</h2>
            <a href="{{ route('admin.plantillas-cuentas.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Nueva plantilla</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-800">
                Estas plantillas son <strong>globales</strong>: se copian al plan de cuentas de una compañía
                <strong>nueva</strong> cuando aún no tiene cuentas. Editarlas no afecta a las compañías ya creadas;
                solo cambia lo que recibirán las compañías futuras. La plantilla
                <strong>{{ $porDefecto }}</strong> es la que se aplica por defecto.
            </div>

            @if ($plantillas->isEmpty())
                <div class="rounded-lg border-2 border-dashed border-slate-300 bg-white p-12 text-center">
                    <h3 class="text-base font-semibold text-slate-900">No hay plantillas</h3>
                    <p class="mt-1 text-sm text-slate-500">Crea la primera plantilla de plan de cuentas.</p>
                    <a href="{{ route('admin.plantillas-cuentas.create') }}" class="mt-4 inline-block rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Nueva plantilla</a>
                </div>
            @else
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 sm:px-6">Código / Nombre</th>
                                <th class="hidden px-6 py-3 md:table-cell">País</th>
                                <th class="px-6 py-3 text-center">Cuentas</th>
                                <th class="px-4 py-3 sm:px-6">Estado</th>
                                <th class="px-4 py-3 text-right sm:px-6">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($plantillas as $plantilla)
                                <tr>
                                    <td class="px-4 py-2.5 sm:px-6">
                                        <a href="{{ route('admin.plantillas-cuentas.show', $plantilla) }}" class="font-semibold text-[#0d2d5e] hover:underline">{{ $plantilla->codigo }}</a>
                                        @if ($plantilla->codigo === $porDefecto)
                                            <span class="ml-2 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Por defecto</span>
                                        @endif
                                        <div class="text-slate-700">{{ $plantilla->nombre }}</div>
                                        @if ($plantilla->descripcion)
                                            <div class="mt-0.5 text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($plantilla->descripcion, 120) }}</div>
                                        @endif
                                    </td>
                                    <td class="hidden px-6 py-2.5 text-slate-600 md:table-cell">{{ $plantilla->pais ?? '—' }}</td>
                                    <td class="px-6 py-2.5 text-center text-slate-600">{{ $plantilla->detalles_count }}</td>
                                    <td class="px-4 py-2.5 sm:px-6">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $plantilla->activa ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $plantilla->activa ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-medium sm:px-6">
                                        <a href="{{ route('admin.plantillas-cuentas.show', $plantilla) }}" class="text-[#0d2d5e] hover:underline">Cuentas</a>
                                        <a href="{{ route('admin.plantillas-cuentas.edit', $plantilla) }}" class="ml-3 text-indigo-600 hover:text-indigo-900">Editar</a>
                                        @if ($plantilla->codigo !== $porDefecto)
                                            <form method="POST" action="{{ route('admin.plantillas-cuentas.destroy', $plantilla) }}" class="inline" onsubmit="return confirm('¿Eliminar la plantilla {{ $plantilla->codigo }} y sus {{ $plantilla->detalles_count }} cuentas? Esto no afecta a las compañías ya creadas.')">
                                                @csrf @method('DELETE')
                                                <button class="ml-3 text-red-600 hover:text-red-800">Eliminar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
