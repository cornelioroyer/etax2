<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $empleado ? 'Ficha de empleado — '.$empleado->nombreCompleto() : 'Nuevo empleado' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <div class="text-sm">
                <a href="{{ route('admin.nomina.empleados.index') }}" class="text-indigo-600 hover:underline">← Volver a empleados</a>
            </div>

            <form method="POST"
                  action="{{ $empleado ? route('admin.nomina.empleados.update', $empleado) : route('admin.nomina.empleados.store') }}"
                  x-data="{ tipoSalario: '{{ old('tipo_salario', $empleado->tipo_salario ?? 'FIJO') }}' }">
                @csrf
                @if ($empleado) @method('PUT') @endif

                <div class="bg-white p-6 shadow-sm sm:rounded-lg space-y-6">
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 border-b pb-2">Datos personales</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div>
                                <x-input-label value="Código *" />
                                <x-text-input name="codigo" type="text" class="mt-1 block w-full"
                                    value="{{ old('codigo', $empleado->codigo ?? '') }}" required maxlength="20" />
                            </div>
                            <div>
                                <x-input-label value="Nombre *" />
                                <x-text-input name="nombre" type="text" class="mt-1 block w-full"
                                    value="{{ old('nombre', $empleado->nombre ?? '') }}" required maxlength="150" />
                            </div>
                            <div>
                                <x-input-label value="Apellido *" />
                                <x-text-input name="apellido" type="text" class="mt-1 block w-full"
                                    value="{{ old('apellido', $empleado->apellido ?? '') }}" required maxlength="150" />
                            </div>
                            <div>
                                <x-input-label value="Cédula" />
                                <x-text-input name="cedula" type="text" class="mt-1 block w-full"
                                    value="{{ old('cedula', $empleado->cedula ?? '') }}" maxlength="30" placeholder="8-123-456" />
                            </div>
                            <div>
                                <x-input-label value="Nº Seguro Social" />
                                <x-text-input name="seguro_social" type="text" class="mt-1 block w-full"
                                    value="{{ old('seguro_social', $empleado->seguro_social ?? '') }}" maxlength="30" />
                            </div>
                            <div>
                                <x-input-label value="Fecha de nacimiento" />
                                <x-text-input name="fecha_nacimiento" type="date" class="mt-1 block w-full"
                                    value="{{ old('fecha_nacimiento', $empleado?->fecha_nacimiento?->toDateString()) }}" />
                            </div>
                            <div>
                                <x-input-label value="Sexo" />
                                <select name="sexo" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option value="">—</option>
                                    <option value="M" @selected(old('sexo', $empleado->sexo ?? '') === 'M')>Masculino</option>
                                    <option value="F" @selected(old('sexo', $empleado->sexo ?? '') === 'F')>Femenino</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Dependientes" />
                                <x-text-input name="dependientes" type="number" min="0" class="mt-1 block w-full"
                                    value="{{ old('dependientes', $empleado->dependientes ?? 0) }}" />
                            </div>
                            <div>
                                <x-input-label value="Email" />
                                <x-text-input name="email" type="email" class="mt-1 block w-full"
                                    value="{{ old('email', $empleado->email ?? '') }}" maxlength="200" />
                            </div>
                            <div>
                                <x-input-label value="Teléfono" />
                                <x-text-input name="telefono" type="text" class="mt-1 block w-full"
                                    value="{{ old('telefono', $empleado->telefono ?? '') }}" maxlength="50" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Dirección" />
                                <x-text-input name="direccion" type="text" class="mt-1 block w-full"
                                    value="{{ old('direccion', $empleado->direccion ?? '') }}" maxlength="500" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 border-b pb-2">Contrato y salario</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div>
                                <x-input-label value="Fecha de inicio *" />
                                <x-text-input name="fecha_inicio" type="date" class="mt-1 block w-full"
                                    value="{{ old('fecha_inicio', $empleado?->fecha_inicio?->toDateString()) }}" required />
                            </div>
                            <div>
                                <x-input-label value="Fecha de terminación" />
                                <x-text-input name="fecha_terminacion" type="date" class="mt-1 block w-full"
                                    value="{{ old('fecha_terminacion', $empleado?->fecha_terminacion?->toDateString()) }}" />
                            </div>
                            <div>
                                <x-input-label value="Departamento" />
                                <select name="departamento_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option value="">—</option>
                                    @foreach ($departamentos as $d)
                                        <option value="{{ $d->id }}" @selected((int) old('departamento_id', $empleado->departamento_id ?? 0) === $d->id)>{{ $d->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Cargo" />
                                <select name="cargo_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option value="">—</option>
                                    @foreach ($cargos as $c)
                                        <option value="{{ $c->id }}" @selected((int) old('cargo_id', $empleado->cargo_id ?? 0) === $c->id)>{{ $c->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Tipo de salario *" />
                                <select name="tipo_salario" x-model="tipoSalario" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                    <option value="FIJO">Fijo (mensual)</option>
                                    <option value="POR_HORA">Por hora</option>
                                </select>
                            </div>
                            <div x-show="tipoSalario === 'FIJO'">
                                <x-input-label value="Salario mensual B/. *" />
                                <x-text-input name="salario_mensual" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                    value="{{ old('salario_mensual', $empleado->salario_mensual ?? '') }}" />
                            </div>
                            <div x-show="tipoSalario === 'POR_HORA'" x-cloak>
                                <x-input-label value="Tasa por hora B/. *" />
                                <x-text-input name="tasa_hora" type="number" step="0.0001" min="0" class="mt-1 block w-full"
                                    value="{{ old('tasa_hora', $empleado->tasa_hora ?? '') }}" />
                            </div>
                            <div>
                                <x-input-label value="Horas semanales" />
                                <x-text-input name="horas_semanales" type="number" step="0.5" min="0" max="60" class="mt-1 block w-full"
                                    value="{{ old('horas_semanales', $empleado->horas_semanales ?? 48) }}" />
                            </div>
                            <div>
                                <x-input-label value="Tipo de planilla *" />
                                <select name="tipo_planilla" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                    @foreach (\App\Models\NomEmpleado::TIPOS_PLANILLA as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('tipo_planilla', $empleado->tipo_planilla ?? 'QUINCENAL') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Estado *" />
                                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                    @foreach (\App\Models\NomEmpleado::STATUSES as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('status', $empleado->status ?? 'ACTIVO') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 border-b pb-2">Pago</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <div>
                                <x-input-label value="Forma de pago *" />
                                <select name="forma_pago" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                    @foreach (\App\Models\NomEmpleado::FORMAS_PAGO as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('forma_pago', $empleado->forma_pago ?? 'TRANSFERENCIA') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Banco" />
                                <x-text-input name="banco" type="text" class="mt-1 block w-full"
                                    value="{{ old('banco', $empleado->banco ?? '') }}" maxlength="100" />
                            </div>
                            <div>
                                <x-input-label value="Cuenta bancaria" />
                                <x-text-input name="cuenta_bancaria" type="text" class="mt-1 block w-full"
                                    value="{{ old('cuenta_bancaria', $empleado->cuenta_bancaria ?? '') }}" maxlength="50" />
                            </div>
                            <div>
                                <x-input-label value="Tipo de cuenta" />
                                <select name="tipo_cuenta" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option value="">—</option>
                                    <option value="AHORRO" @selected(old('tipo_cuenta', $empleado->tipo_cuenta ?? '') === 'AHORRO')>Ahorro</option>
                                    <option value="CORRIENTE" @selected(old('tipo_cuenta', $empleado->tipo_cuenta ?? '') === 'CORRIENTE')>Corriente</option>
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <x-input-label value="Observaciones" />
                                <textarea name="observacion" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('observacion', $empleado->observacion ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    @can('nomina.gestionar')
                    <div class="flex gap-3">
                        <x-primary-button>{{ $empleado ? 'Actualizar empleado' : 'Crear empleado' }}</x-primary-button>
                    </div>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
