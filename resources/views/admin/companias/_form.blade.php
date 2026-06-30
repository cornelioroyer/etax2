@php($c = $compania ?? null)

<div class="space-y-8">
    <div>
        <h3 class="text-base font-semibold text-gray-900 border-b pb-2">Datos generales</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $c->nombre ?? '') }}" required class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="ruc" class="block text-sm font-medium text-gray-700">RUC</label>
                <input id="ruc" name="ruc" value="{{ old('ruc', $c->ruc ?? '') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('ruc') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="dv" class="block text-sm font-medium text-gray-700">DV</label>
                <input id="dv" name="dv" maxlength="2" value="{{ old('dv', $c->dv ?? '00') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('dv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="zonas_id" class="block text-sm font-medium text-gray-700">Zona</label>
                <select id="zonas_id" name="zonas_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($zonas as $zona)
                        <option value="{{ $zona->id }}" @selected(old('zonas_id', $c->zonas_id ?? $zonas->first()?->id) == $zona->id)>{{ $zona->description }}</option>
                    @endforeach
                </select>
                @error('zonas_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="activa" class="block text-sm font-medium text-gray-700">Estado</label>
                <select id="activa" name="activa" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['1' => 'Activa', '0' => 'Inactiva'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('activa', ($c->activa ?? true) ? '1' : '0') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('activa') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if (auth()->user()->is_admin)
                <div>
                    <label class="block text-sm font-medium text-gray-700">Acceso</label>
                    <label class="mt-2 inline-flex items-start gap-2">
                        <input type="hidden" name="solo_lectura" value="0">
                        <input type="checkbox" id="solo_lectura" name="solo_lectura" value="1"
                               @checked(old('solo_lectura', $c->solo_lectura ?? false))
                               class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Solo lectura: los usuarios que no son super-administradores solo podrán consultar (no registrar ni modificar) en esta compañía.</span>
                    </label>
                    @error('solo_lectura') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="correlativo_ss" class="block text-sm font-medium text-gray-700">Correlativo</label>
                <input id="correlativo_ss" name="correlativo_ss" type="number" min="0" value="{{ old('correlativo_ss', $c->correlativo_ss ?? 0) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('correlativo_ss') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fecha_de_apertura" class="block text-sm font-medium text-gray-700">Fecha de apertura</label>
                <input id="fecha_de_apertura" name="fecha_de_apertura" type="text" value="{{ old('fecha_de_apertura', $c?->fecha_de_apertura?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="js-date mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('fecha_de_apertura') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fecha_de_expiracion" class="block text-sm font-medium text-gray-700">Fecha de expiración</label>
                <input id="fecha_de_expiracion" type="text" value="{{ $c?->fecha_de_expiracion?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d') }}" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">Solo consulta — se asigna automáticamente (apertura + 30 días).</p>
            </div>

            <div>
                <label for="tipo_de_entidad" class="block text-sm font-medium text-gray-700">Tipo de entidad</label>
                <input id="tipo_de_entidad" name="tipo_de_entidad" value="{{ old('tipo_de_entidad', $c->tipo_de_entidad ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="act_economica" class="block text-sm font-medium text-gray-700">Actividad económica</label>
                <input id="act_economica" name="act_economica" value="{{ old('act_economica', $c->act_economica ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Campo "cliente" eliminado: ahora es cliente_id (FK a contactos);
                 se agregara un selector de contactos cuando exista el modulo. --}}

            <div>
                <label for="no_patronal" class="block text-sm font-medium text-gray-700">No. patronal</label>
                <input id="no_patronal" name="no_patronal" value="{{ old('no_patronal', $c->no_patronal ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-base font-semibold text-gray-900 border-b pb-2">Contacto</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="direccion" class="block text-sm font-medium text-gray-700">Dirección</label>
                <textarea id="direccion" name="direccion" rows="2" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('direccion', $c->direccion ?? '') }}</textarea>
                @error('direccion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono 1</label>
                <input id="telefono" name="telefono" value="{{ old('telefono', $c->telefono ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="telefono2" class="block text-sm font-medium text-gray-700">Teléfono 2</label>
                <input id="telefono2" name="telefono2" value="{{ old('telefono2', $c->telefono2 ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $c->email ?? auth()->user()->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="municipio" class="block text-sm font-medium text-gray-700">Municipio</label>
                <input id="municipio" name="municipio" value="{{ old('municipio', $c->municipio ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-base font-semibold text-gray-900 border-b pb-2">Representante legal</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div>
                <label for="repre_legal" class="block text-sm font-medium text-gray-700">Representante legal</label>
                <input id="repre_legal" name="repre_legal" value="{{ old('repre_legal', $c->repre_legal ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="cedula_repre_legal" class="block text-sm font-medium text-gray-700">Cédula repre. legal</label>
                <input id="cedula_repre_legal" name="cedula_repre_legal" value="{{ old('cedula_repre_legal', $c->cedula_repre_legal ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="cedula" class="block text-sm font-medium text-gray-700">Cédula</label>
                <input id="cedula" name="cedula" value="{{ old('cedula', $c->cedula ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="licencia" class="block text-sm font-medium text-gray-700">Licencia</label>
                <input id="licencia" name="licencia" value="{{ old('licencia', $c->licencia ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="cargo" class="block text-sm font-medium text-gray-700">Cargo</label>
                <input id="cargo" name="cargo" value="{{ old('cargo', $c->cargo ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="firma_cartas" class="block text-sm font-medium text-gray-700">Firma cartas</label>
                <input id="firma_cartas" name="firma_cartas" value="{{ old('firma_cartas', $c->firma_cartas ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>
    </div>

    @can('companias.campo.facturacion_fiscal')
    <div>
        <h3 class="text-base font-semibold text-gray-900 border-b pb-2">Facturación fiscal</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div>
                <label for="nit" class="block text-sm font-medium text-gray-700">NIT</label>
                <input id="nit" name="nit" value="{{ old('nit', $c->nit ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Token/clave/URL de factura fiscal eliminados: la configuracion
                 FEL por compania vive ahora en la tabla fel_configuracion
                 (modulo FEL, con token cifrado). --}}

            <div>
                <label for="clave_municipio" class="block text-sm font-medium text-gray-700">Clave municipio</label>
                <input id="clave_municipio" name="clave_municipio" value="{{ old('clave_municipio', $c->clave_municipio ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="token" class="block text-sm font-medium text-gray-700">Token</label>
                <input id="token" name="token" value="{{ old('token', $c->token ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>
    </div>
    @endcan

    <div>
        <h3 class="text-base font-semibold text-gray-900 border-b pb-2">Otros</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="mensaje" class="block text-sm font-medium text-gray-700">Mensaje</label>
                <textarea id="mensaje" name="mensaje" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('mensaje', $c->mensaje ?? '') }}</textarea>
            </div>

            <div class="md:col-span-2">
                <label for="constitucion" class="block text-sm font-medium text-gray-700">Constitución</label>
                <textarea id="constitucion" name="constitucion" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('constitucion', $c->constitucion ?? '') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Logo</label>
                <div id="logo-dropzone" class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed border-gray-300 bg-gray-50 p-4 text-center transition-colors hover:border-indigo-400 hover:bg-indigo-50">
                    <img id="logo-preview" src="{{ $c?->logo_url ? asset('storage/'.$c->logo_url) : '' }}" alt="Logo" class="{{ $c?->logo_url ? '' : 'hidden' }} mb-2 h-16 w-auto rounded border border-gray-200 bg-white object-contain p-1">
                    <p class="text-sm text-gray-600">Arrastra una imagen aquí o haz clic para seleccionar</p>
                    <p id="logo-filename" class="mt-1 text-xs text-gray-500">PNG, JPG o similar — máx. 2 MB.{{ $c?->logo_url ? ' Si subes una nueva, reemplaza la actual.' : '' }}</p>
                    <input id="logo" name="logo" type="file" accept="image/*" class="hidden">
                </div>
                @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const initDropzone = (name) => {
                        const zone = document.getElementById(name + '-dropzone');
                        const input = document.getElementById(name);
                        const preview = document.getElementById(name + '-preview');
                        const filename = document.getElementById(name + '-filename');

                        const showFile = (file) => {
                            if (!file || !file.type.startsWith('image/')) return;
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                preview.src = e.target.result;
                                preview.classList.remove('hidden');
                            };
                            reader.readAsDataURL(file);
                            filename.textContent = file.name;
                        };

                        zone.addEventListener('click', () => input.click());

                        input.addEventListener('change', () => showFile(input.files[0]));

                        ['dragover', 'dragenter'].forEach((ev) => zone.addEventListener(ev, (e) => {
                            e.preventDefault();
                            zone.classList.add('border-indigo-500', 'bg-indigo-50');
                        }));

                        ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
                            e.preventDefault();
                            zone.classList.remove('border-indigo-500', 'bg-indigo-50');
                        }));

                        zone.addEventListener('drop', (e) => {
                            const file = e.dataTransfer.files[0];
                            if (!file || !file.type.startsWith('image/')) return;
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            input.files = dt.files;
                            showFile(file);
                        });
                    };

                    initDropzone('logo');
                    initDropzone('sello');
                });
            </script>

            <div>
                <label class="block text-sm font-medium text-gray-700">Sello</label>
                <div id="sello-dropzone" class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed border-gray-300 bg-gray-50 p-4 text-center transition-colors hover:border-indigo-400 hover:bg-indigo-50">
                    <img id="sello-preview" src="{{ $c?->sello_url ? asset('storage/'.$c->sello_url) : '' }}" alt="Sello" class="{{ $c?->sello_url ? '' : 'hidden' }} mb-2 h-16 w-auto rounded border border-gray-200 bg-white object-contain p-1">
                    <p class="text-sm text-gray-600">Arrastra una imagen aquí o haz clic para seleccionar</p>
                    <p id="sello-filename" class="mt-1 text-xs text-gray-500">PNG, JPG o similar — máx. 2 MB.{{ $c?->sello_url ? ' Si subes una nueva, reemplaza la actual.' : '' }}</p>
                    <input id="sello" name="sello" type="file" accept="image/*" class="hidden">
                </div>
                @error('sello') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 border-t pt-6">
        <a href="{{ route('admin.companias.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Guardar</button>
    </div>
</div>
