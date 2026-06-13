<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $checklist->nombre }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ $checklist->codigo }} · {{ \App\Models\TallerChecklist::TIPOS[$checklist->tipo_checklist] ?? $checklist->tipo_checklist }}</p>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.taller.checklists.index', ['taller_id' => $checklist->taller_id]) }}" class="text-gray-500 hover:text-gray-900">← Checklists</a>
                @can('taller.gestionar')
                    <a href="{{ route('admin.taller.checklists.edit', $checklist) }}" class="text-gray-600 hover:text-gray-900">Editar / Gestionar ítems</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
                    <div><dt class="text-xs font-medium text-gray-500 uppercase">Taller</dt><dd class="mt-1 font-medium">{{ $checklist->taller->nombre }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500 uppercase">Tipo equipo</dt><dd class="mt-1">{{ $checklist->tipoEquipo?->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500 uppercase">Total ítems</dt><dd class="mt-1 font-bold text-indigo-700">{{ $checklist->detalles->count() }}</dd></div>
                    <div><dt class="text-xs font-medium text-gray-500 uppercase">Estado</dt>
                        <dd class="mt-1">
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $checklist->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $checklist->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Ítems del checklist ({{ $checklist->detalles->count() }})</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Orden</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipo respuesta</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Obligatorio</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500">Activo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($checklist->detalles as $d)
                            <tr>
                                <td class="px-4 py-2 text-right text-gray-500 font-mono text-xs">{{ $d->orden }}</td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $d->codigo }}</td>
                                <td class="px-4 py-2">{{ $d->descripcion }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600">{{ \App\Models\TallerChecklistDetalle::TIPOS_RESPUESTA[$d->tipo_respuesta] ?? $d->tipo_respuesta }}</td>
                                <td class="px-4 py-2 text-center text-xs">{{ $d->obligatorio ? 'Sí' : 'No' }}</td>
                                <td class="px-4 py-2 text-center text-xs">{{ $d->activo ? 'Sí' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Sin ítems. <a href="{{ route('admin.taller.checklists.edit', $checklist) }}" class="text-indigo-600 hover:underline">Agregar ítems</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
