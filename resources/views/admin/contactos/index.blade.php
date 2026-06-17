<x-app-layout>
    <x-slot name="header">
        @php($ayudaModulo = $tipo === 'PROVEEDOR' ? 'cxp' : ($tipo === 'CLIENTE' ? 'cxc' : ''))
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Contactos — {{ $companiaActiva->nombre ?? '' }}</h2>
            <div class="flex items-center gap-2">
                <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-help', { detail: { module: {{ $ayudaModulo ? "'".$ayudaModulo."'" : 'null' }} } }))"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    title="Ayuda de esta pantalla">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.326-.67.442-.745.361-1.451.999-1.451 1.827v.75M12 18h.008v.008H12V18Zm9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Ayuda
                </button>
                @can('contactos.crear')
                    @if($tipo === 'PROVEEDOR')
                        <button type="button" onclick="document.getElementById('modal-importar-proveedores').classList.remove('hidden')"
                                class="inline-flex items-center gap-1.5 rounded-md border border-sky-300 bg-white px-3 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                            Importar proveedores
                        </button>
                    @else
                        <button type="button" onclick="document.getElementById('modal-importar-contactos').classList.remove('hidden')"
                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                            Importar
                        </button>
                    @endif
                    <a href="{{ route('admin.contactos.create', $tipo ? ['tipo' => $tipo] : []) }}" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Nuevo contacto</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            {{-- Filtros por tipo + busqueda --}}
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.contactos.index') }}" class="rounded-full px-3 py-1 text-xs font-semibold {{ $tipo === '' ? 'bg-[#0d2d5e] text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50' }}">Todos</a>
                @foreach ($tipos as $t)
                    <a href="{{ route('admin.contactos.index', ['tipo' => $t->codigo]) }}" class="rounded-full px-3 py-1 text-xs font-semibold {{ $tipo === $t->codigo ? 'bg-[#0d2d5e] text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50' }}">{{ $t->nombre . (\Illuminate\Support\Str::endsWith(mb_strtolower($t->nombre), ['a','e','i','o','u']) ? 's' : 'es') }}</a>
                @endforeach

                <form method="GET" action="{{ route('admin.contactos.index') }}" class="ml-auto flex gap-2">
                    @if ($tipo)<input type="hidden" name="tipo" value="{{ $tipo }}">@endif
                    <input type="search" name="search" value="{{ $search }}" placeholder="Buscar nombre, RUC, email..." class="h-9 w-64 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button class="rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Buscar</button>
                </form>
            </div>

            @if ($contactos->isEmpty())
                <div class="rounded-lg border-2 border-dashed border-slate-300 bg-white p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                    <h3 class="mt-4 text-base font-semibold text-slate-900">No hay contactos {{ $tipo ? 'de este tipo' : '' }} todavía</h3>
                    <p class="mt-1 text-sm text-slate-500">Los clientes y proveedores que registres aparecerán aquí.</p>
                    @can('contactos.crear')
                        <a href="{{ route('admin.contactos.create', $tipo ? ['tipo' => $tipo] : []) }}" class="mt-6 inline-block rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Crear el primero</a>
                    @endcan
                </div>
            @else
                {{-- Movil: tarjetas --}}
                <div class="space-y-3 md:hidden">
                    @foreach ($contactos as $contacto)
                        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-slate-900">{{ $contacto->nombre }}</div>
                                    <div class="truncate text-xs text-slate-500">{{ $contacto->identificacion ? $contacto->identificacion . ($contacto->dv ? ' DV ' . $contacto->dv : '') : ($contacto->email ?: '—') }}</div>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $contacto->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $contacto->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($contacto->tipos as $t)
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $t->codigo === 'CLIENTE' ? 'bg-emerald-50 text-emerald-700' : ($t->codigo === 'PROVEEDOR' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600') }}">{{ $t->nombre }}</span>
                                @endforeach
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                                <span class="text-xs text-slate-500">{{ $contacto->telefono ?: '' }}</span>
                                <span class="flex gap-4 text-sm font-medium">
                                    <a href="{{ route('admin.contactos.detalle', $contacto) }}" class="text-blue-600">Detalle</a>
                                    @can('contactos.editar')
                                        <a href="{{ route('admin.contactos.edit', $contacto) }}" class="text-indigo-600">Editar</a>
                                    @endcan
                                    @can('contactos.eliminar')
                                        <form method="POST" action="{{ route('admin.contactos.destroy', $contacto) }}" onsubmit="return confirm('¿Eliminar a {{ $contacto->nombre }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600">Eliminar</button>
                                        </form>
                                    @endcan
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Escritorio: tabla --}}
                <div class="hidden overflow-x-auto bg-white shadow-sm sm:rounded-lg md:block">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Contacto</th>
                                <th class="px-6 py-3">Identificación</th>
                                <th class="px-6 py-3">Tipos</th>
                                <th class="px-6 py-3">Teléfono</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($contactos as $contacto)
                                <tr>
                                    <td class="px-6 py-3">
                                        <div class="font-medium text-slate-900">{{ $contacto->nombre }}</div>
                                        <div class="text-xs text-slate-500">{{ $contacto->email ?: $contacto->razon_social }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-slate-600">
                                        {{ $contacto->identificacion ?: '—' }}@if($contacto->identificacion && $contacto->dv) DV {{ $contacto->dv }}@endif
                                        <div class="text-xs text-slate-400">{{ $contacto->tipo_persona === 'JURIDICA' ? 'Jurídica' : 'Natural' }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        @foreach ($contacto->tipos as $t)
                                            <span class="mr-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $t->codigo === 'CLIENTE' ? 'bg-emerald-50 text-emerald-700' : ($t->codigo === 'PROVEEDOR' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600') }}">{{ $t->nombre }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-6 py-3 text-slate-600">{{ $contacto->telefono ?: '—' }}</td>
                                    <td class="px-6 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $contacto->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $contacto->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-right font-medium">
                                        <a href="{{ route('admin.contactos.detalle', $contacto) }}" class="text-blue-600 hover:text-blue-900 mr-3">Detalle</a>
                                        @can('contactos.editar')
                                            <a href="{{ route('admin.contactos.edit', $contacto) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                        @endcan
                                        @can('contactos.eliminar')
                                            <form method="POST" action="{{ route('admin.contactos.destroy', $contacto) }}" class="inline" onsubmit="return confirm('¿Eliminar a {{ $contacto->nombre }}?')">
                                                @csrf @method('DELETE')
                                                <button class="ml-3 text-red-600 hover:text-red-800">Eliminar</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $contactos->links() }}
            @endif
        </div>
    </div>
@can('contactos.crear')
<div id="modal-importar-contactos" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Importar contactos</h3>
            <button type="button" onclick="document.getElementById('modal-importar-contactos').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <div class="mb-4 rounded-md bg-slate-50 p-3 text-xs text-slate-600 space-y-1">
            <p class="font-semibold text-slate-700">Formato del archivo (Excel o CSV):</p>
            <p>Fila 1 = encabezados (se omite). Columnas en orden:</p>
            <ol class="list-decimal list-inside space-y-0.5">
                <li><strong>nombre</strong> — requerido</li>
                <li>razon_social</li>
                <li>tipo_persona — NATURAL / JURIDICA / EXTRANJERO (default: NATURAL)</li>
                <li>identificacion — RUC o cédula</li>
                <li>dv — dígito verificador</li>
                <li>email</li>
                <li>telefono</li>
                <li>direccion</li>
                <li><strong>tipos</strong> — CLIENTE / PROVEEDOR / EMPLEADO (separados por coma; default: CLIENTE)</li>
                <li>saldo — saldo inicial en CxC (opcional; ej: 500.00)</li>
                <li>fecha_saldo — fecha del saldo (DD/MM/YYYY o YYYY-MM-DD; default: hoy)</li>
            </ol>
            <p class="mt-1">Si hay saldo, se crea una factura PENDIENTE en Cuentas por Cobrar con el asiento correspondiente.</p>
            <p class="mt-0.5">Si ya existe un contacto con la misma identificación, la fila se omite.</p>
        </div>

        <div class="mb-4">
            <a href="{{ route('admin.contactos.plantilla') }}"
               class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Descargar plantilla CSV
            </a>
        </div>

        <form method="POST" action="{{ route('admin.contactos.importar') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo (.xlsx, .xls, .csv)</label>
                <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-importar-contactos').classList.add('hidden')"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">
                    Importar
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

@can('contactos.crear')
<div id="modal-importar-proveedores" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Importar catálogo de proveedores</h3>
            <button type="button" onclick="document.getElementById('modal-importar-proveedores').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <div class="mb-4 space-y-2">
            <p class="text-xs font-semibold text-slate-700">Formato del archivo (Excel o CSV) — fila 1 = encabezados, fila 2 en adelante = datos:</p>

            {{-- Tabla ejemplo --}}
            <div class="overflow-x-auto rounded-md border border-sky-200">
                <table class="min-w-max text-[10px]">
                    <thead class="bg-sky-600 text-white">
                        <tr>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">nombre *</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">razon_social</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">tipo_persona</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">identificacion</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">dv</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">email</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">telefono</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">direccion</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">saldo</th>
                            <th class="px-2 py-1 font-semibold whitespace-nowrap">fecha_saldo</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-100">
                        <tr class="text-slate-600">
                            <td class="px-2 py-1 whitespace-nowrap">Ferretería ABC S.A.</td>
                            <td class="px-2 py-1 whitespace-nowrap">Ferretería ABC S.A.</td>
                            <td class="px-2 py-1 whitespace-nowrap">JURIDICA</td>
                            <td class="px-2 py-1 whitespace-nowrap">888-888-111111</td>
                            <td class="px-2 py-1 whitespace-nowrap">99</td>
                            <td class="px-2 py-1 whitespace-nowrap">compras@abc.com</td>
                            <td class="px-2 py-1 whitespace-nowrap">6000-0000</td>
                            <td class="px-2 py-1 whitespace-nowrap">Ciudad de Panamá</td>
                            <td class="px-2 py-1 whitespace-nowrap">1500.00</td>
                            <td class="px-2 py-1 whitespace-nowrap">31/12/2025</td>
                        </tr>
                        <tr class="bg-slate-50 text-slate-600">
                            <td class="px-2 py-1 whitespace-nowrap">Juan Pérez</td>
                            <td class="px-2 py-1 whitespace-nowrap"></td>
                            <td class="px-2 py-1 whitespace-nowrap">NATURAL</td>
                            <td class="px-2 py-1 whitespace-nowrap">8-123-456</td>
                            <td class="px-2 py-1 whitespace-nowrap">5</td>
                            <td class="px-2 py-1 whitespace-nowrap">juan@email.com</td>
                            <td class="px-2 py-1 whitespace-nowrap">6111-2222</td>
                            <td class="px-2 py-1 whitespace-nowrap">Chorrera</td>
                            <td class="px-2 py-1 whitespace-nowrap"></td>
                            <td class="px-2 py-1 whitespace-nowrap"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-md bg-sky-50 p-2.5 text-[11px] text-sky-800 space-y-0.5">
                <p>• <strong>tipo_persona:</strong> NATURAL / JURIDICA / EXTRANJERO (default: NATURAL)</p>
                <p>• <strong>saldo:</strong> si viene, se crea factura PENDIENTE en CxP con asiento contable</p>
                <p>• <strong>fecha_saldo:</strong> DD/MM/YYYY o YYYY-MM-DD (omitir = fecha de hoy)</p>
                <p>• Si el proveedor <strong>ya existe</strong> (misma identificación), sus datos se <strong>actualizan</strong></p>
            </div>
        </div>

        <div class="mb-4 flex items-center gap-4">
            <a href="{{ route('admin.contactos.plantilla-proveedores-xlsx') }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-green-300 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-100">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Plantilla Excel (.xlsx)
            </a>
            <a href="{{ route('admin.contactos.plantilla-proveedores') }}"
               class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700 hover:underline">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Plantilla CSV
            </a>
        </div>

        <form method="POST" action="{{ route('admin.contactos.importar-proveedores') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo (.xlsx, .xls, .csv)</label>
                <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-importar-proveedores').classList.add('hidden')"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="rounded-md bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">
                    Importar
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

</x-app-layout>
