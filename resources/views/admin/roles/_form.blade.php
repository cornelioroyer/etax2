@php
    $role = $role ?? null;
    $esProtegido = $role && $role->esProtegido();
    $nombreActual = old('name', $role?->name);
    $descripcionActual = old('descripcion', $role?->descripcion);
@endphp

<div class="space-y-6">
    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="rounded-lg bg-white p-4 shadow-sm space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Nombre del rol</label>
            @if ($esProtegido)
                <input type="text" value="{{ $role->etiqueta() }}" disabled
                       class="mt-1 block w-full rounded-md border-gray-200 bg-gray-100 text-gray-500 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">Este es un rol base del sistema: su nombre no se puede cambiar.</p>
            @else
                <input type="text" name="name" value="{{ $nombreActual }}" required maxlength="100"
                       placeholder="Ej.: Cajero, Contador, Solo lectura"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-500">Se guarda como clave técnica en minúsculas (ej.: «Cajero General» → <span class="font-mono">cajero_general</span>).</p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Descripción (opcional)</label>
            <input type="text" name="descripcion" value="{{ $descripcionActual }}" maxlength="255"
                   placeholder="Ej.: Registra cobros y maneja la caja"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div class="flex items-center gap-4">
        <button type="submit" class="rounded-md bg-gray-900 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700">
            {{ $role ? 'Guardar cambios' : 'Crear rol' }}
        </button>
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
    </div>
</div>
