<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Caja {{ $caja->codigo }} — {{ $caja->nombre }}</h2>
            <div class="flex items-center gap-3">
                <x-help-button module="caja" />
                <a href="{{ route('admin.caja.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Saldo y configuración --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div class="text-sm text-gray-500">Saldo en sistema</div>
                        <div class="text-3xl font-bold {{ $saldo < 0 ? 'text-red-600' : 'text-gray-800' }}">B/. {{ number_format($saldo, 2) }}</div>
                        <div class="mt-1 text-xs text-gray-500">Cuenta de efectivo: {{ $caja->cuentaContable?->codigo ?? '— sin asignar —' }}</div>
                    </div>
                    @can('caja.gestionar')
                        <form method="POST" action="{{ route('admin.caja.toggle', $caja) }}">
                            @csrf
                            <button class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                {{ $caja->activa ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                    @endcan
                </div>

                @can('caja.gestionar')
                    <form method="POST" action="{{ route('admin.caja.update', $caja) }}" class="mt-4 grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-3">
                        @csrf @method('PUT')
                        <div>
                            <x-input-label for="nombre" value="Nombre" />
                            <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="$caja->nombre" required />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="cuenta_contable_id" value="Cuenta de efectivo (GL)" />
                            <div class="flex gap-2">
                                <select id="cuenta_contable_id" name="cuenta_contable_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Cuenta contable —</option>
                                    @foreach ($cuentas as $cuenta)
                                        <option value="{{ $cuenta->id }}" @selected($caja->cuenta_contable_id == $cuenta->id)>{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                    @endforeach
                                </select>
                                <button class="mt-1 rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-700 hover:bg-gray-50">Guardar</button>
                            </div>
                        </div>
                    </form>
                @endcan
            </div>

            @can('caja.gestionar')
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {{-- Movimiento --}}
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Registrar movimiento</h3>
                        <form method="POST" action="{{ route('admin.caja.movimiento', $caja) }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label value="Tipo *" />
                                    <select name="tipo_movimiento" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="EGRESO">Egreso (gasto)</option>
                                        <option value="INGRESO">Ingreso</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label value="Fecha *" />
                                    <x-text-input name="fecha" type="text" class="js-date mt-1 block w-full" :value="now()->format('Y-m-d')" required />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label value="Monto *" />
                                    <x-text-input name="monto" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                                </div>
                                <div>
                                    <x-input-label value="Beneficiario" />
                                    <x-text-input name="beneficiario" type="text" class="mt-1 block w-full" />
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Cuenta contrapartida (gasto/origen) *" />
                                <select name="cuenta_contable_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                                    <option value="">— Cuenta —</option>
                                    @foreach ($cuentas as $cuenta)
                                        <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label value="ITBMS incluido" />
                                    <x-text-input name="itbms_monto" type="number" step="0.01" min="0" class="mt-1 block w-full" placeholder="0.00" />
                                    <p class="mt-1 text-xs text-gray-500">Crédito fiscal (solo egreso). Va incluido en el monto.</p>
                                </div>
                                <div>
                                    <x-input-label value="N° comprobante" />
                                    <x-text-input name="documento_ref" type="text" class="mt-1 block w-full" placeholder="Factura / recibo" maxlength="60" />
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Descripción" />
                                <x-text-input name="descripcion" type="text" class="mt-1 block w-full" />
                            </div>
                            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Registrar y contabilizar</button>
                        </form>
                    </div>

                    {{-- Reembolso + Vale --}}
                    <div class="space-y-4">
                        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 class="mb-3 text-sm font-semibold text-gray-700">Reembolso (reposición del fondo)</h3>
                            <form method="POST" action="{{ route('admin.caja.reembolso', $caja) }}" class="space-y-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label value="Fecha *" />
                                        <x-text-input name="fecha" type="text" class="js-date mt-1 block w-full" :value="now()->format('Y-m-d')" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Monto *" />
                                        <x-text-input name="monto" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Cuenta de origen (banco) *" />
                                    <select name="cuenta_banco_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                                        <option value="">— Cuenta —</option>
                                        @foreach ($cuentas as $cuenta)
                                            <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Registrar reembolso</button>
                            </form>
                        </div>

                        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 class="mb-3 text-sm font-semibold text-gray-700">Vale (adelanto)</h3>
                            <form method="POST" action="{{ route('admin.caja.vale', $caja) }}" class="space-y-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label value="Fecha *" />
                                        <x-text-input name="fecha" type="text" class="js-date mt-1 block w-full" :value="now()->format('Y-m-d')" required />
                                    </div>
                                    <div>
                                        <x-input-label value="Monto *" />
                                        <x-text-input name="monto" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Beneficiario *" />
                                    <x-text-input name="beneficiario" type="text" class="mt-1 block w-full" required />
                                </div>
                                <div>
                                    <x-input-label value="Motivo" />
                                    <x-text-input name="motivo" type="text" class="mt-1 block w-full" />
                                </div>
                                <button class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">Registrar vale</button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Vales pendientes --}}
                @php $valesPendientes = $caja->vales->where('estado', 'PENDIENTE'); @endphp
                @if ($valesPendientes->isNotEmpty())
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Vales pendientes de liquidar</h3>
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr><th class="py-2 pr-2">Fecha</th><th class="py-2 pr-2">Beneficiario</th><th class="py-2 pr-2 text-right">Monto</th><th class="py-2 pr-2">Liquidar</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($valesPendientes as $vale)
                                    <tr class="border-t border-gray-100 align-middle">
                                        <td class="py-2 pr-2">{{ $vale->fecha->format('d/m/Y') }}</td>
                                        <td class="py-2 pr-2">{{ $vale->beneficiario }}<div class="text-xs text-gray-500">{{ $vale->motivo }}</div></td>
                                        <td class="py-2 pr-2 text-right">{{ number_format((float) $vale->monto, 2) }}</td>
                                        <td class="py-2 pr-2">
                                            <form method="POST" action="{{ route('admin.caja.vale.liquidar', $vale) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="fecha" value="{{ now()->format('Y-m-d') }}">
                                                <select name="cuenta_contable_id" class="rounded-md border-gray-300 text-xs shadow-sm" required>
                                                    <option value="">— Cuenta gasto —</option>
                                                    @foreach ($cuentas as $cuenta)
                                                        <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="number" name="itbms_monto" step="0.01" min="0" placeholder="ITBMS" title="ITBMS incluido (crédito fiscal)" class="w-20 rounded-md border-gray-300 text-xs shadow-sm">
                                                <input type="text" name="documento_ref" maxlength="60" placeholder="N° comp." title="N° de comprobante" class="w-24 rounded-md border-gray-300 text-xs shadow-sm">
                                                <button class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">Liquidar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Arqueo --}}
                <div class="bg-white p-6 shadow-sm sm:rounded-lg" x-data="arqueoForm()">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Arqueo de caja</h3>
                    <form method="POST" action="{{ route('admin.caja.arqueo', $caja) }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Fecha *" />
                                <x-text-input name="fecha" type="text" class="js-date mt-1 block w-full" :value="now()->format('Y-m-d')" required />
                            </div>
                        </div>
                        <table class="mt-4 min-w-full text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr><th class="py-2 pr-2 w-40">Denominación</th><th class="py-2 pr-2 w-32">Cantidad</th><th class="py-2 pr-2 text-right">Total</th><th class="w-8"></th></tr>
                            </thead>
                            <tbody>
                                <template x-for="(d, idx) in filas" :key="idx">
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 pr-2">
                                            <input type="number" step="0.01" min="0.01" :name="`denominaciones[${idx}][denominacion]`" x-model.number="d.denominacion" required
                                                   class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm">
                                        </td>
                                        <td class="py-2 pr-2">
                                            <input type="number" min="0" :name="`denominaciones[${idx}][cantidad]`" x-model.number="d.cantidad" required
                                                   class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm">
                                        </td>
                                        <td class="py-2 pr-2 text-right" x-text="fmt((d.denominacion||0)*(d.cantidad||0))"></td>
                                        <td class="py-2 text-right"><button type="button" @click="filas.splice(idx,1)" x-show="filas.length>1" class="text-red-500 hover:text-red-700">✕</button></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="border-t-2 border-gray-200">
                                <tr class="font-semibold"><td colspan="2" class="py-2 pr-2 text-right text-gray-700">Total físico</td><td class="py-2 pr-2 text-right" x-text="fmt(totalFisico())"></td><td></td></tr>
                                <tr><td colspan="2" class="py-1 pr-2 text-right text-gray-500">Saldo sistema</td><td class="py-1 pr-2 text-right text-gray-500">{{ number_format($saldo, 2) }}</td><td></td></tr>
                                <tr><td colspan="2" class="py-1 pr-2 text-right text-gray-500">Diferencia</td><td class="py-1 pr-2 text-right" :class="(totalFisico()-{{ $saldo }})==0 ? 'text-green-600' : 'text-red-600'" x-text="fmt(totalFisico()-{{ $saldo }})"></td><td></td></tr>
                            </tfoot>
                        </table>
                        <div class="mt-3 flex items-center gap-3">
                            <button type="button" @click="filas.push({denominacion:0,cantidad:0})" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">+ Denominación</button>
                            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Registrar arqueo</button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- Historial de movimientos --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Movimientos</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr><th class="py-2 pr-2">Fecha</th><th class="py-2 pr-2">Tipo</th><th class="py-2 pr-2">Detalle</th><th class="py-2 pr-2">Cuenta</th><th class="py-2 pr-2 text-right">Monto</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($caja->movimientos as $mov)
                            <tr class="border-t border-gray-100">
                                <td class="py-2 pr-2 whitespace-nowrap">{{ $mov->fecha->format('d/m/Y') }}</td>
                                <td class="py-2 pr-2">
                                    @if ($mov->tipo_movimiento === 'EGRESO')
                                        <span class="text-red-600">Egreso</span>
                                    @else
                                        <span class="text-green-600">Ingreso</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-2">
                                    {{ $mov->descripcion ?? $mov->beneficiario ?? '—' }}
                                    @if ($mov->documento_ref)<span class="text-xs text-gray-400">· {{ $mov->documento_ref }}</span>@endif
                                    @if ((float) $mov->itbms_monto > 0)<div class="text-xs text-gray-400">ITBMS: B/. {{ number_format((float) $mov->itbms_monto, 2) }}</div>@endif
                                </td>
                                <td class="py-2 pr-2 text-gray-500">{{ $mov->cuentaContable?->codigo }}</td>
                                <td class="py-2 pr-2 text-right {{ $mov->tipo_movimiento === 'EGRESO' ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $mov->tipo_movimiento === 'EGRESO' ? '-' : '+' }}{{ number_format((float) $mov->monto, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-500">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Reembolsos y arqueos --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Reembolsos</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="py-2 pr-2">Fecha</th><th class="py-2 pr-2 text-right">Monto</th></tr></thead>
                        <tbody>
                            @forelse ($caja->reembolsos as $r)
                                <tr class="border-t border-gray-100"><td class="py-2 pr-2">{{ $r->fecha->format('d/m/Y') }}</td><td class="py-2 pr-2 text-right">{{ number_format((float) $r->monto, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="py-6 text-center text-gray-500">Sin reembolsos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Arqueos</h3>
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><tr><th class="py-2 pr-2">Fecha</th><th class="py-2 pr-2 text-right">Físico</th><th class="py-2 pr-2 text-right">Diferencia</th></tr></thead>
                        <tbody>
                            @forelse ($caja->arqueos as $a)
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-2">{{ $a->fecha->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-2 text-right">{{ number_format((float) $a->saldo_fisico, 2) }}</td>
                                    <td class="py-2 pr-2 text-right {{ (float) $a->diferencia == 0.0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format((float) $a->diferencia, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-gray-500">Sin arqueos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function arqueoForm() {
            return {
                filas: [{ denominacion: 20, cantidad: 0 }, { denominacion: 10, cantidad: 0 }, { denominacion: 5, cantidad: 0 }, { denominacion: 1, cantidad: 0 }],
                totalFisico() { return this.filas.reduce((s, d) => s + (parseFloat(d.denominacion) || 0) * (parseInt(d.cantidad) || 0), 0); },
                fmt(v) { return 'B/. ' + (Math.round(v * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
