<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Anticipo {{ $anticipo->numero }}</h2>
            <a href="{{ route('admin.cxp.anticipos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <dl class="grid grid-cols-2 gap-x-10 gap-y-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-gray-500">Proveedor</dt>
                            <dd class="font-medium text-gray-900">{{ $anticipo->proveedor->nombre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Fecha</dt>
                            <dd class="font-medium text-gray-900">{{ $anticipo->fecha->format('d/m/Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Estado</dt>
                            <dd>@include('admin.cxc._estado', ['estado' => $anticipo->estado])</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Asiento</dt>
                            <dd class="font-medium">
                                @if ($anticipo->asiento)
                                    <a href="{{ route('admin.asientos.show', $anticipo->asiento) }}" class="text-blue-700 hover:underline">{{ $anticipo->asiento->numero }}</a>
                                @else — @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Monto</dt>
                            <dd class="text-lg font-bold text-[#0d2d5e]">B/. {{ number_format((float) $anticipo->total, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Disponible</dt>
                            <dd class="text-lg font-bold text-emerald-700">B/. {{ number_format((float) $anticipo->saldo, 2) }}</dd>
                        </div>
                    </dl>

                    @can('cxp.gestionar')
                        @if (! $anticipo->esAnulado())
                            <form method="POST" action="{{ route('admin.cxp.anticipos.anular', $anticipo) }}"
                                  onsubmit="return confirm('¿Anular el anticipo {{ $anticipo->numero }}? Se revertirán sus aplicaciones y asientos.');">
                                @csrf
                                <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                    Anular anticipo
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            {{-- Aplicar disponible a facturas --}}
            @can('cxp.gestionar')
                @if (! $anticipo->esAnulado() && (float) $anticipo->saldo > 0)
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Aplicar disponible a facturas</h3>
                        @if ($facturas->isEmpty())
                            <p class="text-sm text-gray-500">El proveedor no tiene facturas con saldo pendiente.</p>
                        @else
                            <form method="POST" action="{{ route('admin.cxp.anticipos.aplicar', $anticipo) }}"
                                  x-data="aplicarAnticipo({{ $facturas->map(fn ($f) => ['id' => $f->id, 'numero' => $f->numero, 'fecha' => $f->fecha->format('d/m/Y'), 'saldo' => (float) $f->saldo, 'monto' => 0])->toJson() }}, {{ (float) $anticipo->saldo }})">
                                @csrf
                                <div class="mb-4 max-w-xs">
                                    <x-input-label for="fecha" value="Fecha de aplicación *" />
                                    <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full" required
                                                  :value="old('fecha', now()->format('Y-m-d'))" />
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            <tr>
                                                <th class="py-2 pr-2">Factura</th>
                                                <th class="py-2 pr-2">Fecha</th>
                                                <th class="py-2 pr-2 text-right">Saldo</th>
                                                <th class="w-40 py-2 pr-2 text-right">Monto a aplicar</th>
                                                <th class="w-20 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(factura, idx) in facturas" :key="factura.id">
                                                <tr class="border-t border-gray-100">
                                                    <td class="py-2 pr-2 font-medium" x-text="factura.numero"></td>
                                                    <td class="py-2 pr-2" x-text="factura.fecha"></td>
                                                    <td class="py-2 pr-2 text-right" x-text="fmt(factura.saldo)"></td>
                                                    <td class="py-2 pr-2">
                                                        <input type="hidden" :name="`aplicaciones[${idx}][documento_id]`" :value="factura.id">
                                                        <input type="number" step="0.01" min="0" :max="factura.saldo"
                                                               :name="`aplicaciones[${idx}][monto]`" x-model.number="factura.monto"
                                                               class="block w-full rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </td>
                                                    <td class="py-2 text-right">
                                                        <button type="button" class="text-xs text-blue-700 hover:underline"
                                                                @click="factura.monto = Math.min(factura.saldo, restante() + (parseFloat(factura.monto)||0))">Máx</button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <tfoot class="border-t-2 border-gray-200 font-semibold">
                                            <tr>
                                                <td colspan="3" class="py-2 pr-2 text-right text-gray-700">Total a aplicar</td>
                                                <td class="py-2 pr-2 text-right" :class="excede() ? 'text-red-600' : ''" x-text="fmt(total())"></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="py-1 pr-2 text-right text-gray-500">Disponible tras aplicar</td>
                                                <td class="py-1 pr-2 text-right" x-text="fmt(restante())"></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="mt-4 flex items-center gap-3 border-t border-gray-100 pt-4">
                                    <button type="submit" :disabled="total() <= 0 || excede()"
                                            class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                                        Aplicar a facturas
                                    </button>
                                    <p class="text-xs text-gray-500">Asiento: débito a Cuentas por Pagar, crédito a Anticipos a proveedores.</p>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif
            @endcan

            {{-- Historial de aplicaciones --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Aplicaciones</h3>
                @if ($anticipo->aplicacionesComoOrigen->isEmpty())
                    <p class="text-sm text-gray-500">Este anticipo aún no se ha aplicado a ninguna factura.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Factura</th>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4 text-right">Monto aplicado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($anticipo->aplicacionesComoOrigen as $aplicacion)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <a href="{{ route('admin.cxp.facturas.show', $aplicacion->destino) }}" class="text-blue-700 hover:underline">{{ $aplicacion->destino->numero }}</a>
                                    </td>
                                    <td class="py-2 pr-4">{{ $aplicacion->fecha->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 text-right">B/. {{ number_format((float) $aplicacion->monto_aplicado, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <script>
        function aplicarAnticipo(facturas, disponible) {
            return {
                facturas,
                disponible,
                total() { return Math.round(this.facturas.reduce((s, f) => s + (parseFloat(f.monto) || 0), 0) * 100) / 100; },
                restante() { return Math.round((this.disponible - this.total()) * 100) / 100; },
                excede() { return this.total() > this.disponible + 0.004; },
                fmt(v) { return 'B/. ' + (Math.round(v * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
