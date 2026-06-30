<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuentas bancarias</h2>
            @can('bancos.gestionar')
                <button onclick="document.getElementById('modal-cuenta').classList.remove('hidden')"
                    class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    + Nueva cuenta
                </button>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            {{-- Resumen por cuenta --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cuentas->where('activa', true) as $c)
                    <a href="{{ route('admin.bco.cuentas.show', $c) }}" class="block bg-white rounded-lg shadow-sm p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-gray-500 font-medium uppercase">{{ $c->banco?->nombre }}</p>
                                <p class="font-semibold text-gray-900 mt-0.5">{{ $c->nombre }}</p>
                                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $c->numero_cuenta }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">{{ $c->tipo_cuenta }}</span>
                        </div>
                        <div class="mt-4 text-right">
                            <p class="text-xs text-gray-400">Saldo actual</p>
                            <p class="text-xl font-bold text-gray-900">B/. {{ number_format($c->saldo_actual, 2) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Tabla completa --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Todas las cuentas</h3>
                    @can('bancos.gestionar')
                        <button onclick="document.getElementById('modal-banco').classList.remove('hidden')"
                            class="text-xs text-blue-600 hover:underline">+ Agregar banco</button>
                    @endcan
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Banco</th>
                            <th class="px-4 py-3">Número / Nombre</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Cuenta contable</th>
                            <th class="px-4 py-3 text-right">Saldo inicial</th>
                            <th class="px-4 py-3 text-right">Saldo actual</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cuentas as $c)
                            <tr class="{{ $c->activa ? '' : 'opacity-50' }}">
                                <td class="px-4 py-3">{{ $c->banco?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $c->nombre }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $c->numero_cuenta }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ \App\Models\BcoCuenta::TIPOS[$c->tipo_cuenta] ?? $c->tipo_cuenta }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $c->cuentaContable?->codigo }} {{ $c->cuentaContable?->nombre }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">B/. {{ number_format((float) $c->saldo_inicial, 2) }}</td>
                                <td class="px-4 py-3 text-right font-medium">B/. {{ number_format($c->saldo_actual, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $c->activa ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $c->activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <a href="{{ route('admin.bco.cuentas.show', $c) }}" class="text-blue-600 hover:underline text-xs">Movimientos</a>
                                    @can('bancos.gestionar')
                                        <form method="POST" action="{{ route('admin.bco.cuentas.toggle', $c) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-gray-400 hover:text-gray-700">{{ $c->activa ? 'Desactivar' : 'Activar' }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Sin cuentas bancarias registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal nueva cuenta --}}
    @can('bancos.gestionar')
    <div id="modal-cuenta" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-base font-semibold text-gray-900">Nueva cuenta bancaria</h3>
                <button onclick="document.getElementById('modal-cuenta').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
            </div>
            <form method="POST" action="{{ route('admin.bco.cuentas.store') }}" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <x-buscador-contacto name="banco_id" label="Banco *" required
                            :opciones="$bancos" :selected="old('banco_id')" placeholder="Buscar banco por nombre" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Número de cuenta <span class="text-red-500">*</span></label>
                        <input type="text" name="numero_cuenta" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre / Alias <span class="text-red-500">*</span></label>
                        <input type="text" name="nombre" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo <span class="text-red-500">*</span></label>
                        <select name="tipo_cuenta" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (\App\Models\BcoCuenta::TIPOS as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Saldo inicial</label>
                        <input type="number" name="saldo_inicial" value="0" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div class="col-span-2">
                        <x-buscador-contacto name="cuenta_contable_id" label="Cuenta contable" :opciones="$cuentasContables"
                            placeholder="Buscar cuenta por código o nombre" empty-label="— Sin mapeo contable —" />
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Crear cuenta</button>
                    <button type="button" onclick="document.getElementById('modal-cuenta').classList.add('hidden')"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal nuevo banco --}}
    <div id="modal-banco" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-base font-semibold text-gray-900">Registrar banco</h3>
                <button onclick="document.getElementById('modal-banco').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
            </div>
            <form method="POST" action="{{ route('admin.bco.bancos.store') }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700">Código (BIC / siglas)</label>
                    <input type="text" name="codigo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="BANCOP, BANIST…">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Guardar</button>
                    <button type="button" onclick="document.getElementById('modal-banco').classList.add('hidden')"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-layout>
