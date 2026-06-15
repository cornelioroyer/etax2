<x-app-layout>
    @php
        $antes = $registro->valores_anteriores ?? [];
        $despues = $registro->valores_nuevos ?? [];
        $claves = collect(array_keys($antes + $despues))->unique()->values();
        $fmt = function ($v) {
            if (is_null($v)) return '—';
            if (is_bool($v)) return $v ? 'Sí' : 'No';
            if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
            return (string) $v;
        };
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-4">
            <a href="{{ url()->previous() }}" class="text-sm font-semibold" style="color:#005293">&larr; Volver</a>
        </div>

        <div class="mx-auto max-w-4xl space-y-6">
            {{-- Cabecera del evento --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-xl font-bold text-slate-900">{{ $registro->evento_label }} · {{ $registro->entidad }}</h1>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-400">Usuario</dt><dd class="font-medium text-slate-800">{{ $registro->usuario?->name ?: $registro->usuario_nombre ?: '—' }}</dd></div>
                    <div><dt class="text-slate-400">Fecha y hora</dt><dd class="font-medium text-slate-800">@fechaHora($registro->created_at)</dd></div>
                    <div><dt class="text-slate-400">Compañía</dt><dd class="font-medium text-slate-800">{{ $compania->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-slate-400">Registro</dt><dd class="font-medium text-slate-800">{{ $registro->entidad_tabla }} #{{ $registro->entidad_id ?? '—' }}</dd></div>
                    <div><dt class="text-slate-400">IP</dt><dd class="font-medium text-slate-800">{{ $registro->ip ?: '—' }}</dd></div>
                    <div><dt class="text-slate-400">URL</dt><dd class="font-medium text-slate-800 break-all">{{ $registro->url ?: '—' }}</dd></div>
                </dl>
            </div>

            {{-- Diff antes/después --}}
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead style="background-color:#0d2d5e">
                        <tr class="text-left text-xs uppercase tracking-wide text-white">
                            <th class="px-4 py-3">Campo</th>
                            <th class="px-4 py-3">Antes</th>
                            <th class="px-4 py-3">Después</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($claves as $clave)
                            @php $cambio = ($fmt($antes[$clave] ?? null) !== $fmt($despues[$clave] ?? null)); @endphp
                            <tr class="{{ $cambio ? '' : 'text-slate-400' }}">
                                <td class="px-4 py-2 font-medium text-slate-700">{{ $clave }}</td>
                                <td class="px-4 py-2 {{ $cambio ? 'text-red-700' : '' }}">{{ $fmt($antes[$clave] ?? null) }}</td>
                                <td class="px-4 py-2 {{ $cambio ? 'font-semibold text-green-700' : '' }}">{{ $fmt($despues[$clave] ?? null) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Sin detalle de valores.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
