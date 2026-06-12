<div class="space-y-6">
    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Descripción</label>
        <input id="description" name="description" value="{{ old('description', $zona->description ?? '') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center justify-end gap-3 border-t pt-6">
        <a href="{{ route('admin.zonas.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Guardar</button>
    </div>
</div>
