{{--
    Combobox de cuenta contable buscable (código o nombre) para FILAS Alpine (x-for).
    Pensado para incluirse dentro de un <template x-for="(linea, idx) in lineas">.
    No se puede usar el componente <x-buscador-contacto> aquí porque cada fila es
    clonada por Alpine y necesita ligarse al objeto de fila del scope padre.

    El desplegable usa position:fixed anclado al campo para no ser recortado por
    contenedores con overflow (las tablas de líneas van en .overflow-x-auto).

    Variables esperadas vía @include([...]):
      - $cuentas    : colección con ->id, ->codigo, ->nombre (obligatorio)
      - $model      : nombre JS del objeto de fila en el x-for     (def. 'linea')
      - $field      : propiedad del id de cuenta en la fila        (def. 'cuenta_id')
      - $refExpr    : expresión JS del valor ligado; sobreescribe $model.$field
                      (ej. 'editCuentaId' para un select suelto Alpine)
      - $nameExpr   : expresión JS para el atributo :name del hidden
                      (ej. '`lineas[${idx}][cuenta_id]`' o "'cuenta_default_id'") (obligatorio)
      - $required   : si true, no muestra opción vacía             (def. false)
      - $emptyLabel : etiqueta de la opción vacía                  (def. '— Sin cuenta —')
      - $placeholder: placeholder del input de texto               (def. 'Código o descripción…')
--}}
@php
    $model = $model ?? 'linea';
    $field = $field ?? 'cuenta_id';
    $required = $required ?? false;
    $emptyLabel = $emptyLabel ?? '— Sin cuenta —';
    $placeholder = $placeholder ?? 'Código o descripción…';
    $ref = ($refExpr ?? null) ?: ($model.'.'.$field);
    $datosCuentas = collect($cuentas)->map(fn ($c) => [
        'id' => $c->id,
        'codigo' => (string) ($c->codigo ?? ''),
        'nombre' => (string) ($c->nombre ?? ''),
    ])->values();
@endphp
<div x-data="{
        open: false,
        q: '',
        estilo: '',
        items: {{ \Illuminate\Support\Js::from($datosCuentas) }},
        get filtrados() {
            const b = this.q.trim().toLowerCase();
            if (! b) return this.items;
            return this.items.filter(c => c.nombre.toLowerCase().includes(b) || c.codigo.toLowerCase().includes(b));
        },
        rotulo(c) { return (c.codigo ? c.codigo + ' — ' : '') + c.nombre; },
        sincronizar() {
            const c = this.items.find(x => String(x.id) === String({{ $ref }}));
            this.q = c ? this.rotulo(c) : '';
        },
        abrir() {
            const r = this.$refs.campo.getBoundingClientRect();
            this.estilo = `position:fixed; top:${r.bottom + 2}px; left:${r.left}px; width:${r.width}px;`;
            this.open = true;
        },
        elegir(c) {
            {{ $ref }} = c ? String(c.id) : '';
            this.q = c ? this.rotulo(c) : '';
            this.open = false;
        },
     }"
     x-init="sincronizar(); $watch('{{ $ref }}', () => { if (! open) sincronizar(); })"
     @click.outside="open = false" class="relative">
    <input type="hidden" :name="{{ $nameExpr }}" :value="{{ $ref }}">
    <input type="text" x-ref="campo" x-model="q" autocomplete="off"
           @focus="abrir(); $el.select()" @input="abrir(); {{ $ref }} = ''"
           @keydown.escape="open = false"
           placeholder="{{ $placeholder }}"
           class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    <div x-show="open" x-cloak :style="estilo" @scroll.window="open = false" @resize.window="open = false"
         class="z-50 max-h-60 overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
        @unless ($required)
            <button type="button" @click="elegir(null)" class="block w-full px-3 py-1.5 text-left text-gray-500 hover:bg-gray-50">{{ $emptyLabel }}</button>
        @endunless
        <template x-for="c in filtrados" :key="c.id">
            <button type="button" @click="elegir(c)" class="flex w-full items-baseline gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">
                <span class="font-mono text-xs text-gray-400" x-text="c.codigo"></span>
                <span class="truncate" x-text="c.nombre"></span>
            </button>
        </template>
        <p x-show="filtrados.length === 0" class="px-3 py-1.5 text-gray-400">Sin coincidencias</p>
    </div>
</div>
