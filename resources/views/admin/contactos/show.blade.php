<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $contacto->nombre }}</h2>
                <p class="text-sm text-gray-500">{{ $contacto->identificacion ? "RUC/Cédula: {$contacto->identificacion}" : '' }}</p>
            </div>
            <div class="flex gap-3">
                @can('contactos.editar')
                    <a href="{{ route('admin.contactos.edit', $contacto) }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Editar</a>
                @endcan
                <a href="{{ route('admin.contactos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Info básica --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Nombre</span><p class="font-medium">{{ $contacto->nombre }}</p></div>
                    <div><span class="text-gray-500">Tipos</span><p>{{ $contacto->tipos->pluck('nombre')->join(', ') }}</p></div>
                    <div><span class="text-gray-500">Email</span><p>{{ $contacto->email ?? '—' }}</p></div>
                    <div><span class="text-gray-500">Teléfono</span><p>{{ $contacto->telefono ?? '—' }}</p></div>
                    <div><span class="text-gray-500">RUC / Cédula</span><p>{{ $contacto->identificacion ?? '—' }}{{ $contacto->dv ? ' DV '.$contacto->dv : '' }}</p></div>
                    <div><span class="text-gray-500">Forma de pago</span><p>{{ $contacto->forma_pago ? ucfirst(strtolower($contacto->forma_pago)) : '—' }}</p></div>
                    @if ($contacto->forma_pago === \App\Models\Contacto::FORMA_PAGO_CREDITO)
                    <div><span class="text-gray-500">Días de crédito</span><p>{{ $contacto->dias_credito ?? 30 }} días</p></div>
                    @endif
                    <div><span class="text-gray-500">Concepto</span><p>{{ $contacto->conceptoEtiqueta() ?? '—' }}</p></div>
                </div>
            </div>

            {{-- Personas de contacto --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Personas de contacto</h3>
                </div>
                @can('contactos.editar')
                    <form method="POST" action="{{ route('admin.contactos.personas.store', $contacto) }}" class="px-4 py-3 bg-gray-50 flex flex-wrap gap-3 items-end border-b border-gray-100">
                        @csrf
                        <div><label class="block text-xs text-gray-500 mb-1">Nombre *</label>
                            <input type="text" name="nombre" required maxlength="200" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Cargo</label>
                            <input type="text" name="cargo" maxlength="100" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Email</label>
                            <input type="email" name="email" maxlength="150" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Teléfono</label>
                            <input type="text" name="telefono" maxlength="50" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <label class="flex items-center gap-1 text-xs text-gray-600">
                            <input type="checkbox" name="principal" value="1" class="rounded border-gray-300"> Principal
                        </label>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                    </form>
                @endcan
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Cargo</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Teléfono</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($personas as $p)
                            <tr class="{{ $p->principal ? 'bg-blue-50' : '' }}">
                                <td class="px-4 py-3 font-medium">{{ $p->nombre }} @if($p->principal)<span class="ml-1 text-xs text-blue-600">★</span>@endif</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->cargo ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->email ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->telefono ?? '—' }}</td>
                                <td class="px-4 py-3">@can('contactos.editar')
                                    <form method="POST" action="{{ route('admin.contactos.personas.destroy', [$contacto, $p]) }}" onsubmit="return confirm('¿Eliminar?')">@csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Eliminar</button>
                                    </form>@endcan</td>
                            </tr>
                        @empty<tr><td colspan="5" class="px-4 py-4 text-center text-gray-400 text-xs">Sin personas de contacto.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>

            {{-- Cuentas bancarias --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Cuentas bancarias</h3>
                </div>
                @can('contactos.editar')
                    <form method="POST" action="{{ route('admin.contactos.cuentas-bancarias.store', $contacto) }}" class="px-4 py-3 bg-gray-50 flex flex-wrap gap-3 items-end border-b border-gray-100">
                        @csrf
                        <div><label class="block text-xs text-gray-500 mb-1">Banco *</label>
                            <input type="text" name="banco" required maxlength="100" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">No. Cuenta *</label>
                            <input type="text" name="numero_cuenta" required maxlength="50" class="rounded-md border-gray-300 text-sm shadow-sm font-mono"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Tipo</label>
                            <select name="tipo_cuenta" class="rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">—</option>
                                <option value="CORRIENTE">Corriente</option>
                                <option value="AHORROS">Ahorros</option>
                                <option value="OTRO">Otro</option>
                            </select></div>
                        <label class="flex items-center gap-1 text-xs text-gray-600">
                            <input type="checkbox" name="principal" value="1" class="rounded border-gray-300"> Principal
                        </label>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                    </form>
                @endcan
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr><th class="px-4 py-3">Banco</th><th class="px-4 py-3">No. Cuenta</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cuentasBancarias as $cb)
                            <tr class="{{ $cb->principal ? 'bg-blue-50' : '' }}">
                                <td class="px-4 py-3 font-medium">{{ $cb->banco }} @if($cb->principal)<span class="ml-1 text-xs text-blue-600">★</span>@endif</td>
                                <td class="px-4 py-3 font-mono">{{ $cb->numero_cuenta }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $cb->tipo_cuenta ?? '—' }}</td>
                                <td class="px-4 py-3">@can('contactos.editar')
                                    <form method="POST" action="{{ route('admin.contactos.cuentas-bancarias.destroy', [$contacto, $cb]) }}" onsubmit="return confirm('¿Eliminar?')">@csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Eliminar</button>
                                    </form>@endcan</td>
                            </tr>
                        @empty<tr><td colspan="4" class="px-4 py-4 text-center text-gray-400 text-xs">Sin cuentas bancarias.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>

            {{-- Direcciones --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Direcciones</h3>
                </div>
                @can('contactos.editar')
                    <form method="POST" action="{{ route('admin.contactos.direcciones.store', $contacto) }}" class="px-4 py-3 bg-gray-50 flex flex-wrap gap-3 items-end border-b border-gray-100">
                        @csrf
                        <div><label class="block text-xs text-gray-500 mb-1">Tipo</label>
                            <select name="tipo" required class="rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="PRINCIPAL">Principal</option>
                                <option value="FACTURACION">Facturación</option>
                                <option value="ENTREGA">Entrega</option>
                                <option value="OTRO">Otro</option>
                            </select></div>
                        <div class="flex-1"><label class="block text-xs text-gray-500 mb-1">Dirección *</label>
                            <input type="text" name="direccion" required class="w-full rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Provincia</label>
                            <input type="text" name="provincia" maxlength="100" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Distrito</label>
                            <input type="text" name="distrito" maxlength="100" class="rounded-md border-gray-300 text-sm shadow-sm"></div>
                        <label class="flex items-center gap-1 text-xs text-gray-600">
                            <input type="checkbox" name="principal" value="1" class="rounded border-gray-300"> Principal
                        </label>
                        <button type="submit" class="rounded-md bg-[#0d2d5e] px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Agregar</button>
                    </form>
                @endcan
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Dirección</th><th class="px-4 py-3">Provincia</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($direcciones as $dir)
                            <tr class="{{ $dir->principal ? 'bg-blue-50' : '' }}">
                                <td class="px-4 py-3 text-xs font-medium">{{ $dir->tipo }} @if($dir->principal)<span class="ml-1 text-blue-600">★</span>@endif</td>
                                <td class="px-4 py-3">{{ $dir->direccion }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $dir->provincia ?? '—' }}, {{ $dir->pais ?? 'Panamá' }}</td>
                                <td class="px-4 py-3">@can('contactos.editar')
                                    <form method="POST" action="{{ route('admin.contactos.direcciones.destroy', [$contacto, $dir]) }}" onsubmit="return confirm('¿Eliminar?')">@csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Eliminar</button>
                                    </form>@endcan</td>
                            </tr>
                        @empty<tr><td colspan="4" class="px-4 py-4 text-center text-gray-400 text-xs">Sin direcciones registradas.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
