<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar factura por QR</h2>
            <a href="{{ route('admin.cxp.facturas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Botón principal --}}
            <div id="panel-inicio" class="bg-white p-6 shadow-sm sm:rounded-lg text-center">
                <p class="mb-5 text-sm text-gray-600">
                    Apunta la cámara al <strong>código QR</strong> de la factura electrónica.<br>
                    El sistema consulta la DGI y muestra los datos para que confirmes.
                </p>
                <button type="button" onclick="abrirScanner()"
                        style="display:inline-flex;align-items:center;gap:0.6rem;background:#4f46e5;color:#fff;border:none;border-radius:0.5rem;padding:0.75rem 1.75rem;font-size:1rem;font-weight:700;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:1.4rem;height:1.4rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                    Escanear QR
                </button>
                <div class="mt-5 border-t pt-4">
                    <p class="text-xs text-gray-500 mb-2">¿Ya tienes el CUFE o la URL? Pégalo aquí:</p>
                    <div class="flex gap-2">
                        <input type="text" id="cufe-manual"
                               placeholder="FE01200001... o URL dgi-fep..."
                               class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" onclick="procesarCufeManual()"
                                style="background:#2563eb;color:#fff;border:none;border-radius:0.375rem;padding:0.5rem 1rem;font-size:0.875rem;font-weight:600;white-space:nowrap;cursor:pointer;">
                            Buscar
                        </button>
                    </div>
                </div>
            </div>

            {{-- Estado / spinner --}}
            <div id="panel-cargando" style="display:none;" class="bg-white p-6 shadow-sm sm:rounded-lg text-center">
                <div style="display:inline-block;width:2rem;height:2rem;border:3px solid #e5e7eb;border-top-color:#4f46e5;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                <p id="msg-cargando" class="mt-3 text-sm text-gray-600">Consultando la DGI…</p>
            </div>

            {{-- Preview de la factura --}}
            <div id="panel-preview" style="display:none;" class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div style="background:#4f46e5;padding:1rem 1.5rem;">
                    <div class="flex justify-between items-start">
                        <div>
                            <div id="prev-tipo" style="font-size:0.75rem;font-weight:600;color:#c7d2fe;text-transform:uppercase;letter-spacing:.05em;"></div>
                            <div id="prev-numero" style="font-size:1.4rem;font-weight:700;color:#fff;"></div>
                            <div id="prev-fecha" style="font-size:0.85rem;color:#c7d2fe;margin-top:0.1rem;"></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.75rem;color:#c7d2fe;">TOTAL</div>
                            <div id="prev-total" style="font-size:1.6rem;font-weight:700;color:#fff;"></div>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    {{-- Emisor --}}
                    <div style="border-radius:0.375rem;background:#f9fafb;padding:0.875rem 1rem;">
                        <div style="font-size:0.7rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:0.4rem;">Emisor (Proveedor)</div>
                        <div id="prev-emisor-nombre" style="font-weight:600;font-size:0.9rem;"></div>
                        <div id="prev-emisor-ruc" style="font-size:0.8rem;color:#6b7280;"></div>
                    </div>

                    {{-- Montos --}}
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;">
                        <div style="border-radius:0.375rem;background:#f9fafb;padding:0.75rem;text-align:center;">
                            <div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase;">Subtotal</div>
                            <div id="prev-subtotal" style="font-size:1rem;font-weight:600;margin-top:0.2rem;"></div>
                        </div>
                        <div style="border-radius:0.375rem;background:#f9fafb;padding:0.75rem;text-align:center;">
                            <div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase;">ITBMS</div>
                            <div id="prev-itbms" style="font-size:1rem;font-weight:600;margin-top:0.2rem;"></div>
                        </div>
                        <div style="border-radius:0.375rem;background:#eef2ff;padding:0.75rem;text-align:center;">
                            <div style="font-size:0.7rem;color:#4f46e5;text-transform:uppercase;font-weight:600;">Total</div>
                            <div id="prev-total2" style="font-size:1rem;font-weight:700;color:#4f46e5;margin-top:0.2rem;"></div>
                        </div>
                    </div>

                    {{-- Líneas --}}
                    <div>
                        <div style="font-size:0.7rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:0.5rem;">Detalle</div>
                        <div id="prev-lineas" style="font-size:0.8rem;"></div>
                    </div>

                    {{-- Acciones --}}
                    <div id="panel-acciones">
                        <form method="POST" action="{{ route('admin.cxp.facturas.desde-cufe') }}">
                            @csrf
                            <input type="hidden" name="cufe_input" id="cufe_hidden">
                            <div class="flex gap-3 mt-2">
                                <button type="submit"
                                        style="flex:1;background:#16a34a;color:#fff;border:none;border-radius:0.375rem;padding:0.65rem;font-size:0.9rem;font-weight:700;cursor:pointer;">
                                    ✓ Registrar factura
                                </button>
                                <button type="button" onclick="resetear()"
                                        style="flex:0;background:#f3f4f6;color:#374151;border:none;border-radius:0.375rem;padding:0.65rem 1rem;font-size:0.9rem;font-weight:600;cursor:pointer;">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Error --}}
            <div id="panel-error" style="display:none;" class="rounded-md bg-red-50 p-4">
                <p id="msg-error" class="text-sm text-red-800"></p>
                <button onclick="resetear()" style="margin-top:0.5rem;font-size:0.8rem;color:#991b1b;background:none;border:none;cursor:pointer;text-decoration:underline;">Volver a intentar</button>
            </div>

        </div>
    </div>

    {{-- Modal cámara --}}
    <div id="qr-modal"
         style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,0.9);align-items:center;justify-content:center;"
         onclick="if(event.target===this)cerrarCamara()">
        <div style="background:#fff;border-radius:0.75rem;padding:1rem;width:min(380px,94vw);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                <span style="font-weight:700;">Apunta al código QR</span>
                <button type="button" onclick="cerrarCamara()"
                        style="font-size:1.5rem;line-height:1;border:none;background:none;cursor:pointer;color:#9ca3af;">&times;</button>
            </div>
            <div style="position:relative;border-radius:0.375rem;overflow:hidden;background:#000;">
                <video id="qr-video" style="width:100%;display:block;max-height:50vh;object-fit:cover;" playsinline autoplay muted></video>
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                    <div style="width:60%;aspect-ratio:1;border:3px solid #4f46e5;border-radius:8px;box-shadow:0 0 0 9999px rgba(0,0,0,0.35);"></div>
                </div>
            </div>
            <canvas id="qr-canvas" style="display:none;"></canvas>
            <p id="cam-msg" style="margin-top:0.75rem;text-align:center;font-size:0.8rem;color:#6b7280;">
                Apunta al código QR de la factura…
            </p>
        </div>
    </div>

    <script src="/js/jsqr.min.js"></script>
    <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
    <script>
    var _stream   = null;
    var _scanning = false;
    var _timer    = null;
    var _cufeUrl  = '{{ route('admin.cxp.facturas.consultar-cufe') }}';
    var _csrf     = document.querySelector('meta[name="csrf-token"]').content;

    window.addEventListener('error', function (ev) {
        var el = document.getElementById('cam-msg');
        if (el) { el.textContent = 'JS: ' + ev.message; el.style.color = '#dc2626'; }
    });

    // ── CÁMARA ──────────────────────────────────────────────────────
    function abrirScanner() {
        document.getElementById('qr-modal').style.display = 'flex';
        setCamMsg('Iniciando cámara…', '#6b7280');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setCamMsg('Tu navegador no soporta la cámara. Usa el campo de texto.', '#dc2626');
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width:  { ideal: 1920 },
                height: { ideal: 1080 }
            }
        })
            .then(function (s) {
                _stream   = s;
                _scanning = true;
                var v = document.getElementById('qr-video');
                v.srcObject = s;
                v.play();
                setCamMsg('Apunta al código QR de la factura…', '#6b7280');
                iniciarDeteccion(v);
            })
            .catch(function (e) {
                setCamMsg('Sin acceso a la cámara: ' + e.message, '#dc2626');
            });
    }

    function cerrarCamara() {
        _scanning = false;
        if (_timer) { clearInterval(_timer); _timer = null; }
        if (_stream) { _stream.getTracks().forEach(function (t) { t.stop(); }); _stream = null; }
        var v = document.getElementById('qr-video');
        v.srcObject = null;
        document.getElementById('qr-modal').style.display = 'none';
    }

    function iniciarDeteccion(video) {
        var tieneBD   = ('BarcodeDetector' in window);
        var tieneJsqr = (typeof jsQR === 'function');
        var frames    = 0;
        var detector  = tieneBD ? new BarcodeDetector({ formats: ['qr_code'] }) : null;
        var c  = document.getElementById('qr-canvas');
        var cx = c.getContext('2d', { willReadFrequently: true });

        setCamMsg('Detector: ' + (tieneBD ? 'nativo' : (tieneJsqr ? 'jsQR' : 'NINGUNO ❌')) + ' — iniciando…', '#6b7280');

        if (!tieneBD && !tieneJsqr) {
            setCamMsg('Este navegador no puede leer QR. Pega el CUFE/URL en el campo de texto.', '#dc2626');
            return;
        }

        function exito(valor) {
            if (!_scanning) return;
            _scanning = false;
            clearInterval(_timer);
            setCamMsg('✓ QR detectado', '#16a34a');
            cerrarCamara();
            consultarDGI(valor);
        }

        // jsQR sobre el frame actual, probando 4 rotaciones (vence rotación Android)
        function intentarJsqr() {
            if (video.videoWidth === 0) return;
            var w = video.videoWidth, h = video.videoHeight;
            for (var rot = 0; rot < 4; rot++) {
                if (rot === 0 || rot === 2) { c.width = w; c.height = h; }
                else                        { c.width = h; c.height = w; }
                cx.save();
                cx.translate(c.width / 2, c.height / 2);
                cx.rotate(rot * Math.PI / 2);
                cx.drawImage(video, -w / 2, -h / 2, w, h);
                cx.restore();
                try {
                    var img  = cx.getImageData(0, 0, c.width, c.height);
                    var code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
                    if (code && code.data) { exito(code.data); return; }
                } catch (e) {}
            }
        }

        _timer = setInterval(function () {
            if (!_scanning) return;
            frames++;
            if (frames % 4 === 0) {
                var res = video.videoWidth ? (video.videoWidth + '×' + video.videoHeight) : '?';
                setCamMsg('Buscando QR… (' + frames + ') · ' + res, '#6b7280');
            }
            // Detector nativo (rápido) Y jsQR con rotaciones (robusto) en paralelo
            if (tieneBD) {
                detector.detect(video).then(function (codes) {
                    if (codes && codes.length > 0) { exito(codes[0].rawValue); }
                }).catch(function () {});
            }
            if (tieneJsqr) { intentarJsqr(); }
        }, 250);
    }

    function setCamMsg(t, c) {
        var el = document.getElementById('cam-msg');
        el.textContent = t; el.style.color = c;
    }

    // ── CAMPO MANUAL ────────────────────────────────────────────────
    function procesarCufeManual() {
        var val = document.getElementById('cufe-manual').value.trim();
        if (!val) return;
        consultarDGI(val);
    }

    document.getElementById('cufe-manual').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); procesarCufeManual(); }
    });

    // ── CONSULTA DGI ────────────────────────────────────────────────
    function consultarDGI(raw) {
        // Extrae CUFE de una URL o lo usa directo
        var cufe = raw;
        var m = raw.match(/\/FacturasPorCUFE\/([A-Za-z0-9\-]+)/i);
        if (m) cufe = m[1];

        mostrarCargando('Consultando la DGI…');

        fetch(_cufeUrl, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrf, 'Accept': 'application/json' },
            body   : JSON.stringify({ cufe: cufe }),
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (r) {
            if (!r.ok) { mostrarError(r.data.error || r.data.message || 'Error al consultar la DGI.'); return; }
            if (r.data.ya_registrada) {
                mostrarYaRegistrada(r.data);
                return;
            }
            mostrarPreview(cufe, r.data);
        })
        .catch(function () { mostrarError('No se pudo conectar al servidor.'); });
    }

    // ── PREVIEW ─────────────────────────────────────────────────────
    function mostrarPreview(cufe, d) {
        ocultarTodo();
        document.getElementById('cufe_hidden').value = cufe;

        var tipo = d.tipo === 'NOTA_CREDITO' ? 'Nota de Crédito' : d.tipo === 'NOTA_DEBITO' ? 'Nota de Débito' : 'Factura';
        setText('prev-tipo',   tipo);
        setText('prev-numero', 'N° ' + (d.numero || cufe.slice(0, 15) + '…'));
        setText('prev-fecha',  d.fecha || '');
        setText('prev-total',  'B/. ' + fmt(d.total));
        setText('prev-total2', 'B/. ' + fmt(d.total));
        setText('prev-subtotal', 'B/. ' + fmt(d.subtotal));
        setText('prev-itbms',    'B/. ' + fmt(d.itbms));
        setText('prev-emisor-nombre', (d.emisor && d.emisor.nombre) ? d.emisor.nombre : '—');
        setText('prev-emisor-ruc',    (d.emisor && d.emisor.ruc)    ? 'RUC ' + d.emisor.ruc + (d.emisor.dv ? '-' + d.emisor.dv : '') : '');

        var lineasHtml = '';
        if (d.lineas && d.lineas.length) {
            lineasHtml = '<table style="width:100%;border-collapse:collapse;">';
            d.lineas.forEach(function (l, i) {
                lineasHtml += '<tr style="border-top:1px solid #f3f4f6;' + (i === 0 ? 'border-top:none;' : '') + '">'
                    + '<td style="padding:0.3rem 0.5rem 0.3rem 0;">' + escHtml(l.descripcion) + '</td>'
                    + '<td style="padding:0.3rem 0;text-align:right;white-space:nowrap;color:#6b7280;">' + fmt(l.cantidad) + ' × ' + fmt(l.precio_unitario) + '</td>'
                    + '<td style="padding:0.3rem 0 0.3rem 0.5rem;text-align:right;white-space:nowrap;font-weight:500;">B/. ' + fmt(l.total) + '</td>'
                    + '</tr>';
            });
            lineasHtml += '</table>';
        } else {
            lineasHtml = '<span style="color:#9ca3af;">Sin detalle de líneas</span>';
        }
        document.getElementById('prev-lineas').innerHTML = lineasHtml;

        document.getElementById('panel-preview').style.display = 'block';
    }

    function mostrarYaRegistrada(d) {
        ocultarTodo();
        document.getElementById('panel-error').style.display = 'block';
        document.getElementById('msg-error').innerHTML =
            'Esta factura ya fue registrada como <strong>' + escHtml(d.numero) + '</strong>. '
            + '<a href="' + escHtml(d.url) + '" style="color:#1d4ed8;text-decoration:underline;">Ver factura →</a>';
    }

    function mostrarCargando(msg) {
        ocultarTodo();
        document.getElementById('msg-cargando').textContent = msg;
        document.getElementById('panel-cargando').style.display = 'block';
    }

    function mostrarError(msg) {
        ocultarTodo();
        document.getElementById('msg-error').textContent = msg;
        document.getElementById('panel-error').style.display = 'block';
    }

    function resetear() {
        ocultarTodo();
        document.getElementById('cufe-manual').value = '';
    }

    function ocultarTodo() {
        ['panel-cargando','panel-preview','panel-error'].forEach(function (id) {
            document.getElementById(id).style.display = 'none';
        });
    }

    // ── HELPERS ──────────────────────────────────────────────────────
    function setText(id, val) { document.getElementById(id).textContent = val; }
    function fmt(n) { return Number(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    </script>
</x-app-layout>
