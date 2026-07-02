<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nómina — Conceptos</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            @can('nomina.gestionar')
            <div class="flex flex-wrap items-center justify-between gap-3">
                <form method="POST" action="{{ route('admin.nomina.conceptos.aplicar-catalogo') }}"
                      onsubmit="return confirm('¿Aplicar el catálogo default de conceptos (Salario, CSS, SE, ISR, cuotas patronales...)? No duplica los existentes.')">
                    @csrf
                    <button class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color:#0d2d5e">
                        Aplicar catálogo default
                    </button>
                </form>
                <div x-data="{ nuevo: false }" class="w-full">
                    <button @click="nuevo = !nuevo" class="text-sm text-indigo-600 hover:underline">+ Concepto personalizado</button>
                    <div x-show="nuevo" x-cloak class="mt-3 bg-white p-6 shadow-sm sm:rounded-lg">
                        <form method="POST" action="{{ route('admin.nomina.conceptos.store') }}">
                            @csrf
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <div>
                                    <x-input-label value="Código *" />
                                    <x-text-input name="codigo" type="text" class="mt-1 block w-full" :value="old('codigo')" required maxlength="10" />
                                </div>
                                <div>
                                    <x-input-label value="Descripción *" />
                                    <x-text-input name="descripcion" type="text" class="mt-1 block w-full" :value="old('descripcion')" required maxlength="200" />
                                </div>
                                <div>
                                    <x-input-label value="Tipo *" />
                                    <select name="tipo" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                        <option value="INGRESO">Ingreso</option>
                                        <option value="DEDUCCION">Deducción</option>
                                    </select>
                                </div>
                                <div class="flex items-end gap-4 pb-1">
                                    <label class="flex items-center gap-1 text-sm text-gray-700">
                                        <input type="hidden" name="gravable_css" value="0">
                                        <input type="checkbox" name="gravable_css" value="1" checked class="rounded border-gray-300"> Grava CSS
                                    </label>
                                    <label class="flex items-center gap-1 text-sm text-gray-700">
                                        <input type="hidden" name="gravable_isr" value="0">
                                        <input type="checkbox" name="gravable_isr" value="1" checked class="rounded border-gray-300"> Grava ISR
                                    </label>
                                </div>
                            </div>
                            <div class="mt-4"><x-primary-button>Guardar concepto</x-primary-button></div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cálculo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">CSS</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">ISR</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Cuentas</th>
                            @can('nomina.gestionar')<th></th>@endcan
                        </tr>
                    </thead>
                    @forelse ($items as $item)
                        <tbody x-data="{ edit: false }" class="divide-y divide-gray-100">
                            <tr class="{{ $item->activo ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 font-mono font-semibold">{{ $item->codigo }}
                                    @if($item->de_sistema)<span class="ml-1 rounded bg-gray-100 px-1 text-[10px] text-gray-500">sistema</span>@endif
                                </td>
                                <td class="px-4 py-2">{{ $item->descripcion }}</td>
                                <td class="px-4 py-2">{{ $item->etiquetaTipo() }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $item->calculo }}</td>
                                <td class="px-4 py-2 text-center">{{ $item->gravable_css ? '✓' : '—' }}</td>
                                <td class="px-4 py-2 text-center">{{ $item->gravable_isr ? '✓' : '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    @if($item->cuentaGasto)<div>Dr {{ $item->cuentaGasto->codigo }}</div>@endif
                                    @if($item->cuentaPasivo)<div>Cr {{ $item->cuentaPasivo->codigo }}</div>@endif
                                    @if(!$item->cuentaGasto && !$item->cuentaPasivo)<span class="text-gray-300">default</span>@endif
                                </td>
                                @can('nomina.gestionar')
                                <td class="px-4 py-2 text-right space-x-2">
                                    <button @click="edit = !edit" class="text-xs text-indigo-600 hover:underline">Editar</button>
                                    @unless($item->de_sistema)
                                    <form method="POST" action="{{ route('admin.nomina.conceptos.destroy', $item) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar concepto?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                    @endunless
                                </td>
                                @endcan
                            </tr>
                            @can('nomina.gestionar')
                            <tr x-show="edit" x-cloak>
                                <td colspan="8" class="bg-gray-50 px-4 py-3">
                                    <form method="POST" action="{{ route('admin.nomina.conceptos.update', $item) }}">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4 items-end">
                                            <div>
                                                <x-input-label value="Descripción *" />
                                                <x-text-input name="descripcion" type="text" class="mt-1 block w-full"
                                                    value="{{ $item->descripcion }}" required maxlength="200" />
                                            </div>
                                            <div>
                                                <x-input-label value="Cuenta de gasto (Dr)" />
                                                <select name="cuenta_gasto_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                                    <option value="">— default del módulo —</option>
                                                    @foreach ($cuentas as $c)
                                                        <option value="{{ $c->id }}" @selected($item->cuenta_gasto_id === $c->id)>{{ $c->codigo }} — {{ $c->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Cuenta de pasivo (Cr)" />
                                                <select name="cuenta_pasivo_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                                    <option value="">— default del módulo —</option>
                                                    @foreach ($cuentas as $c)
                                                        <option value="{{ $c->id }}" @selected($item->cuenta_pasivo_id === $c->id)>{{ $c->codigo }} — {{ $c->nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex items-center gap-3 pb-1">
                                                @unless($item->de_sistema)
                                                <label class="flex items-center gap-1 text-sm text-gray-700">
                                                    <input type="hidden" name="gravable_css" value="0">
                                                    <input type="checkbox" name="gravable_css" value="1" {{ $item->gravable_css ? 'checked' : '' }} class="rounded border-gray-300"> CSS
                                                </label>
                                                <label class="flex items-center gap-1 text-sm text-gray-700">
                                                    <input type="hidden" name="gravable_isr" value="0">
                                                    <input type="checkbox" name="gravable_isr" value="1" {{ $item->gravable_isr ? 'checked' : '' }} class="rounded border-gray-300"> ISR
                                                </label>
                                                @endunless
                                                <label class="flex items-center gap-1 text-sm text-gray-700">
                                                    <input type="hidden" name="activo" value="0">
                                                    <input type="checkbox" name="activo" value="1" {{ $item->activo ? 'checked' : '' }} class="rounded border-gray-300"> Activo
                                                </label>
                                                <x-primary-button>Actualizar</x-primary-button>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            @endcan
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">
                            Sin conceptos. Usa "Aplicar catálogo default" para sembrar Salario, CSS, SE, ISR y cuotas patronales.
                        </td></tr></tbody>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
