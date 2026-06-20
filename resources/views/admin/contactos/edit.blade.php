<x-app-layout>
    @php($ayudaModulo = $contacto->tipos->pluck('codigo')->contains('PROVEEDOR') ? 'cxp' : ($contacto->tipos->pluck('codigo')->contains('CLIENTE') ? 'cxc' : ''))
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar contacto — {{ $contacto->nombre }}</h2>
            <div class="flex items-center gap-2">
            <a href="{{ route('admin.contactos.index') }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Proveedores
            </a>
            <button type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-help', { detail: { module: {{ $ayudaModulo ? "'".$ayudaModulo."'" : 'null' }} } }))"
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                title="Ayuda de esta pantalla">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.326-.67.442-.745.361-1.451.999-1.451 1.827v.75M12 18h.008v.008H12V18Zm9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Ayuda
            </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.contactos.update', $contacto) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf @method('PUT')
                @include('admin.contactos._form')
            </form>
        </div>
    </div>
</x-app-layout>
