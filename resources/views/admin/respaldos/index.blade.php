<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Respaldo de datos — {{ $companiaActiva->nombre ?? '' }}
            </h2>
            <div class="flex items-center gap-2">
                @if (auth()->user()?->is_admin)
                    <a href="{{ route('admin.restauraciones.form') }}"
                       class="inline-flex items-center gap-1.5 rounded-md border border-sky-300 bg-white px-3 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5M21 7.5v9A2.25 2.25 0 0 1 18.75 18.75H5.25" />
                        </svg>
                        Restaurar
                    </a>
                @endif
                <form method="POST" action="{{ route('admin.respaldos.store') }}"
                      onsubmit="return confirm('¿Generar un respaldo de los datos de esta compañía?');">
                    @csrf
                    <button type="submit" {{ $enCurso ? 'disabled' : '' }}
                        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ $enCurso ? 'Respaldo en proceso…' : 'Generar respaldo' }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                El respaldo contiene <strong>únicamente los datos de esta compañía</strong> en un archivo
                ZIP (formato JSON, restaurable). Incluye catálogo de cuentas, asientos, clientes,
                proveedores, facturas, compras, inventario, bancos, caja, activos y demás módulos.
                Guarde el archivo en un lugar seguro: es información confidencial de la empresa.
            </div>

            {{-- Barra de progreso para el respaldo en curso --}}
            @if ($enCurso)
                <div id="progreso" class="rounded-md border border-sky-200 bg-white px-4 py-4 shadow-sm"
                     data-estado-url="{{ route('admin.respaldos.estado', $enCurso) }}">
                    <div class="flex items-center justify-between text-sm text-slate-700">
                        <span id="progreso-texto">Preparando respaldo…</span>
                        <span id="progreso-pct">0%</span>
                    </div>
                    <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
                        <div id="progreso-barra" class="h-2.5 rounded-full bg-sky-600 transition-all" style="width:0%"></div>
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2 text-right">Filas</th>
                            <th class="px-4 py-2 text-right">Tamaño</th>
                            <th class="px-4 py-2">Usuario</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($respaldos as $r)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-slate-700">
                                    {{ $r->created_at?->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-2">
                                    @php
                                        $badge = match ($r->estado) {
                                            'COMPLETADO' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'FALLIDO' => 'border-red-200 bg-red-50 text-red-700',
                                            'PROCESANDO' => 'border-sky-200 bg-sky-50 text-sky-700',
                                            default => 'border-slate-200 bg-slate-50 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $badge }}">
                                        {{ $r->estado }}
                                    </span>
                                    @if ($r->estado === 'FALLIDO' && $r->mensaje_error)
                                        <p class="mt-1 text-xs text-red-600">{{ \Illuminate\Support\Str::limit($r->mensaje_error, 120) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-slate-700">
                                    {{ number_format($r->total_filas) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-slate-700">
                                    {{ $r->tamanoLegible() }}
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $r->usuario }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($r->estado === 'COMPLETADO')
                                            <a href="{{ route('admin.respaldos.download', $r) }}"
                                               class="inline-flex items-center gap-1 rounded-md border border-sky-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50">
                                                Descargar
                                            </a>
                                        @endif
                                        @if ($r->terminado())
                                            <form method="POST" action="{{ route('admin.respaldos.destroy', $r) }}"
                                                  onsubmit="return confirm('¿Eliminar este respaldo?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-slate-500">
                                    Aún no hay respaldos. Genere el primero con el botón de arriba.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($enCurso)
        <script>
            (function () {
                const cont = document.getElementById('progreso');
                if (!cont) return;
                const url = cont.dataset.estadoUrl;
                const barra = document.getElementById('progreso-barra');
                const pct = document.getElementById('progreso-pct');
                const texto = document.getElementById('progreso-texto');

                const tick = async () => {
                    try {
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const d = await r.json();
                        barra.style.width = d.porcentaje + '%';
                        pct.textContent = d.porcentaje + '%';
                        texto.textContent = d.estado === 'PROCESANDO'
                            ? `Exportando… ${d.tablas_procesadas}/${d.total_tablas} tablas · ${Number(d.total_filas).toLocaleString()} filas`
                            : 'Preparando respaldo…';
                        if (d.terminado) {
                            window.location.reload();
                            return;
                        }
                    } catch (e) { /* reintenta */ }
                    setTimeout(tick, 2000);
                };
                tick();
            })();
        </script>
    @endif
</x-app-layout>
