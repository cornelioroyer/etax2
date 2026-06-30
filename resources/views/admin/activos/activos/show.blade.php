<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $activo->codigo }}</h2>
                @include('admin.activos.activos._estado', ['estado' => $activo->estado])
            </div>
            <a href="{{ route('admin.activos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Listado</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Datos del activo --}}
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="mb-4 text-sm font-semibold text-gray-700">{{ $activo->descripcion }}</h3>
                <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-gray-500">Categoría</dt>
                        <dd class="font-medium">{{ $activo->categoria?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Ubicación</dt>
                        <dd class="font-medium">{{ $activo->ubicacion?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Fecha compra</dt>
                        <dd class="font-medium">{{ $activo->fecha_compra?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Inicio depreciación</dt>
                        <dd class="font-medium">{{ $activo->fecha_inicio_depreciacion?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Valor de compra</dt>
                        <dd class="font-mono font-semibold">B/. {{ number_format($activo->valor_compra, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Valor residual</dt>
                        <dd class="font-mono">B/. {{ number_format($activo->valor_residual, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Dep. acumulada</dt>
                        <dd class="font-mono text-orange-700">B/. {{ number_format($activo->depreciacionAcumulada(), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Valor en libros</dt>
                        <dd class="font-mono font-bold text-indigo-700">B/. {{ number_format($activo->valorLibros(), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Vida útil (meses)</dt>
                        <dd class="font-medium">{{ $activo->vida_util_meses }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Meses depreciados</dt>
                        <dd class="font-medium">{{ $activo->mesesDepreciados() }} / {{ $activo->vida_util_meses }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Cuota mensual</dt>
                        <dd class="font-mono">B/. {{ number_format($activo->depreciacionMensual(), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Método</dt>
                        <dd class="font-medium">{{ $activo->metodo_depreciacion }}</dd>
                    </div>
                </div>

                @if ($activo->asientoCompra)
                    <p class="mt-3 text-xs text-gray-400">
                        Asiento de compra: #{{ $activo->asientoCompra->numero }}
                    </p>
                @endif
            </div>

            {{-- Acciones (solo si ACTIVO) --}}
            @can('activos.gestionar')
            @if ($activo->estaActivo())

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                {{-- Depreciar --}}
                @if (! $activo->estaDepreciadoTotal() && $activo->vida_util_meses > 0)
                <div class="bg-white p-6 shadow-sm sm:rounded-lg" x-data="{ open: false }">
                    <h3 class="mb-1 text-sm font-semibold text-gray-700">Registrar depreciación</h3>
                    <p class="mb-3 text-xs text-gray-500">
                        Cuota: B/. {{ number_format($activo->depreciacionMensual(), 2) }} ·
                        Meses restantes: {{ $activo->mesesRestantes() }}
                    </p>
                    <button @click="open = !open"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">
                        Depreciar período
                    </button>
                    <div x-show="open" x-cloak class="mt-4">
                        <form method="POST" action="{{ route('admin.activos.depreciar', $activo) }}">
                            @csrf
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <x-input-label for="dep_periodo_id" value="Período *" />
                                    <select id="dep_periodo_id" name="periodo_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">— seleccionar —</option>
                                        @foreach ($periodos as $p)
                                            <option value="{{ $p->id }}">{{ $p->anio }}-{{ str_pad($p->mes, 2, '0', STR_PAD_LEFT) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="dep_fecha" value="Fecha del asiento *" />
                                    <x-text-input id="dep_fecha" name="fecha" type="text" class="js-date mt-1 block w-full"
                                        :value="now()->format('Y-m-d')" required />
                                </div>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <x-primary-button>Registrar</x-primary-button>
                                <button type="button" @click="open = false"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                {{-- Dar de baja --}}
                <div class="bg-white p-6 shadow-sm sm:rounded-lg" x-data="{ open: false }">
                    <h3 class="mb-1 text-sm font-semibold text-gray-700">Dar de baja</h3>
                    <p class="mb-3 text-xs text-gray-500">
                        Valor en libros: B/. {{ number_format($activo->valorLibros(), 2) }}
                    </p>
                    <button @click="open = !open"
                        class="rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">
                        Dar de baja
                    </button>
                    <div x-show="open" x-cloak class="mt-4">
                        <form method="POST" action="{{ route('admin.activos.baja', $activo) }}"
                              onsubmit="return confirm('¿Confirmar baja del activo? Esta acción es irreversible.')">
                            @csrf
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <x-input-label for="baja_fecha" value="Fecha de baja *" />
                                    <x-text-input id="baja_fecha" name="fecha" type="text" class="js-date mt-1 block w-full"
                                        :value="now()->format('Y-m-d')" required />
                                </div>
                                <div>
                                    <x-input-label for="baja_motivo" value="Motivo" />
                                    <textarea id="baja_motivo" name="motivo" rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                                </div>
                                <div>
                                    <x-buscador-contacto name="cuenta_resultado_id" label="Cuenta pérdida / ganancia en baja *" :opciones="$cuentas"
                                        required placeholder="Buscar cuenta por código o nombre" />
                                </div>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm text-white hover:bg-red-700">Confirmar baja</button>
                                <button type="button" @click="open = false"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @endif
            @endcan

            {{-- Historial de depreciaciones --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Historial de depreciaciones</h3>
                </div>
                @if ($activo->depreciaciones->isNotEmpty())
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Período</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Monto</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Acumulado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Asiento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($activo->depreciaciones as $dep)
                        <tr>
                            <td class="px-4 py-2">{{ $dep->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-xs">
                                @if ($dep->periodo)
                                    {{ $dep->periodo->anio }}-{{ str_pad($dep->periodo->mes, 2, '0', STR_PAD_LEFT) }}
                                @else —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-mono">B/. {{ number_format($dep->monto, 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-orange-700">B/. {{ number_format($dep->acumulado, 2) }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $dep->asiento?->numero ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <p class="px-6 py-4 text-sm text-gray-400">Sin depreciaciones registradas.</p>
                @endif
            </div>

            {{-- Baja --}}
            @if ($activo->baja)
            <div class="bg-red-50 p-6 shadow-sm sm:rounded-lg border border-red-100">
                <h3 class="mb-3 text-sm font-semibold text-red-700">Baja de activo</h3>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-red-500">Fecha</dt>
                        <dd>{{ $activo->baja->fecha->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-red-500">Valor en libros</dt>
                        <dd class="font-mono">B/. {{ number_format($activo->baja->valor_baja, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-red-500">Asiento</dt>
                        <dd>{{ $activo->baja->asiento?->numero ?? '—' }}</dd>
                    </div>
                    @if ($activo->baja->motivo)
                    <div class="col-span-3">
                        <dt class="text-xs text-red-500">Motivo</dt>
                        <dd>{{ $activo->baja->motivo }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
