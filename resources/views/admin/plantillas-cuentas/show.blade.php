<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $plantilla->codigo }} — {{ $plantilla->nombre }}</h2>
                <p class="text-sm text-slate-500">{{ $detalles->count() }} cuentas · {{ $plantilla->pais ?? '—' }} · {{ $plantilla->activa ? 'Activa' : 'Inactiva' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.plantillas-cuentas.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">← Volver</a>
                <a href="{{ route('admin.plantillas-cuentas.edit', $plantilla) }}" class="rounded-md border border-[#0d2d5e] bg-white px-4 py-2 text-sm font-semibold text-[#0d2d5e] hover:bg-blue-50">Editar plantilla</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ agregar: {{ $errors->any() ? 'true' : 'false' }} }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                    </ul>
                </div>
            @endif

            @if ($plantilla->descripcion)
                <div class="rounded-md bg-slate-50 p-3 text-sm text-slate-600">{{ $plantilla->descripcion }}</div>
            @endif

            <div class="flex justify-end">
                <button type="button" @click="agregar = !agregar"
                        class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">
                    <span x-show="!agregar">+ Agregar cuenta</span>
                    <span x-show="agregar" x-cloak>Cerrar</span>
                </button>
            </div>

            {{-- Alta de cuenta --}}
            <div x-show="agregar" x-cloak class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Nueva cuenta en la plantilla</h3>
                <form method="POST" action="{{ route('admin.plantillas-cuentas.detalle.store', $plantilla) }}">
                    @csrf
                    @include('admin.plantillas-cuentas._detalle-campos', ['detalle' => null, 'padres' => $detalles])
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" @click="agregar = false" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Agregar cuenta</button>
                    </div>
                </form>
            </div>

            {{-- Listado de cuentas --}}
            @if ($detalles->isEmpty())
                <div class="rounded-lg border-2 border-dashed border-slate-300 bg-white p-12 text-center">
                    <h3 class="text-base font-semibold text-slate-900">La plantilla aún no tiene cuentas</h3>
                    <p class="mt-1 text-sm text-slate-500">Usa «Agregar cuenta» para empezar a construir el catálogo.</p>
                </div>
            @else
                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 sm:px-6">Código / Cuenta</th>
                                <th class="hidden px-6 py-3 lg:table-cell">Tipo</th>
                                <th class="hidden px-6 py-3 md:table-cell">Naturaleza</th>
                                <th class="hidden px-6 py-3 lg:table-cell">Movimiento</th>
                                <th class="hidden px-6 py-3 lg:table-cell">Clave default</th>
                                <th class="hidden px-6 py-3 lg:table-cell" title="Renglón del Formulario 2 (ISR)">R-ISR</th>
                                <th class="px-4 py-3 text-right sm:px-6">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($detalles as $d)
                                <tr class="{{ $d->permite_movimiento ? '' : 'bg-slate-50' }}">
                                    <td class="px-4 py-2.5 sm:px-6">
                                        <div class="flex items-center" style="padding-left: {{ ($d->nivel - 1) * 0.75 }}rem">
                                            <span class="w-16 shrink-0 font-mono text-xs text-slate-500 sm:w-24">{{ $d->codigo }}</span>
                                            <span class="{{ $d->permite_movimiento ? 'text-slate-800' : 'font-semibold text-[#0d2d5e]' }}">{{ $d->nombre }}</span>
                                            @if ($d->conciliable)
                                                <span class="ml-2 hidden rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 sm:inline">Conciliable</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden px-6 py-2.5 text-xs text-slate-600 lg:table-cell">{{ $d->tipo_cuenta_codigo }}</td>
                                    <td class="hidden px-6 py-2.5 md:table-cell">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $d->naturaleza === 'DEBITO' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ $d->naturaleza === 'DEBITO' ? 'Débito' : 'Crédito' }}
                                        </span>
                                    </td>
                                    <td class="hidden px-6 py-2.5 text-xs text-slate-600 lg:table-cell">{{ $d->permite_movimiento ? 'Sí' : 'Título' }}</td>
                                    <td class="hidden px-6 py-2.5 text-xs lg:table-cell">
                                        @if ($d->clave_default)
                                            <span class="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-amber-700">{{ $d->clave_default }}</span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="hidden px-6 py-2.5 text-xs text-slate-500 lg:table-cell">{{ $d->renglon_isr ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-right font-medium sm:px-6">
                                        <a href="{{ route('admin.plantillas-cuentas.detalle.edit', [$plantilla, $d]) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                        <form method="POST" action="{{ route('admin.plantillas-cuentas.detalle.destroy', [$plantilla, $d]) }}" class="inline" onsubmit="return confirm('¿Eliminar la cuenta {{ $d->codigo }} de la plantilla?')">
                                            @csrf @method('DELETE')
                                            <button class="ml-3 text-red-600 hover:text-red-800">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-500">{{ $detalles->count() }} cuentas — las filas sombreadas son cuentas de título (no reciben movimientos). Las claves <span class="font-mono">CLAVE</span> mapean cuentas por defecto que se configuran al aplicar la plantilla.</p>
            @endif
        </div>
    </div>
</x-app-layout>
