<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Plan de cuentas — {{ $companiaActiva->nombre ?? '' }}</h2>
            @can('contabilidad.crear')
                @if ($cuentas->isNotEmpty())
                    <a href="{{ route('admin.cuentas.create') }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Nueva cuenta</a>
                @endif
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @if ($cuentas->isEmpty())
                <div class="rounded-lg border-2 border-dashed border-slate-300 bg-white p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7.5h6M9 12h6M9 16.5h3M6.75 3h10.5A2.25 2.25 0 0 1 19.5 5.25v13.5A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75V5.25A2.25 2.25 0 0 1 6.75 3Z" /></svg>
                    <h3 class="mt-4 text-base font-semibold text-slate-900">Esta compañía aún no tiene plan de cuentas</h3>
                    <p class="mt-1 text-sm text-slate-500">Elige una plantilla para empezar con el catálogo completo (incluye cuentas por defecto configuradas), o crea las cuentas una por una.</p>
                    @can('contabilidad.crear')
                        <div class="mx-auto mt-6 grid max-w-2xl gap-3 sm:grid-cols-2">
                            @foreach ($plantillas as $plantilla)
                                <form method="POST" action="{{ route('admin.cuentas.aplicar-plantilla') }}" class="h-full">
                                    @csrf
                                    <input type="hidden" name="plantilla" value="{{ $plantilla->codigo }}">
                                    <button class="flex h-full w-full flex-col rounded-lg border border-slate-300 bg-white p-4 text-left hover:border-blue-500 hover:bg-blue-50">
                                        <span class="font-semibold text-[#0d2d5e]">{{ $plantilla->nombre }}</span>
                                        <span class="mt-1 text-xs text-slate-500">{{ $plantilla->descripcion }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('admin.cuentas.create') }}" class="text-sm font-semibold text-blue-700 hover:underline">o crear cuenta manual</a>
                        </div>
                    @endcan
                </div>
            @else
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 sm:px-6">Código / Cuenta</th>
                                <th class="hidden px-6 py-3 lg:table-cell">Tipo</th>
                                <th class="hidden px-6 py-3 md:table-cell">Naturaleza</th>
                                <th class="hidden px-6 py-3 lg:table-cell" title="Renglón del Formulario 2 (ISR) al que tributa">R-ISR</th>
                                <th class="hidden px-6 py-3 lg:table-cell">Movimiento</th>
                                <th class="px-4 py-3 sm:px-6">Estado</th>
                                <th class="px-4 py-3 text-right sm:px-6">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cuentas as $cuenta)
                                <tr class="{{ $cuenta->permite_movimiento ? '' : 'bg-slate-50' }}">
                                    <td class="px-4 py-2.5 sm:px-6">
                                        <div class="flex items-center" style="padding-left: {{ ($cuenta->nivel - 1) * 0.75 }}rem">
                                            <span class="w-14 shrink-0 font-mono text-xs text-slate-500 sm:w-20">{{ $cuenta->codigo }}</span>
                                            <span class="{{ $cuenta->permite_movimiento ? 'text-slate-800' : 'font-semibold text-[#0d2d5e]' }}">{{ $cuenta->nombre }}</span>
                                            @if ($cuenta->conciliable)
                                                <span class="ml-2 hidden rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 sm:inline">Conciliable</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden px-6 py-2.5 text-xs text-slate-600 lg:table-cell">{{ $cuenta->tipo->nombre ?? '—' }}</td>
                                    <td class="hidden px-6 py-2.5 md:table-cell">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $cuenta->naturaleza === 'DEBITO' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ $cuenta->naturaleza === 'DEBITO' ? 'Débito' : 'Crédito' }}
                                        </span>
                                    </td>
                                    <td class="hidden px-6 py-2.5 text-xs text-slate-500 lg:table-cell">{{ $cuenta->renglon_isr ?? '—' }}</td>
                                    <td class="hidden px-6 py-2.5 text-xs text-slate-600 lg:table-cell">{{ $cuenta->permite_movimiento ? 'Sí' : 'Título' }}</td>
                                    <td class="px-4 py-2.5 sm:px-6">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $cuenta->activa ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $cuenta->activa ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-medium sm:px-6">
                                        @can('contabilidad.editar')
                                            <a href="{{ route('admin.cuentas.edit', $cuenta) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                        @endcan
                                        @can('contabilidad.eliminar')
                                            <form method="POST" action="{{ route('admin.cuentas.destroy', $cuenta) }}" class="inline" onsubmit="return confirm('¿Eliminar la cuenta {{ $cuenta->codigo }}?')">
                                                @csrf @method('DELETE')
                                                <button class="ml-3 text-red-600 hover:text-red-800">Eliminar</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-500">{{ $cuentas->count() }} cuentas — las filas sombreadas son cuentas de título (no reciben movimientos).</p>
            @endif
        </div>
    </div>
</x-app-layout>
