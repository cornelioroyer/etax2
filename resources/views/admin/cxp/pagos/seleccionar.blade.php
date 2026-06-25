<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar pago a proveedor</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg"
                 x-data="selProveedor({{ $proveedores->toJson() }})">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">Selecciona el proveedor a pagar</h3>
                    <p class="text-xs text-gray-500">Se muestra el saldo pendiente y el crédito a favor de cada proveedor. Haz clic en una fila para registrar su pago.</p>
                </div>

                {{-- Filtros --}}
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <input type="text" x-model="q" placeholder="Buscar por nombre, código o RUC…"
                           class="w-80 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" x-model="soloConSaldo" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Solo con saldo pendiente
                    </label>
                    <span class="ml-auto text-xs text-gray-500">
                        <span x-text="filtrados.length"></span> proveedor(es) ·
                        Total saldo: <span class="font-semibold text-gray-700" x-text="fmt(totalSaldo())"></span>
                    </span>
                </div>

                {{-- Tabla de proveedores --}}
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2 pr-2">Proveedor</th>
                                <th class="w-24 py-2 pr-2 text-center">Facturas</th>
                                <th class="w-40 py-2 pr-2 text-right">Saldo pendiente</th>
                                <th class="w-40 py-2 pr-2 text-right">Crédito a favor</th>
                                <th class="w-24 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="p in filtrados" :key="p.id">
                                <tr class="cursor-pointer border-t border-gray-100 hover:bg-blue-50/60" @click="window.location = p.url">
                                    <td class="py-2.5 pr-2">
                                        <div class="font-medium text-gray-800" x-text="p.nombre"></div>
                                        <div class="text-xs text-gray-400">
                                            <span x-text="p.codigo"></span>
                                            <span x-show="p.ruc" x-text="' · RUC ' + p.ruc"></span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 pr-2 text-center" :class="p.n_facturas > 0 ? 'text-gray-700' : 'text-gray-300'" x-text="p.n_facturas"></td>
                                    <td class="py-2.5 pr-2 text-right font-semibold" :class="p.saldo > 0 ? 'text-[#0d2d5e]' : 'text-gray-300'" x-text="fmt(p.saldo)"></td>
                                    <td class="py-2.5 pr-2 text-right" :class="p.credito > 0 ? 'text-emerald-700 font-medium' : 'text-gray-300'" x-text="fmt(p.credito)"></td>
                                    <td class="py-2.5 text-right">
                                        <a :href="p.url" @click.stop
                                           class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500">Pagar &rarr;</a>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filtrados.length === 0">
                                <td colspan="5" class="py-10 text-center text-gray-500">
                                    No hay proveedores que coincidan con el filtro.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot x-show="filtrados.length > 0" class="border-t-2 border-gray-200 font-semibold text-gray-800">
                            <tr>
                                <td class="py-3 pr-2 text-right">Totales (<span x-text="filtrados.length"></span> proveedores)</td>
                                <td class="py-3 pr-2 text-center" x-text="totalFacturas()"></td>
                                <td class="py-3 pr-2 text-right text-[#0d2d5e]" x-text="fmt(totalSaldo())"></td>
                                <td class="py-3 pr-2 text-right text-emerald-700" x-text="fmt(totalCredito())"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selProveedor(proveedores) {
            return {
                proveedores,
                q: '',
                soloConSaldo: true,
                get filtrados() {
                    const b = this.q.trim().toLowerCase();
                    return this.proveedores.filter(p => {
                        if (this.soloConSaldo && !(p.saldo > 0)) return false;
                        if (!b) return true;
                        return p.nombre.toLowerCase().includes(b)
                            || p.codigo.toLowerCase().includes(b)
                            || (p.ruc && p.ruc.toLowerCase().includes(b));
                    });
                },
                totalSaldo() { return this.filtrados.reduce((s, p) => s + (parseFloat(p.saldo) || 0), 0); },
                totalCredito() { return this.filtrados.reduce((s, p) => s + (parseFloat(p.credito) || 0), 0); },
                totalFacturas() { return this.filtrados.reduce((s, p) => s + (parseInt(p.n_facturas) || 0), 0); },
                fmt(v) { return 'B/. ' + (Math.round((parseFloat(v) || 0) * 100) / 100).toFixed(2); },
            };
        }
    </script>
</x-app-layout>
