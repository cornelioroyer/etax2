@php($p = $plantilla ?? null)
<div class="space-y-5">
    <div class="grid gap-5 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código <span class="text-red-500">*</span></label>
            <input type="text" name="codigo" value="{{ old('codigo', $p->codigo ?? '') }}" required maxlength="50"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm uppercase focus:outline-none focus:ring-1 focus:ring-blue-500"
                   placeholder="PA_BASICO">
            @error('codigo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" value="{{ old('nombre', $p->nombre ?? '') }}" required maxlength="200"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
            @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">País</label>
            <input type="text" name="pais" value="{{ old('pais', $p->pais ?? 'Panamá') }}" maxlength="100"
                   class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
            @error('pais') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2 flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="hidden" name="activa" value="0">
                <input type="checkbox" name="activa" value="1" {{ old('activa', $p->activa ?? true) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-[#0d2d5e] focus:ring-blue-500">
                Activa (se ofrece al crear una compañía nueva)
            </label>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
        <textarea name="descripcion" rows="3" maxlength="1000"
                  class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">{{ old('descripcion', $p->descripcion ?? '') }}</textarea>
        @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
