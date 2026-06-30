@props([
    'name' => 'cliente_id',
    'label' => 'Cliente',
    'opciones' => null,
    'selected' => null,
    'placeholder' => 'Todos — buscar por nombre o código',
    'width' => 'w-full',
    'compact' => false,
    'required' => false,
    'submitOnSelect' => false,
    'emptyLabel' => 'Todos',
    'mostrarRuc' => false,
    'inputId' => null,
])
@php
    $col = collect($opciones ?? []);
    $sel = ($selected !== null && $selected !== '') ? $col->firstWhere('id', (int) $selected) : null;
    $datos = $col->map(fn ($c) => [
        'id' => $c->id,
        'codigo' => (string) ($c->codigo ?? ''),
        'nombre' => (string) ($c->nombre ?? ''),
        'ruc' => (string) ($c->identificacion ?? ''),
        'dv' => (string) ($c->dv ?? ''),
    ])->values();
    $inicial = $sel
        ? trim((((string) ($sel->codigo ?? '') !== '') ? $sel->codigo.' — ' : '').($sel->nombre ?? '')
            .($mostrarRuc && ($sel->identificacion ?? '') !== '' ? ' — RUC '.$sel->identificacion.(($sel->dv ?? '') !== '' ? ' DV '.$sel->dv : '') : ''))
        : '';
    $cid = $inputId ?? $name.'_buscar';
@endphp
{{-- Combobox buscable (por nombre, código o RUC). Envía $name vía <input hidden>; el
     campo de texto no tiene name. Alpine inline → no requiere recompilar el bundle JS.
     - required:       bloquea el submit del formulario si no hay selección.
     - submitOnSelect: al elegir, envía el formulario (reemplaza onchange="this.form.submit()").
     - emptyLabel:     texto de la opción vacía (ej. "Consumidor final (sin RUC)").
     - mostrarRuc:     muestra el RUC en la lista/etiqueta (la búsqueda por RUC va siempre).
     - evento:         despacha "contacto-seleccionado" con detail = item elegido (o null),
                       para que el formulario reaccione (ej. autoseleccionar forma de pago). --}}
<div x-data="{
        open: false,
        q: @js($inicial),
        sel: @js((string) ($selected ?? '')),
        items: {{ \Illuminate\Support\Js::from($datos) }},
        mostrarRuc: {{ $mostrarRuc ? 'true' : 'false' }},
        get filtrados() {
            const b = this.q.trim().toLowerCase();
            if (! b) return this.items;
            return this.items.filter(c => c.nombre.toLowerCase().includes(b)
                || c.codigo.toLowerCase().includes(b)
                || (c.ruc && c.ruc.toLowerCase().includes(b)));
        },
        etiqueta(c) {
            let s = (c.codigo ? c.codigo + ' — ' : '') + c.nombre;
            if (this.mostrarRuc && c.ruc) s += ' — RUC ' + c.ruc + (c.dv ? ' DV ' + c.dv : '');
            return s;
        },
        pick(c) {
            this.sel = c ? String(c.id) : '';
            this.q = c ? this.etiqueta(c) : '';
            this.open = false;
            this.$dispatch('contacto-seleccionado', c);
            @if ($submitOnSelect)
                if (c) this.$el.closest('form')?.submit();
            @endif
        },
     }"
     @if ($required)
        x-init="$el.closest('form')?.addEventListener('submit', e => {
            if (! sel) { e.preventDefault(); open = true; $el.querySelector('input[type=text]')?.focus(); }
        })"
     @endif
     @click.outside="open = false" {{ $attributes }}>
    @if ($label !== '')
        @if ($compact)
            <label for="{{ $cid }}" class="block text-xs text-gray-500 mb-1">{{ $label }}</label>
        @else
            <x-input-label :for="$cid" :value="$label" />
        @endif
    @endif
    <div class="relative {{ $compact ? '' : 'mt-1' }}">
        <input type="hidden" name="{{ $name }}" :value="sel">
        <input type="text" id="{{ $cid }}" x-model="q" autocomplete="off"
               @focus="open = true; $el.select()" @input="open = true; sel = ''"
               placeholder="{{ $placeholder }}"
               class="block {{ $width }} rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $compact ? 'text-sm' : '' }}">
        <div x-show="open" x-cloak
             class="absolute z-20 mt-1 max-h-60 {{ $width }} overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
            @unless ($required)
                <button type="button" @click="pick(null)" class="block w-full px-3 py-1.5 text-left text-gray-500 hover:bg-gray-50">{{ $emptyLabel }}</button>
            @endunless
            <template x-for="c in filtrados" :key="c.id">
                <button type="button" @click="pick(c)" class="flex w-full items-baseline gap-1 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">
                    <span class="font-mono text-xs text-gray-400" x-text="c.codigo"></span>
                    <span class="truncate" x-text="c.nombre"></span>
                    <span class="ml-auto whitespace-nowrap text-xs text-gray-400" x-show="mostrarRuc && c.ruc" x-text="'RUC ' + c.ruc"></span>
                </button>
            </template>
            <p x-show="filtrados.length === 0" class="px-3 py-1.5 text-gray-400">Sin coincidencias</p>
        </div>
    </div>
</div>
