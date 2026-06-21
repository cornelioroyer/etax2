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

            @if (session('ok_factura'))
                @php $ok = session('ok_factura'); @endphp
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800 flex items-center justify-between gap-3">
                    <span>✓ Factura <strong>{{ $ok['numero'] }}</strong> {{ $ok['aviso'] ?? 'registrada' }}. Escanea la siguiente.</span>
                    <a href="{{ $ok['url'] }}" class="shrink-0 font-semibold text-green-700 underline">Ver factura →</a>
                </div>
            @endif

            {{-- Botón principal: leer factura con IA --}}
            <div id="panel-inicio" class="bg-white p-6 shadow-sm sm:rounded-lg text-center">
                <p class="mb-5 text-sm text-gray-600">
                    Toma una <strong>foto de la factura</strong> donde se vea el código QR o el CUFE.<br>
                    La IA lee los datos y consulta la DGI para que los confirmes.
                </p>
                <input type="file" id="foto-ia" accept="image/*" capture="environment" style="display:none;" onchange="enviarFoto(this)">
                <button type="button" onclick="document.getElementById('foto-ia').click()"
                        style="display:inline-flex;align-items:center;gap:0.6rem;background:#4f46e5;color:#fff;border:none;border-radius:0.5rem;padding:0.75rem 1.75rem;font-size:1rem;font-weight:700;cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:1.4rem;height:1.4rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L3.32 8.91a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h13.5A2.25 2.25 0 0 0 21 18V9.572a2.25 2.25 0 0 0-.1-.661L19.24 5.338A2.25 2.25 0 0 0 17.088 3.75H15M9 3.75h6M9 3.75 8.25 6m6.75-2.25L15.75 6m-3 3.75a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
                    </svg>
                    Leer factura con IA
                </button>
                <p class="text-xs text-gray-400 mt-2">Toma una foto donde se vea el CUFE; la IA lo lee y consulta la DGI.</p>
            </div>

            {{-- Estado / spinner --}}
            <div id="panel-cargando" style="display:none;" class="bg-white p-6 shadow-sm sm:rounded-lg text-center">
                <div style="display:inline-block;width:2rem;height:2rem;border:3px solid #e5e7eb;border-top-color:#4f46e5;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                <p id="msg-cargando" class="mt-3 text-sm text-gray-600">Consultando la DGI…</p>
            </div>

            {{-- Preview de la factura --}}
            <div id="panel-preview" style="display:none;" class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div id="prev-aviso-ia" style="display:none;background:#fef3c7;color:#92400e;font-size:0.8rem;font-weight:600;padding:0.6rem 1rem;text-align:center;">
                    ⚠ Datos leídos por IA (sin verificar con la DGI) — revisa montos y líneas antes de contabilizar.
                </div>
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
                            <input type="hidden" name="datos_ia" id="datos_ia_hidden">
                            <input type="hidden" name="archivo_path" id="archivo_path_hidden">
                            <input type="hidden" name="archivo_disk" id="archivo_disk_hidden">
                            {{-- Registrar y volver para seguir agregando --}}
                            <button type="submit" name="seguir" value="1"
                                    style="width:100%;background:#16a34a;color:#fff;border:none;border-radius:0.375rem;padding:0.7rem;font-size:0.95rem;font-weight:700;cursor:pointer;">
                                ✓ Registrar y leer otra
                            </button>
                            <div class="flex gap-3 mt-2">
                                <button type="submit"
                                        style="flex:1;background:#e0e7ff;color:#3730a3;border:none;border-radius:0.375rem;padding:0.55rem;font-size:0.85rem;font-weight:600;cursor:pointer;">
                                    Registrar y ver factura
                                </button>
                                <button type="button" onclick="resetear()"
                                        style="flex:0;background:#f3f4f6;color:#374151;border:none;border-radius:0.375rem;padding:0.55rem 1rem;font-size:0.85rem;font-weight:600;cursor:pointer;">
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

    <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
    <script src="/js/jsqr.min.js"></script>
    <script>
    var _csrf = document.querySelector('meta[name="csrf-token"]').content;

    // ── PASO 1: intenta leer QR en el cliente (gratis) ───────────────
    function enviarFoto(input) {
        var file = input.files && input.files[0];
        if (!file) return;

        mostrarCargando('Leyendo código QR…');

        leerQrDeImagen(file)
            .then(function (qrUrl) {
                if (qrUrl) {
                    mostrarCargando('Buscando factura en la DGI…');
                    return fetch('{{ route('admin.cxp.facturas.consultar-cufe') }}', {
                        method : 'POST',
                        headers: { 'X-CSRF-TOKEN': _csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body   : JSON.stringify({ qr: qrUrl }),
                    })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (r) {
                        if (r.ok && !r.data.error) return r;
                        // QR leído pero DGI no la encontró → fallback a IA
                        return enviarFotoAIa(file);
                    });
                }
                // Sin QR legible → IA directa
                return enviarFotoAIa(file);
            })
            .then(function (r) {
                if (!r) return;
                if (!r.ok) { mostrarError(r.data.error || 'No se pudo leer la factura.'); return; }
                if (r.data.ya_registrada) { mostrarYaRegistrada(r.data); return; }
                mostrarPreview(r.data.cufe, r.data);
            })
            .catch(function () { mostrarError('No se pudo conectar al servidor.'); })
            .finally(function () { input.value = ''; });
    }

    // ── PASO 2 (fallback): enviar foto a la IA ────────────────────────
    function enviarFotoAIa(file) {
        mostrarCargando('Leyendo la factura con IA…');
        var fd = new FormData();
        fd.append('foto', file);
        return fetch('{{ route('admin.cxp.facturas.cufe-desde-foto') }}', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': _csrf, 'Accept': 'application/json' },
            body   : fd,
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    // ── Decodifica el QR de una imagen en el cliente ──────────────────
    // Devuelve Promise<string|null> con la URL del QR o null si no lo encuentra.
    function leerQrDeImagen(file) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.onload = function () {
                // Escalar a máx 2 000 px: fotos de celular (4 000×3 000+) superan
                // el límite de canvas en móvil y jsQR falla silenciosamente.
                var MAX   = 2000;
                var sw    = img.naturalWidth  || img.width;
                var sh    = img.naturalHeight || img.height;
                var scale = Math.min(1, MAX / Math.max(sw, sh));
                var w     = Math.round(sw * scale);
                var h     = Math.round(sh * scale);

                var canvas = document.createElement('canvas');
                var ctx    = canvas.getContext('2d');

                // Intento 1: BarcodeDetector nativo (Chrome Android, más rápido)
                if (window.BarcodeDetector) {
                    new BarcodeDetector({ formats: ['qr_code'] }).detect(img)
                        .then(function (codes) {
                            if (codes.length > 0) { liberar(); resolve(codes[0].rawValue); }
                            else tryJsQR();
                        })
                        .catch(tryJsQR);
                } else {
                    tryJsQR();
                }

                // Intento 2: jsQR en 4 rotaciones sobre imagen escalada
                function tryJsQR() {
                    if (!window.jsQR) { liberar(); resolve(null); return; }
                    var angulos = [0, 90, 180, 270];
                    for (var i = 0; i < angulos.length; i++) {
                        var deg = angulos[i];
                        var cw  = (deg === 90 || deg === 270) ? h : w;
                        var ch  = (deg === 90 || deg === 270) ? w : h;
                        canvas.width = cw; canvas.height = ch;
                        ctx.save();
                        ctx.translate(cw / 2, ch / 2);
                        ctx.rotate(deg * Math.PI / 180);
                        ctx.drawImage(img, -w / 2, -h / 2, w, h);
                        ctx.restore();
                        var data = ctx.getImageData(0, 0, cw, ch);
                        var code = jsQR(data.data, data.width, data.height, { inversionAttempts: 'dontInvert' });
                        if (code) { liberar(); resolve(code.data); return; }
                    }
                    liberar(); resolve(null);
                }

                function liberar() { URL.revokeObjectURL(img.src); }
            };
            img.onerror = function () { resolve(null); };
            img.src = URL.createObjectURL(file);
        });
    }

    // ── PREVIEW ─────────────────────────────────────────────────────
    function mostrarPreview(cufe, d) {
        ocultarTodo();

        // Archivo de respaldo (foto subida a S3 en el paso de IA), si lo hay.
        document.getElementById('archivo_path_hidden').value = d.archivo_path || '';
        document.getElementById('archivo_disk_hidden').value = d.archivo_disk || '';

        // Origen de los datos: DGI (oficial) o IA (de la foto, sin verificar).
        var esIA = (d.via === 'ia');
        document.getElementById('prev-aviso-ia').style.display = esIA ? 'block' : 'none';
        if (esIA) {
            document.getElementById('datos_ia_hidden').value = d.datos_ia || JSON.stringify(d);
            document.getElementById('cufe_hidden').value = '';
        } else {
            document.getElementById('datos_ia_hidden').value = '';
            document.getElementById('cufe_hidden').value = cufe || '';
        }

        var tipo = d.tipo === 'NOTA_CREDITO' ? 'Nota de Crédito' : d.tipo === 'NOTA_DEBITO' ? 'Nota de Débito' : 'Factura';
        setText('prev-tipo',   esIA ? tipo + ' · IA' : tipo);
        setText('prev-numero', 'N° ' + (d.numero || (cufe ? cufe.slice(0, 15) + '…' : '—')));
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
