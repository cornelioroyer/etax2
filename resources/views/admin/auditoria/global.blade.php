<x-app-layout>
    @php
        $badge = [
            'created' => 'background:#dcfce7;color:#166534',
            'updated' => 'background:#dbeafe;color:#1e40af',
            'deleted' => 'background:#fee2e2;color:#991b1b',
            'login' => 'background:#e0e7ff;color:#3730a3',
            'logout' => 'background:#f1f5f9;color:#475569',
            'login_fallido' => 'background:#fef3c7;color:#92400e',
        ];
    @endphp

    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Encabezado + export --}}
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Auditoría global</h1>
                <p class="text-sm text-slate-500">
                    Actividad de <span class="font-semibold" style="color:#005293">todas las compañías</span>:
                    creaciones, ediciones, borrados y accesos. (Solo super administrador.)
                </p>
            </div>
            <div class="flex items-end gap-2">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
                   style="background-color:#d21034;color:#fff"
                   class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold hover:opacity-90">PDF</a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"
                   style="background-color:#1d6f42;color:#fff"
                   class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold hover:opacity-90">Excel</a>
            </div>
        </div>

        {{-- Filtros --}}
        <form method="GET" class="mb-5 grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3 lg:grid-cols-6">
            <label class="text-xs font-medium text-slate-600">
                Compañía
                <select name="compania_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                    <option value="">Todas</option>
                    @foreach ($companias as $c)
                        <option value="{{ $c->id }}" @selected(($filtros['compania_id'] ?? null) == $c->id)>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">
                Usuario
                <select name="usuario_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                    <option value="">Todos</option>
                    @foreach ($usuarios as $u)
                        <option value="{{ $u->id }}" @selected(($filtros['usuario_id'] ?? null) == $u->id)>{{ $u->name ?: $u->email }}</option>
                    @endforeach
                </select>
            </label>
            <div class="text-xs font-medium text-slate-600"
                 x-data="{ open: false, sel: @js(array_map('strval', (array) ($filtros['usuario_excluir_id'] ?? []))) }">
                Excluir usuario(s)
                <div class="relative mt-1">
                    <button type="button" @click="open = ! open" @click.outside="open = false"
                            class="flex w-full items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                        <span class="truncate" x-text="sel.length ? sel.length + ' usuario(s)' : '— Ninguno —'"></span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M6 8l4 4 4-4"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                        @forelse ($usuarios as $u)
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm font-normal text-slate-700 hover:bg-slate-50">
                                <input type="checkbox" name="usuario_excluir_id[]" value="{{ $u->id }}" x-model="sel"
                                       class="rounded border-slate-300 text-[#0d2d5e] focus:ring-[#0d2d5e]">
                                <span class="truncate">{{ $u->name ?: $u->email }}</span>
                            </label>
                        @empty
                            <p class="px-3 py-1.5 text-sm text-slate-400">Sin usuarios.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <label class="text-xs font-medium text-slate-600">
                Acción
                <select name="evento" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                    <option value="">Todas</option>
                    @foreach ($etiquetas as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filtros['evento'] ?? null) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">
                Módulo
                <select name="entidad" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                    <option value="">Todos</option>
                    @foreach ($entidades as $ent)
                        <option value="{{ $ent }}" @selected(($filtros['entidad'] ?? null) === $ent)>{{ $ent }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">
                Desde
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
            </label>
            <label class="text-xs font-medium text-slate-600">
                Hasta
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                       class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
            </label>
            <label class="text-xs font-medium text-slate-600 lg:col-span-2">
                Buscar
                <div class="mt-1 flex gap-2">
                    <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="texto…"
                           class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-[#0d2d5e] focus:ring-[#0d2d5e]">
                    <button type="submit" class="rounded-md bg-[#0d2d5e] px-3 py-2 text-sm font-semibold text-white hover:opacity-90">Filtrar</button>
                </div>
            </label>
        </form>

        {{-- Tabla --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead style="background-color:#0d2d5e">
                    <tr class="text-left text-xs uppercase tracking-wide text-white">
                        <th class="px-4 py-3">Fecha y hora</th>
                        <th class="px-4 py-3">Compañía</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Acción</th>
                        <th class="px-4 py-3">Detalle</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($registros as $r)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">@fechaHora($r->created_at)</td>
                            <td class="px-4 py-2 text-slate-600">{{ $r->compania_id ? ($companiasMap[$r->compania_id] ?? ('Cía '.$r->compania_id)) : '—' }}</td>
                            <td class="px-4 py-2 font-medium text-slate-800">{{ $r->usuario?->name ?: $r->usuario_nombre ?: '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" style="{{ $badge[$r->evento] ?? '' }}">
                                    {{ $r->evento_label }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-700">{{ $r->descripcion ?: $r->entidad }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-400">{{ $r->ip ?: '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                @if (in_array($r->evento, ['created', 'updated', 'deleted']))
                                    <a href="{{ route('admin.auditoria.show', $r->id) }}" class="text-sm font-semibold" style="color:#005293">Ver</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">Sin actividad en el rango seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $registros->links() }}
        </div>
    </div>
</x-app-layout>
