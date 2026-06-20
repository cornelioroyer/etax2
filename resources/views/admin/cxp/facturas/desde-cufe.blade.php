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
                    Escanea el <strong>código QR</strong> de la factura electrónica con la cámara, o pega el <strong>CUFE</strong> directamente.
                    El sistema consultará la DGI, creará el proveedor si no existe y registrará la factura en borrador con sus líneas reales.
                </p>

                {{-- Input de cámara (oculto) --}}
                <input type="file" id="input-camara" accept="image/*" capture="environment" style="display:none">

                {{-- Botón de cámara --}}
                <div class="mb-5">
                    <button type="button" id="btn-escanear"
                            style="display:inline-flex;align-items:center;gap:0.5rem;border-radius:0.375rem;border:1px solid #6366f1;background:#eef2ff;padding:0.5rem 1.1rem;font-size:0.875rem;font-weight:600;color:#4338ca;cursor:pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:1.1rem;height:1.1rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                        </svg>
                        Escanear QR con cámara
                    </button>
                    <span id="qr-status" style="display:none;margin-left:0.75rem;font-size:0.8rem;"></span>
                </div>

                <form method="POST" action="{{ route('admin.cxp.facturas.desde-cufe') }}">
                    @csrf
                    <div>
                        <x-input-label for="cufe_input" value="URL del QR o CUFE *" />
                        <textarea id="cufe_input" name="cufe_input" rows="4"
                                  placeholder="https://dgi-fep.mef.gob.pa/Consultas/FacturasPorCUFE/FE0120...&#10;— o —&#10;FE0120..."
                                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  required>{{ old('cufe_input') }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            El escáner rellena este campo automáticamente, o pega la URL/CUFE manualmente.
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
                En el PDF de la factura electrónica hay un código QR. Usa el botón de cámara para fotografiarlo,
                o copia el enlace con tu celular y pégalo en el campo de arriba.
            </div>
        </div>
    </div>

    <canvas id="qr-canvas" style="display:none;"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
    document.getElementById('btn-escanear').addEventListener('click', function () {
        document.getElementById('input-camara').click();
    });

    document.getElementById('input-camara').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        var status   = document.getElementById('qr-status');
        var textarea = document.getElementById('cufe_input');
        var canvas   = document.getElementById('qr-canvas');
        var ctx      = canvas.getContext('2d');

        status.style.display = 'inline';
        status.style.color   = '#6b7280';
        status.textContent   = 'Procesando imagen…';

        var img  = new Image();
        var url  = URL.createObjectURL(file);

        img.onload = function () {
            canvas.width  = img.naturalWidth;
            canvas.height = img.naturalHeight;
            ctx.drawImage(img, 0, 0);
            URL.revokeObjectURL(url);

            var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var code      = jsQR(imageData.data, imageData.width, imageData.height);

            if (code) {
                textarea.value     = code.data;
                status.textContent = '✓ QR detectado. Revisa el campo y haz clic en «Consultar DGI y registrar».';
                status.style.color = '#16a34a';
            } else {
                status.textContent = 'No se detectó un QR en la imagen. Acerca más la cámara e intenta de nuevo.';
                status.style.color = '#dc2626';
            }
        };

        img.onerror = function () {
            URL.revokeObjectURL(url);
            status.textContent = 'No se pudo leer la imagen.';
            status.style.color = '#dc2626';
        };

        img.src = url;

        // Limpiar el input para permitir re-selección del mismo archivo
        this.value = '';
    });
    </script>
</x-app-layout>
