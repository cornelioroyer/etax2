<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar factura por QR / CUFE</h2>
            <a href="{{ route('admin.cxp.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="mb-5 text-sm text-gray-600">
                    Pega el contenido del <strong>código QR</strong> de la factura electrónica o el <strong>CUFE</strong> directamente.
                    El sistema consultará la DGI, creará el proveedor si no existe y registrará la factura en borrador con sus líneas reales.
                </p>

                <form method="POST" action="{{ route('admin.cxp.facturas.desde-cufe') }}">
                    @csrf
                    <div>
                        <x-input-label for="cufe_input" value="URL del QR o CUFE *" />
                        <textarea id="cufe_input" name="cufe_input" rows="4"
                                  placeholder="https://dgi-fep.mef.gob.pa/Consultas/FacturasPorCUFE/FE0120...&#10;— o —&#10;FE0120..."
                                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  required>{{ old('cufe_input') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            Puedes escanear el QR con tu teléfono y copiar el enlace, o pegar el CUFE directamente.
                        </p>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <button type="submit"
                                style="background:#2563eb;color:#fff;padding:0.5rem 1.25rem;border-radius:0.375rem;font-size:0.875rem;font-weight:600;">
                            Consultar DGI y registrar
                        </button>
                        <a href="{{ route('admin.cxp.facturas.index') }}"
                           style="font-size:0.875rem;color:#4b5563;">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>

            <div class="mt-4 rounded-md bg-blue-50 p-4 text-sm text-blue-800">
                <strong>¿Dónde encuentro el QR?</strong><br>
                En el PDF de la factura electrónica recibida hay un código QR. Al escanearlo con el celular obtendrás un enlace de la DGI
                (<code class="text-xs bg-blue-100 px-1 rounded">dgi-fep.mef.gob.pa/Consultas/FacturasPorCUFE/...</code>).
                Copia ese enlace completo o solo la parte del CUFE (el código al final de la URL).
            </div>
        </div>
    </div>
</x-app-layout>
