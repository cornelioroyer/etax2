<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuentas bancarias</h2>
            @can('bancos.gestionar')
                <button type="button" x-data @click="$dispatch('abrir-nueva-cuenta')"
                        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                    + Nueva cuenta
                </button>
            @endcan
        </div>
    </x-slot>

    <div class="py-8" x-data="bancosMgr()">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <p class="text-sm text-gray-500">
                Registra aquí las cuentas bancarias de la compañía. Vincula cada cuenta a su cuenta contable
                correspondiente para que los cobros y pagos impacten correctamente el libro mayor.
            </p>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Banco</th>
                            <th class="px-4 py-3">Número de cuenta</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 hidden sm:table-cell">Moneda</th>
                            <th class="px-4 py-3 hidden md:table-cell">Cuenta contable</th>
                            <th class="px-4 py-3 text-right hidden sm:table-cell">Saldo inicial</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            @can('bancos.gestionar')
                                <th class="px-4 py-3 text-right">Acciones</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cuentas as $cuenta)
                            <tr class="hover:bg-gray-50 {{ $cuenta->activa ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $cuenta->banco_nombre }}</td>
                                <td class="px-4 py-3 font-mono text-gray-700">{{ $cuenta->numero_cuenta }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $tipos[$cuenta->tipo] ?? $cuenta->tipo }}</td>
                                <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">{{ $monedas[$cuenta->moneda] ?? $cuenta->moneda }}</td>
                                <td class="px-4 py-3 hidden md:table-cell">
                                    @if ($cuenta->cuentaContable)
                                        <span class="font-mono text-xs text-gray-500">{{ $cuenta->cuentaContable->codigo }}</span>
                                        <span class="text-xs text-gray-600"> — {{ $cuenta->cuentaContable->nombre }}</span>
                                    @else
                                        <span class="text-gray-300 text-xs">Sin vincular</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums hidden sm:table-cell text-gray-700">
                                    B/. {{ number_format((float) $cuenta->saldo_inicial, 2) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($cuenta->activa)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Activa</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">Inactiva</span>
                                    @endif
                                </td>
                                @can('bancos.gestionar')
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button"
                                                @click="abrirEditar({{ $cuenta->id }}, '{{ addslashes($cuenta->banco_nombre) }}', '{{ $cuenta->tipo }}', '{{ $cuenta->moneda }}', {{ $cuenta->cuenta_contable_id ?? 'null' }}, {{ $cuenta->saldo_inicial }})"
                                                class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
                                        <form method="POST" action="{{ route('admin.bancos.toggle', $cuenta) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="{{ $cuenta->activa ? 'text-gray-500 hover:text-gray-700' : 'text-green-600 hover:text-green-800' }}">
                                                {{ $cuenta->activa ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                    No hay cuentas bancarias registradas.
                                    @can('bancos.gestionar')
                                        <button type="button" x-data @click="$dispatch('abrir-nueva-cuenta')" class="text-blue-700 underline">Crear la primera</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal Nueva cuenta --}}
        @can('bancos.gestionar')
        <div x-show="modalNuevo" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
             @abrir-nueva-cuenta.window="modalNuevo = true"
             @keydown.escape.window="modalNuevo = false">
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl p-6 mx-4" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Nueva cuenta bancaria</h3>
                <form method="POST" action="{{ route('admin.bancos.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <x-input-label for="n_banco" value="Banco *" />
                            <x-text-input id="n_banco" name="banco_nombre" type="text" class="mt-1 block w-full"
                                          placeholder="Ej: Banco General" required maxlength="100" />
                        </div>
                        <div>
                            <x-input-label for="n_numero" value="Número de cuenta *" />
                            <x-text-input id="n_numero" name="numero_cuenta" type="text" class="mt-1 block w-full font-mono"
                                          placeholder="Ej: 04-12-345678-0" required maxlength="50" />
                        </div>
                        <div>
                            <x-input-label for="n_tipo" value="Tipo *" />
                            <select id="n_tipo" name="tipo" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($tipos as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="n_moneda" value="Moneda *" />
                            <select id="n_moneda" name="moneda" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($monedas as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="n_saldo" value="Saldo inicial" />
                            <x-text-input id="n_saldo" name="saldo_inicial" type="number" step="0.01" min="0"
                                          class="mt-1 block w-full" placeholder="0.00" />
                        </div>
                        <div class="col-span-2">
                            <x-input-label for="n_cuenta" value="Cuenta contable vinculada" />
                            <select id="n_cuenta" name="cuenta_contable_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Sin vincular —</option>
                                @foreach ($cuentasContables as $cc)
                                    <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
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
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar cuenta bancaria</h3>
                <form :action="'/admin/bancos/' + editId" method="POST" class="space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <x-input-label for="e_banco" value="Banco *" />
                            <x-text-input id="e_banco" name="banco_nombre" type="text" class="mt-1 block w-full"
                                          x-model="editBanco" required maxlength="100" />
                        </div>
                        <div>
                            <x-input-label for="e_tipo" value="Tipo *" />
                            <select id="e_tipo" name="tipo" x-model="editTipo" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($tipos as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="e_moneda" value="Moneda *" />
                            <select id="e_moneda" name="moneda" x-model="editMoneda" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($monedas as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="e_saldo" value="Saldo inicial" />
                            <x-text-input id="e_saldo" name="saldo_inicial" type="number" step="0.01" min="0"
                                          class="mt-1 block w-full" x-model="editSaldo" />
                        </div>
                        <div class="col-span-1"></div>
                        <div class="col-span-2">
                            <x-input-label for="e_cuenta" value="Cuenta contable vinculada" />
                            <select id="e_cuenta" name="cuenta_contable_id" x-model="editCuentaId"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Sin vincular —</option>
                                @foreach ($cuentasContables as $cc)
                                    <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
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
        function bancosMgr() {
            return {
                modalNuevo: false,
                modalEditar: false,
                editId: null, editBanco: '', editTipo: 'CORRIENTE',
                editMoneda: 'PAB', editCuentaId: '', editSaldo: '0.00',
                abrirEditar(id, banco, tipo, moneda, cuentaId, saldo) {
                    this.editId = id; this.editBanco = banco; this.editTipo = tipo;
                    this.editMoneda = moneda; this.editCuentaId = cuentaId ? String(cuentaId) : '';
                    this.editSaldo = saldo; this.modalEditar = true;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
