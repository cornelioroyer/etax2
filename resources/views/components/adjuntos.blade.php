@props([
    'tablaOrigen',
    'registroId',
    'adjuntos' => collect(),
    'puedeGestionar' => false,
    'titulo' => 'Adjuntos',
])
{{-- Bloque reutilizable de adjuntos centrales (core_adjuntos).
     - Lista los adjuntos del documento con enlace de descarga.
     - Si $puedeGestionar: permite subir (multiple) y eliminar.
     Subida/borrado van a las rutas centrales admin.adjuntos.* --}}
@php($items = collect($adjuntos))
<div class="rounded-lg border border-gray-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">{{ $titulo }}</h3>
        <span class="text-xs text-gray-400">{{ $items->count() }}</span>
    </div>

    @if ($items->isEmpty())
        <p class="text-sm text-gray-400">Sin adjuntos.</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($items as $adj)
                <li class="flex items-center justify-between gap-3 py-2">
                    <a href="{{ route('admin.adjuntos.descargar', $adj) }}" target="_blank" rel="noopener"
                       class="flex min-w-0 items-center gap-2 text-sm text-blue-700 hover:underline">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            @if ($adj->esImagen())
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16l5-5 4 4 3-3 6 6M4 4h16v16H4z" />
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z" />
                            @endif
                        </svg>
                        <span class="truncate">{{ $adj->nombre_archivo }}</span>
                    </a>
                    <div class="flex shrink-0 items-center gap-3">
                        @if ($adj->tamanoLegible())
                            <span class="text-xs text-gray-400">{{ $adj->tamanoLegible() }}</span>
                        @endif
                        @if ($puedeGestionar)
                            <form method="POST" action="{{ route('admin.adjuntos.eliminar', $adj) }}"
                                  onsubmit="return confirm('¿Eliminar este adjunto?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Eliminar</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($puedeGestionar)
        <form method="POST" action="{{ route('admin.adjuntos.subir') }}" enctype="multipart/form-data" class="mt-4 border-t border-gray-100 pt-3">
            @csrf
            <input type="hidden" name="tabla_origen" value="{{ $tablaOrigen }}">
            <input type="hidden" name="registro_id" value="{{ $registroId }}">
            <div class="flex flex-wrap items-center gap-2">
                <input type="file" name="archivos[]" multiple
                       accept=".jpg,.jpeg,.png,.webp,.pdf"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200 sm:w-auto">
                <button type="submit"
                        class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                        style="background-color:#0d2d5e;">
                    Subir
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-400">JPG, PNG, WEBP o PDF · máx 10 MB c/u.</p>
            @error('archivos.*')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('archivos')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </form>
    @endif
</div>
