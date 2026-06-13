<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Diarios contables</h2>
            @can('contabilidad.editar')
                <button type="button" x-data @click="$dispatch('abrir-nuevo-diario')"
                        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    + Nuevo diario
                </button>
            @endcan
        </div>
    </x-slot>

    <div class="py-8" x-data="diariosMgr()">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <p class="text-sm text-gray-500">
                Los diarios agrupan los asientos contables por tipo (ventas, cobros, pagos, etc.).
                Cada compañía puede tener múltiples diarios; el diario <span class="font-mono font-medium">GENERAL</span> se crea automáticamente cuando se registra el primer asiento.
            </p>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 hidden md:table-cell">Cuenta asociada</th>
                            <th class="px-4 py-3 text-center hidden sm:table-cell">Aprobación</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            @can('contabilidad.editar')
                                <th class="px-4 py-3 text-right">Acciones</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($diarios as $diario)
                            <tr class="hover:bg-gray-50 {{ $diario->activo ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 font-mono font-medium text-gray-900">{{ $diario->codigo }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $diario->nombre }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $tipos[$diario->tipo_diario] ?? $diario->tipo_diario }}</td>
                                <td class="px-4 py-3 text-gray-500 hidden md:table-cell">
                                    @if ($diario->cuentaDefault)
                                        <span class="font-mono text-xs">{{ $diario->cuentaDefault->codigo }}</span>
                                        <span class="text-xs">— {{ $diario->cuentaDefault->nombre }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center hidden sm:table-cell">
                                    @if ($diario->requiere_aprobacion)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Sí</span>
                                    @else
                                        <span class="text-gray-300 text-xs">No</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($diario->activo)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Activo</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">Inactivo</span>
                                    @endif
                                </td>
                                @can('contabilidad.editar')
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button"
                                                @click="abrirEditar({{ $diario->id }}, '{{ addslashes($diario->nombre) }}', '{{ $diario->tipo_diario }}', {{ $diario->cuenta_default_id ?? 'null' }}, {{ $diario->requiere_aprobacion ? 'true' : 'false' }})"
                                                class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
                                        <form method="POST" action="{{ route('admin.diarios.toggle', $diario) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="{{ $diario->activo ? 'text-gray-500 hover:text-gray-700' : 'text-green-600 hover:text-green-800' }}">
                                                {{ $diario->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                    No hay diarios configurados.
                                    @can('contabilidad.editar')
                                        <button type="button" x-data @click="$dispatch('abrir-nuevo-diario')" class="text-blue-700 underline">Crear el primero</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal Nuevo --}}
        @can('contabilidad.editar')
        <div x-show="modalNuevo" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
             @abrir-nuevo-diario.window="modalNuevo = true"
             @keydown.escape.window="modalNuevo = false">
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl p-6 mx-4" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Nuevo diario</h3>
                <form method="POST" action="{{ route('admin.diarios.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="n_codigo" value="Código *" />
                            <x-text-input id="n_codigo" name="codigo" type="text" class="mt-1 block w-full uppercase"
                                          placeholder="Ej: VENTAS" required maxlength="30"
                                          oninput="this.value=this.value.toUpperCase()" />
                            <p class="mt-1 text-xs text-gray-400">Letras mayúsculas, números y guiones bajos.</p>
                        </div>
                        <div>
                            <x-input-label for="n_tipo" value="Tipo *" />
                            <select id="n_tipo" name="tipo_diario" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($tipos as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="n_nombre" value="Nombre *" />
                        <x-text-input id="n_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                      placeholder="Ej: Diario de Ventas" required maxlength="100" />
                    </div>
                    <div>
                        <x-input-label for="n_cuenta" value="Cuenta asociada (opcional)" />
                        <select id="n_cuenta" name="cuenta_default_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Sin asignar —</option>
                            @foreach ($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="n_aprobacion" name="requiere_aprobacion" type="checkbox" value="1"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="n_aprobacion" class="text-sm text-gray-700">Requiere aprobación antes de postear</label>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="modalNuevo = false"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Editar --}}
        <div x-show="modalEditar" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
             @keydown.escape.window="modalEditar = false">
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl p-6 mx-4" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar diario</h3>
                <form :action="'/admin/diarios/' + editId" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="e_tipo" value="Tipo *" />
                        <select id="e_tipo" name="tipo_diario" x-model="editTipo" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($tipos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="e_nombre" value="Nombre *" />
                        <x-text-input id="e_nombre" name="nombre" type="text" class="mt-1 block w-full"
                                      x-model="editNombre" required maxlength="100" />
                    </div>
                    <div>
                        <x-input-label for="e_cuenta" value="Cuenta asociada (opcional)" />
                        <select id="e_cuenta" name="cuenta_default_id" x-model="editCuentaId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Sin asignar —</option>
                            @foreach ($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="e_aprobacion" name="requiere_aprobacion" type="checkbox" value="1"
                               x-bind:checked="editAprobacion"
                               @change="editAprobacion = $event.target.checked"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="e_aprobacion" class="text-sm text-gray-700">Requiere aprobación antes de postear</label>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="modalEditar = false"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    </div>

    @push('scripts')
    <script>
        function diariosMgr() {
            return {
                modalNuevo: false,
                modalEditar: false,
                editId: null,
                editNombre: '',
                editTipo: 'GENERAL',
                editCuentaId: '',
                editAprobacion: false,
                abrirEditar(id, nombre, tipo, cuentaId, aprobacion) {
                    this.editId = id;
                    this.editNombre = nombre;
                    this.editTipo = tipo;
                    this.editCuentaId = cuentaId ? String(cuentaId) : '';
                    this.editAprobacion = aprobacion;
                    this.modalEditar = true;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
