<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturación Electrónica — Configuración</h2></x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800 break-all">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-900">
                <strong>{{ $compania->nombre }}</strong> — proveedor: The Factory HKA.
                Los tokens se guardan cifrados. En ambiente <strong>PRUEBAS</strong> los documentos van al
                servidor demo del PAC (no tienen validez fiscal).
            </div>

            <form method="POST" action="{{ route('admin.fel.configuracion.update') }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="ambiente" value="Ambiente" />
                        <select id="ambiente" name="ambiente" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="PRUEBAS" @selected(old('ambiente', $config->ambiente ?? 'PRUEBAS') === 'PRUEBAS')>PRUEBAS (demo)</option>
                            <option value="PRODUCCION" @selected(old('ambiente', $config->ambiente ?? '') === 'PRODUCCION')>PRODUCCIÓN</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="correlativo" value="Último número fiscal emitido" />
                        <x-text-input id="correlativo" name="correlativo" type="number" min="0" class="mt-1 block w-full"
                            :value="old('correlativo', $config->correlativo ?? 0)" />
                        <p class="mt-1 text-xs text-gray-500">La próxima factura usará este número + 1.</p>
                    </div>
                </div>

                <div>
                    <x-input-label for="token_empresa" value="Token Empresa (The Factory HKA)" />
                    <x-text-input id="token_empresa" name="token_empresa" type="password" class="mt-1 block w-full"
                        placeholder="{{ ($config?->token_empresa) ? '••••••••  (guardado — escribir solo para reemplazar)' : 'Pegar token de empresa' }}" autocomplete="off" />
                </div>

                <div>
                    <x-input-label for="token_password" value="Token Password" />
                    <x-text-input id="token_password" name="token_password" type="password" class="mt-1 block w-full"
                        placeholder="{{ ($config?->token_password) ? '••••••••  (guardado — escribir solo para reemplazar)' : 'Pegar token password' }}" autocomplete="off" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="punto_facturacion" value="Punto de facturación fiscal" />
                        <x-text-input id="punto_facturacion" name="punto_facturacion" type="text" class="mt-1 block w-full"
                            :value="old('punto_facturacion', $config->punto_facturacion ?? '001')" />
                    </div>
                    <div>
                        <x-input-label for="codigo_sucursal" value="Código de sucursal emisor" />
                        <x-text-input id="codigo_sucursal" name="codigo_sucursal" type="text" class="mt-1 block w-full"
                            :value="old('codigo_sucursal', $config->codigo_sucursal ?? '0000')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Guardar</x-primary-button>
                    <a href="{{ route('admin.fel.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Volver al listado</a>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.fel.configuracion.probar') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm text-gray-600">Consulta los folios restantes en el PAC para verificar que los tokens funcionan.</p>
                    <x-secondary-button type="submit">Probar conexión</x-secondary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
