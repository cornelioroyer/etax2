{{--
    Render recursivo de un nivel del menú lateral.
    Espera: $items (array normalizado por MenuBuilder o por el fallback estático)
            $nivel (int, 0 = raíz)
    Cada item: ['label','icon','href','dispatch','active','children'=>[...]]

    Nota: la indentación por nivel usa style inline (padding-left) a propósito —
    el deploy por pscp no reconstruye el bundle Tailwind, así que clases
    arbitrarias nuevas no aplicarían.
--}}
@foreach ($items as $item)
    @php
        $nivel = $nivel ?? 0;
        $tieneHijos = ! empty($item['children']);
        $activo = $item['active'] ?? false;
        $href = $item['href'] ?? null;
        $dispatch = $item['dispatch'] ?? null;
        $icon = $item['icon'] ?? null;
        $padIzq = 0.75 + $nivel * 1.0; // rem
    @endphp

    <div
        x-data="{ open: {{ $activo ? 'true' : 'false' }} }"
        x-show="menuQuery === '' || $el.textContent.toLowerCase().includes(menuQuery.toLowerCase())"
    >
        @if ($tieneHijos)
            <button
                type="button"
                @click="open = ! open; if (sidebarCollapsed) sidebarCollapsed = false"
                class="group flex h-10 w-full items-center gap-3 rounded-md pr-3 text-left text-sm font-medium {{ $activo ? 'bg-blue-600 text-white shadow' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
                style="padding-left: {{ $padIzq }}rem"
            >
                @if ($icon)
                    <svg class="h-5 w-5 shrink-0 {{ $activo ? 'text-white' : 'text-blue-300 group-hover:text-white' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                    </svg>
                @endif
                <span x-show="! sidebarCollapsed" class="flex-1 truncate">{{ $item['label'] }}</span>
                <svg x-show="! sidebarCollapsed" :class="open ? 'rotate-90' : ''" class="h-4 w-4 shrink-0 text-blue-300 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
            </button>

            <div x-show="(! sidebarCollapsed && open) || menuQuery !== ''" class="mt-1 space-y-1">
                @include('layouts.partials._menu-item', ['items' => $item['children'], 'nivel' => $nivel + 1])
            </div>
        @else
            <a
                href="{{ $href ?? '#' }}"
                @if ($dispatch)
                    @click.prevent="$dispatch('{{ $dispatch }}')"
                @elseif (! $href)
                    @click.prevent
                @endif
                class="group flex h-10 items-center gap-3 rounded-md pr-3 text-sm {{ $activo ? 'bg-blue-600 font-semibold text-white shadow' : (($href || $dispatch) ? 'text-blue-100 hover:bg-white/10 hover:text-white' : 'text-blue-400/50') }}"
                style="padding-left: {{ $padIzq }}rem"
            >
                @if ($icon)
                    <svg class="h-5 w-5 shrink-0 {{ $activo ? 'text-white' : 'text-blue-300 group-hover:text-white' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                    </svg>
                @endif
                <span x-show="! sidebarCollapsed" class="truncate">{{ $item['label'] }}</span>
            </a>
        @endif
    </div>
@endforeach
