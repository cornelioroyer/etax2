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

                {{-- Botón cámara --}}
                <div class="mb-4">
                    <button type="button" id="btn-escanear"
                            style="display:inline-flex;align-items:center;gap:0.5rem;border-radius:0.375rem;border:1px solid #6366f1;background:#eef2ff;padding:0.5rem 1rem;font-size:0.875rem;font-weight:600;color:#4f46e5;cursor:pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width:1.1rem;height:1.1rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                        </svg>
                        Escanear QR con cámara
                    </button>
                    <span id="scanner-no-support" style="display:none;font-size:0.75rem;color:#ef4444;margin-left:0.5rem;">
                        Tu navegador no soporta la cámara. Pega el CUFE manualmente.
                    </span>
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
                            Escanea el QR con el botón de arriba, o pega la URL/CUFE manualmente.
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
                En el PDF de la factura electrónica recibida hay un código QR. Usa el botón de cámara para escanearlo
                directamente, o cópialo con tu celular y pega la URL completa o solo el CUFE (el código al final de la URL).
            </div>
        </div>
    </div>

    {{-- Modal escáner de QR --}}
    <div id="modal-scanner" style="display:none;position:fixed;inset:0;z-index:50;background:rgba(0,0,0,0.75);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:0.5rem;padding:1.5rem;width:min(420px,92vw);box-shadow:0 20px 60px rgba(0,0,0,0.4);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <span style="font-weight:600;font-size:0.95rem;">Escanear código QR</span>
                <button type="button" id="btn-cerrar-scanner"
                        style="font-size:1.25rem;line-height:1;color:#9ca3af;background:none;border:none;cursor:pointer;">&times;</button>
            </div>

            <div style="position:relative;border-radius:0.375rem;overflow:hidden;background:#000;">
                <video id="video-scanner" style="width:100%;display:block;" playsinline autoplay muted></video>
                {{-- Guías de encuadre --}}
                <div style="position:absolute;inset:0;pointer-events:none;">
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:55%;aspect-ratio:1;border:2px solid rgba(99,102,241,0.8);border-radius:4px;box-shadow:0 0 0 9999px rgba(0,0,0,0.35);"></div>
                </div>
            </div>
            <canvas id="canvas-scanner" style="display:none;"></canvas>

            <p id="scanner-status" style="margin-top:0.75rem;font-size:0.8rem;text-align:center;color:#6b7280;">
                Apunta la cámara al código QR de la factura…
            </p>
        </div>
    </div>

    {{-- jsQR desde CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
    (function () {
        const btnAbrir   = document.getElementById('btn-escanear');
        const btnCerrar  = document.getElementById('btn-cerrar-scanner');
        const modal      = document.getElementById('modal-scanner');
        const video      = document.getElementById('video-scanner');
        const canvas     = document.getElementById('canvas-scanner');
        const status     = document.getElementById('scanner-status');
        const noSupport  = document.getElementById('scanner-no-support');
        const textarea   = document.getElementById('cufe_input');

        let stream       = null;
        let rafId        = null;
        let detectado    = false;

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            btnAbrir.style.display = 'none';
            noSupport.style.display = 'inline';
            return;
        }

        function abrirModal() {
            detectado = false;
            modal.style.display = 'flex';
            status.textContent  = 'Iniciando cámara…';
            status.style.color  = '#6b7280';

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function (s) {
                    stream = s;
                    video.srcObject = s;
                    video.play();
                    status.textContent = 'Apunta la cámara al código QR de la factura…';
                    requestAnimationFrame(escanearFrame);
                })
                .catch(function (err) {
                    status.textContent = 'No se pudo acceder a la cámara: ' + err.message;
                    status.style.color = '#ef4444';
                });
        }

        function cerrarModal() {
            if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
            video.srcObject = null;
            modal.style.display = 'none';
        }

        function escanearFrame() {
            if (detectado) return;

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width  = video.videoWidth;
                canvas.height = video.videoHeight;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var qr  = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });

                if (qr) {
                    detectado = true;
                    status.textContent = '✓ QR detectado. Cerrando…';
                    status.style.color = '#16a34a';
                    textarea.value = qr.data;
                    setTimeout(cerrarModal, 600);
                    return;
                }
            }

            rafId = requestAnimationFrame(escanearFrame);
        }

        btnAbrir.addEventListener('click', abrirModal);
        btnCerrar.addEventListener('click', cerrarModal);

        // Cerrar al hacer clic fuera del panel
        modal.addEventListener('click', function (e) {
            if (e.target === modal) cerrarModal();
        });
    })();
    </script>
</x-app-layout>
