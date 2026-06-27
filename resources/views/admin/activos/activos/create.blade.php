<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo activo fijo</h2>
            @if ($desdeCxp)
                <a href="{{ route('admin.cxp.facturas.show', $desdeCxp->documento) }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver a la factura</a>
            @else
                <a href="{{ route('admin.activos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver al listado</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if ($desdeCxp)
                <div class="mb-4 rounded-md p-4 text-sm" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
                    Registrando activo desde factura <strong>{{ $desdeCxp->documento->numero }}</strong>
                    ({{ $desdeCxp->documento->proveedor->nombre ?? '' }}) — línea {{ $desdeCxp->linea }}: {{ $desdeCxp->descripcion }}.
                    El asiento contable ya fue generado por CxP; no se creará un asiento adicional.
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg"
                 x-data="{
                     categoriaId: {{ old('categoria_id', 'null') }},
                     categorias: {{ $categorias->keyBy('id')->toJson() }},
                     get cat() { return this.categorias[this.categoriaId] ?? null; },
                     aplicarCategoria() {
                         if (!this.cat) return;
                         if (!document.getElementById('vida_util_meses').value && this.cat.vida_util_meses_default)
                             document.getElementById('vida_util_meses').value = this.cat.vida_util_meses_default;
                         ['cuenta_activo_id','cuenta_depreciacion_acum_id','cuenta_gasto_depreciacion_id'].forEach(k => {
                             const sel = document.getElementById(k);
                             if (sel && !sel.value && this.cat[k]) sel.value = this.cat[k];
                         });
                     }
                 }">

                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.activos.store') }}">
                    @csrf

                    @if ($desdeCxp)
                        <input type="hidden" name="cxp_detalle_id" value="{{ $desdeCxp->id }}">
                    @endif

                    <h3 class="mb-4 text-sm font-semibold text-gray-700">Datos del activo</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-input-label for="descripcion" value="Descripción *" />
                            <x-text-input id="descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                                :value="old('descripcion', $desdeCxp?->descripcion)" required maxlength="500" />
                            <x-input-error :messages="$errors->get('descripcion')" class="mt-1" />
                        </div>
                        <div>
                            {{-- Categoría buscable; sincroniza categoriaId y aplica sus defaults. --}}
                            <div @contacto-seleccionado="categoriaId = $event.detail?.id ?? ''; aplicarCategoria()">
                                <x-buscador-contacto name="categoria_id" label="Categoría"
                                    :opciones="$categorias" :selected="old('categoria_id')"
                                    placeholder="Buscar por código o nombre" empty-label="— sin categoría —" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="ubicacion_id" value="Ubicación" />
                            <select id="ubicacion_id" name="ubicacion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— sin ubicación —</option>
                                @foreach ($ubicaciones as $ub)
                                    <option value="{{ $ub->id }}" @selected(old('ubicacion_id') == $ub->id)>{{ $ub->codigo }} — {{ $ub->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="fecha_compra" value="Fecha de compra *" />
                            <x-text-input id="fecha_compra" name="fecha_compra" type="text" class="js-date mt-1 block w-full"
                                :value="old('fecha_compra', $desdeCxp?->documento->fecha->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('fecha_compra')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="fecha_inicio_depreciacion" value="Inicio depreciación *" />
                            <x-text-input id="fecha_inicio_depreciacion" name="fecha_inicio_depreciacion" type="text"
                                class="js-date mt-1 block w-full"
                                :value="old('fecha_inicio_depreciacion', $desdeCxp?->documento->fecha->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('fecha_inicio_depreciacion')" class="mt-1" />
                        </div>
                    </div>

                    <h3 class="mt-6 mb-4 text-sm font-semibold text-gray-700">Valores y depreciación</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="valor_compra" value="Valor de compra (B/.) *" />
                            @php
                                $valorPreLlenado = $desdeCxp
                                    ? number_format((float)$desdeCxp->total_linea - (float)$desdeCxp->impuesto_monto, 2, '.', '')
                                    : null;
                            @endphp
                            <x-text-input id="valor_compra" name="valor_compra" type="number" step="0.01"
                                class="mt-1 block w-full" :value="old('valor_compra', $valorPreLlenado)" required min="0.01" />
                            @if ($desdeCxp && (float)$desdeCxp->impuesto_monto > 0)
                                <p class="mt-1 text-xs text-gray-500">Subtotal sin ITBMS. Si el ITBMS se capitaliza al activo, súmalo manualmente.</p>
                            @endif
                            <x-input-error :messages="$errors->get('valor_compra')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="valor_residual" value="Valor residual (B/.)" />
                            <x-text-input id="valor_residual" name="valor_residual" type="number" step="0.01"
                                class="mt-1 block w-full" :value="old('valor_residual', 0)" min="0" />
                            <x-input-error :messages="$errors->get('valor_residual')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="vida_util_meses" value="Vida útil (meses) *" />
                            <x-text-input id="vida_util_meses" name="vida_util_meses" type="number"
                                class="mt-1 block w-full" :value="old('vida_util_meses')" min="0" max="600" />
                            <x-input-error :messages="$errors->get('vida_util_meses')" class="mt-1" />
                        </div>
                    </div>

                    <h3 class="mt-6 mb-4 text-sm font-semibold text-gray-700">Cuentas contables <span class="font-normal text-gray-400">(se heredan de la categoría si están vacías)</span></h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="cuenta_activo_id" value="Cuenta del activo" />
                            <select id="cuenta_activo_id" name="cuenta_activo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— heredar de categoría —</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_activo_id') == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="cuenta_depreciacion_acum_id" value="Dep. acumulada" />
                            <select id="cuenta_depreciacion_acum_id" name="cuenta_depreciacion_acum_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— heredar de categoría —</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_depreciacion_acum_id') == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="cuenta_gasto_depreciacion_id" value="Gasto depreciación" />
                            <select id="cuenta_gasto_depreciacion_id" name="cuenta_gasto_depreciacion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— heredar de categoría —</option>
                                @foreach ($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected(old('cuenta_gasto_depreciacion_id') == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($desdeCxp)
                            {{-- Asiento ya creado por CxP; no se necesita contrapartida --}}
                            <div class="flex items-center rounded-md p-3 text-sm" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;">
                                Asiento de compra: <a href="{{ route('admin.asientos.show', $desdeCxp->documento->asiento) }}" class="ml-1 font-medium underline">{{ $desdeCxp->documento->asiento->numero }}</a>
                                (generado por {{ $desdeCxp->documento->numero }})
                            </div>
                        @else
                            <div>
                                <x-input-label for="cuenta_contrapartida_id" value="Cuenta contrapartida (compra) *" />
                                <select id="cuenta_contrapartida_id" name="cuenta_contrapartida_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">— seleccionar —</option>
                                    @foreach ($cuentas as $c)
                                        <option value="{{ $c->id }}" @selected(old('cuenta_contrapartida_id') == $c->id)>{{ $c->codigo }} {{ $c->nombre }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Cuenta que se acredita al comprar el activo (ej: banco, proveedor CxP).</p>
                                <x-input-error :messages="$errors->get('cuenta_contrapartida_id')" class="mt-1" />
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Registrar activo</x-primary-button>
                        @if ($desdeCxp)
                            <a href="{{ route('admin.cxp.facturas.show', $desdeCxp->documento) }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                        @else
                            <a href="{{ route('admin.activos.index') }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
