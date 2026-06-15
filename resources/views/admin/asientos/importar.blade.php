<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Importar asiento desde Excel</h2>
            <a href="{{ route('admin.asientos.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Volver a asientos</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold mb-1">Revisa el archivo:</p>
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            {{-- Instrucciones --}}
            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900 space-y-2">
                <p class="font-semibold">Cómo preparar el archivo</p>
                <p>La primera fila debe tener los encabezados y luego una fila por cuenta:</p>
                <div class="overflow-x-auto">
                    <table class="mt-1 border border-blue-200 bg-white text-xs">
                        <thead class="bg-blue-100">
                            <tr>
                                <th class="border border-blue-200 px-3 py-1 text-left">codigo</th>
                                <th class="border border-blue-200 px-3 py-1 text-left">descripcion</th>
                                <th class="border border-blue-200 px-3 py-1 text-right">debito</th>
                                <th class="border border-blue-200 px-3 py-1 text-right">credito</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <tr><td class="border border-blue-200 px-3 py-1">10101</td><td class="border border-blue-200 px-3 py-1">Saldo inicial Caja</td><td class="border border-blue-200 px-3 py-1 text-right">1500.00</td><td class="border border-blue-200 px-3 py-1 text-right"></td></tr>
                            <tr><td class="border border-blue-200 px-3 py-1">30101</td><td class="border border-blue-200 px-3 py-1">Saldo inicial Capital</td><td class="border border-blue-200 px-3 py-1 text-right"></td><td class="border border-blue-200 px-3 py-1 text-right">1500.00</td></tr>
                        </tbody>
                    </table>
                </div>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Cada línea lleva <strong>débito o crédito</strong>, no ambos. El total de débitos debe igualar el de créditos.</li>
                    <li>El <strong>código</strong> debe existir en el plan de cuentas y ser cuenta de movimiento.</li>
                    <li>Las cuentas de control (Cuentas por Cobrar, Cuentas por Pagar, Inventario) <strong>no</strong> se cargan aquí: su saldo inicial entra por sus propios módulos.</li>
                    <li>Acepta archivos <strong>.xlsx, .xls o .csv</strong>.</li>
                </ul>
                <a href="{{ route('admin.asientos.importar.plantilla') }}"
                   style="display:inline-block;background:#2563eb;color:#fff;padding:6px 14px;border-radius:6px;font-size:13px;text-decoration:none;margin-top:4px;">
                    Descargar plantilla de ejemplo
                </a>
            </div>

            {{-- Formulario --}}
            <form method="POST" action="{{ route('admin.asientos.importar') }}" enctype="multipart/form-data"
                  class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="fecha" value="Fecha del asiento *" />
                        <x-text-input id="fecha" name="fecha" type="text" class="js-date mt-1 block w-full"
                                      :value="old('fecha')" placeholder="dd/mm/aaaa" required />
                        <p class="mt-1 text-xs text-gray-500">Normalmente el primer día del período inicial (ej. 01/01/{{ now()->year }}).</p>
                    </div>
                    <div>
                        <x-input-label for="referencia" value="Referencia (opcional)" />
                        <x-text-input id="referencia" name="referencia" type="text" class="mt-1 block w-full"
                                      :value="old('referencia')" maxlength="100" />
                    </div>
                </div>
                <div>
                    <x-input-label for="descripcion" value="Descripción (opcional)" />
                    <x-text-input id="descripcion" name="descripcion" type="text" class="mt-1 block w-full"
                                  :value="old('descripcion', 'Saldos iniciales')" maxlength="500" />
                </div>
                <div>
                    <x-input-label for="archivo" value="Archivo Excel/CSV *" />
                    <input id="archivo" name="archivo" type="file" accept=".xlsx,.xls,.csv,.txt" required
                           class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200" />
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            style="background:#4f46e5;color:#fff;padding:8px 18px;border-radius:6px;font-size:14px;font-weight:600;border:none;cursor:pointer;">
                        Importar como borrador
                    </button>
                    <span class="text-xs text-gray-500">Se crea un borrador para que lo revises antes de postear.</span>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
