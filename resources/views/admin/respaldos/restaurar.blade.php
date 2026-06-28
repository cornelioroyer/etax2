<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Restaurar respaldo en una compañía nueva
            </h2>
            <a href="{{ route('admin.respaldos.index') }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Volver a respaldos
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5 space-y-0.5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                La restauración crea una <strong>compañía nueva</strong> con los datos del respaldo
                (catálogo, asientos, clientes, proveedores, facturas, compras, inventario, bancos,
                caja, activos, etc.). No modifica ninguna compañía existente. Los catálogos globales
                (monedas, tipos de documento, bancos, usuarios) se comparten con la instancia actual.
            </div>

            {{-- Barra de progreso para la restauración en curso --}}
            @if ($enCurso)
                <div id="progreso" class="rounded-md border border-sky-200 bg-white px-4 py-4 shadow-sm"
                     data-estado-url="{{ route('admin.restauraciones.estado', $enCurso) }}">
                    <div class="flex items-center justify-between text-sm text-slate-700">
                        <span id="progreso-texto">Preparando restauración…</span>
                        <span id="progreso-pct">0%</span>
                    </div>
                    <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
                        <div id="progreso-barra" class="h-2.5 rounded-full bg-sky-600 transition-all" style="width:0%"></div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.restauraciones.store') }}"
                  enctype="multipart/form-data" x-data="{ tipo: '{{ old('origen_tipo', 'respaldo') }}' }"
                  onsubmit="return confirm('¿Restaurar este respaldo en una compañía nueva?');"
                  class="space-y-5 rounded-md border border-slate-200 bg-white px-5 py-5 shadow-sm">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Origen del respaldo</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="radio" name="origen_tipo" value="respaldo" x-model="tipo">
                            Desde un respaldo del sistema
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="radio" name="origen_tipo" value="archivo" x-model="tipo">
                            Subir un archivo .zip
                        </label>
                    </div>
                </div>

                <div x-show="tipo === 'respaldo'" x-cloak>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Respaldo</label>
                    <select name="respaldo_id"
                            class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">— Seleccione un respaldo —</option>
                        @foreach ($respaldos as $r)
                            <option value="{{ $r->id }}" {{ (int) old('respaldo_id') === $r->id ? 'selected' : '' }}>
                                {{ $r->compania_nombre }} · {{ $r->created_at?->format('d/m/Y H:i') }}
                                · {{ number_format($r->total_filas) }} filas · {{ $r->tamanoLegible() }}
                            </option>
                        @endforeach
                    </select>
                    @if ($respaldos->isEmpty())
                        <p class="mt-1 text-xs text-slate-500">No hay respaldos completados en el sistema. Genere uno primero o suba un .zip.</p>
                    @endif
                </div>

                <div x-show="tipo === 'archivo'" x-cloak>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Archivo .zip de respaldo</label>
                    <input type="file" name="archivo" accept=".zip"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                    <p class="mt-1 text-xs text-slate-500">Solo respaldos generados por eTax2.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la compañía nueva</label>
                    <input type="text" name="compania_destino_nombre" maxlength="255" required
                           value="{{ old('compania_destino_nombre') }}"
                           placeholder="Ej.: MI EMPRESA (restaurado)"
                           class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div class="flex justify-end">
                    <button type="submit" {{ $enCurso ? 'disabled' : '' }}
                        class="inline-flex items-center gap-1.5 rounded-md border border-sky-300 bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ $enCurso ? 'Restauración en proceso…' : 'Restaurar' }}
                    </button>
                </div>
            </form>

            {{-- Historial de restauraciones --}}
            <div class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2">Origen</th>
                            <th class="px-4 py-2">Compañía creada</th>
                            <th class="px-4 py-2 text-right">Filas</th>
                            <th class="px-4 py-2">Usuario</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($restauraciones as $r)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-slate-700">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2">
                                    @php
                                        $badge = match ($r->estado) {
                                            'COMPLETADO' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'FALLIDO' => 'border-red-200 bg-red-50 text-red-700',
                                            'PROCESANDO' => 'border-sky-200 bg-sky-50 text-sky-700',
                                            default => 'border-slate-200 bg-slate-50 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $r->estado }}</span>
                                    @if ($r->estado === 'FALLIDO' && $r->mensaje_error)
                                        <p class="mt-1 text-xs text-red-600">{{ \Illuminate\Support\Str::limit($r->mensaje_error, 140) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $r->origen ?: '—' }}</td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ $r->compania_destino_nombre }}
                                    @if ($r->compania_destino_id)
                                        <span class="text-xs text-slate-400">#{{ $r->compania_destino_id }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-slate-700">{{ number_format($r->total_filas) }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $r->usuario }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aún no hay restauraciones.</td>
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
                            ? `Restaurando… ${d.tablas_procesadas}/${d.total_tablas} tablas · ${Number(d.total_filas).toLocaleString()} filas`
                            : 'Preparando restauración…';
                        if (d.terminado) { window.location.reload(); return; }
                    } catch (e) { /* reintenta */ }
                    setTimeout(tick, 2000);
                };
                tick();
            })();
        </script>
    @endif
</x-app-layout>
