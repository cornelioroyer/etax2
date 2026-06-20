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

            {{-- Instrucciones --}}
            <div class="mb-4 rounded-md bg-blue-50 p-4 text-sm text-blue-900">
                <p class="font-semibold mb-2">Cómo registrar desde el celular:</p>
                <ol style="padding-left:1.25rem;list-style:decimal;line-height:1.8;">
                    <li>Abre la <strong>cámara</strong> de tu teléfono y apunta al código QR de la factura.</li>
                    <li>Toca el enlace que aparece (<em>dgi-fep.mef.gob.pa…</em>) para abrirlo, <strong>copia la URL</strong> de la barra del navegador, y regresa aquí.</li>
                    <li>Pega la URL en el campo de abajo y toca <strong>Registrar</strong>.</li>
                </ol>
                <p class="mt-2 text-blue-700">También puedes pegar el CUFE directamente si lo tienes a mano.</p>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.cxp.facturas.desde-cufe') }}">
                    @csrf
                    <div>
                        <x-input-label for="cufe_input" value="URL del QR o CUFE *" />
                        <textarea id="cufe_input" name="cufe_input" rows="4"
                                  placeholder="https://dgi-fep.mef.gob.pa/Consultas/FacturasPorCUFE/FE0120...&#10;— o —&#10;FE01200001738771-1-693932-..."
                                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  required autofocus>{{ old('cufe_input') }}</textarea>
                    </div>

                    <div class="mt-5">
                        <button type="submit"
                                style="width:100%;background:#2563eb;color:#fff;padding:0.65rem 1.25rem;border-radius:0.375rem;font-size:0.95rem;font-weight:600;border:none;cursor:pointer;">
                            Registrar factura
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
