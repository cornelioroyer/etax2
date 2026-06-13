<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cierres contables</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @can('contabilidad.gestionar')
                @if ($periodosSinCierre->isNotEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Iniciar cierre de período</h3>
                        <form method="POST" action="{{ route('admin.contabilidad.cierres.store') }}" class="flex gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Período *</label>
                                <select name="periodo_id" required class="rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">Seleccionar…</option>
                                    @foreach ($periodosSinCierre as $p)
                                        <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Observación</label>
                                <input type="text" name="observacion" maxlength="500" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                            </div>
                            <button type="submit" class="rounded-md bg-[#0d2d5e] px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Iniciar cierre</button>
                        </form>
                    </div>
                @endif
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Período</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Fecha cierre</th>
                            <th class="px-4 py-3">Observación</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php $colores = ['PENDIENTE' => 'bg-yellow-100 text-yellow-700', 'EN_PROCESO' => 'bg-blue-100 text-blue-700', 'COMPLETADO' => 'bg-green-100 text-green-700', 'ERROR' => 'bg-red-100 text-red-700']; @endphp
                        @forelse ($cierres as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">{{ $c->periodo?->nombre }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $colores[$c->estado] ?? 'bg-gray-100' }}">
                                        {{ $c->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $c->fecha_cierre?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ Str::limit($c->observacion, 50) ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.contabilidad.cierres.show', $c) }}" class="text-xs text-blue-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Sin cierres registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $cierres->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
