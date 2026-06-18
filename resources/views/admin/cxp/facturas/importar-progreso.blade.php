<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Importando compras desde la DGI</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8"
             x-data="importProgreso({{ $importacion->id }}, '{{ route('admin.cxp.facturas.importar.estado', $importacion) }}', '{{ route('admin.cxp.facturas.index') }}')"
             x-init="iniciar()">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-sm text-gray-600">
                        Archivo: <span class="font-medium text-gray-900">{{ $importacion->archivo }}</span>
                    </p>
                    <span class="text-sm font-semibold" x-text="etiquetaEstado"></span>
                </div>

                <p class="text-xs text-gray-500 mb-4">
                    Cada factura se consulta en la DGI por su CUFE para traer sus líneas reales; esto puede tardar.
                </p>

                <!-- Barra de progreso -->
                <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                    <div class="h-4 rounded-full transition-all duration-300"
                         :class="estado === 'FALLIDO' ? 'bg-red-500' : 'bg-blue-600'"
                         :style="`width: ${porcentaje}%`"></div>
                </div>

                <div class="flex items-center justify-between mt-2 text-sm text-gray-600">
                    <span x-text="`${procesadas} de ${total || '?'} filas`"></span>
                    <span x-text="`${porcentaje}%`"></span>
                </div>

                <!-- Contadores -->
                <div class="grid grid-cols-3 gap-3 mt-6">
                    <div class="rounded-lg bg-green-50 p-3 text-center">
                        <div class="text-2xl font-bold text-green-700" x-text="creadas"></div>
                        <div class="text-xs text-gray-600">creadas</div>
                    </div>
                    <div class="rounded-lg bg-blue-50 p-3 text-center">
                        <div class="text-2xl font-bold text-blue-700" x-text="con_detalle"></div>
                        <div class="text-xs text-gray-600">con detalle DGI</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 text-center">
                        <div class="text-2xl font-bold text-gray-700" x-text="omitidas"></div>
                        <div class="text-xs text-gray-600">omitidas (duplicadas)</div>
                    </div>
                </div>

                <!-- Mensaje de error fatal -->
                <div x-show="mensaje_error" x-cloak class="mt-5 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <span class="font-semibold">Error:</span> <span x-text="mensaje_error"></span>
                </div>

                <!-- Errores por fila -->
                <div x-show="errores.length > 0" x-cloak class="mt-5">
                    <p class="text-sm font-semibold text-gray-700 mb-1" x-text="`Avisos (${errores.length})`"></p>
                    <ul class="text-xs text-gray-600 list-disc pl-5 space-y-0.5 max-h-40 overflow-auto">
                        <template x-for="(e, idx) in errores" :key="idx">
                            <li x-text="e"></li>
                        </template>
                    </ul>
                </div>

                <!-- Acciones al terminar -->
                <div class="mt-6 flex justify-end gap-3">
                    <a :href="indexUrl"
                       class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Volver a facturas
                    </a>
                    <a x-show="estado === 'COMPLETADO'" x-cloak :href="indexUrl"
                       class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">
                        Ver facturas importadas
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function importProgreso(id, estadoUrl, indexUrl) {
            return {
                estadoUrl, indexUrl,
                estado: 'PENDIENTE',
                total: {{ $importacion->total }},
                procesadas: 0, creadas: 0, con_detalle: 0, omitidas: 0,
                porcentaje: 0, errores: [], mensaje_error: null,
                timer: null,
                get etiquetaEstado() {
                    return {
                        PENDIENTE: 'En cola…',
                        PROCESANDO: 'Procesando…',
                        COMPLETADO: 'Completado',
                        FALLIDO: 'Falló',
                    }[this.estado] ?? this.estado;
                },
                iniciar() {
                    this.consultar();
                    this.timer = setInterval(() => this.consultar(), 1500);
                },
                async consultar() {
                    try {
                        const r = await fetch(this.estadoUrl, { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) return;
                        const d = await r.json();
                        this.estado = d.estado;
                        this.total = d.total;
                        this.procesadas = d.procesadas;
                        this.creadas = d.creadas;
                        this.con_detalle = d.con_detalle;
                        this.omitidas = d.omitidas;
                        this.porcentaje = d.porcentaje;
                        this.errores = d.errores || [];
                        this.mensaje_error = d.mensaje_error;
                        if (d.terminada && this.timer) {
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                    } catch (e) { /* reintenta en el próximo tick */ }
                },
            };
        }
    </script>
</x-app-layout>
