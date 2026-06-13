<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Evaluación — {{ $evaluacion->titulo }}
            </h2>
            <a href="{{ route('admin.edu.evaluaciones.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Evaluaciones</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Datos de la evaluación</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Institución</dt>
                        <dd class="font-semibold">{{ $evaluacion->institucion?->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Período</dt>
                        <dd>{{ $evaluacion->periodo?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Asignatura</dt>
                        <dd>{{ $evaluacion->asignatura?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Grupo</dt>
                        <dd>{{ $evaluacion->grupo?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Docente</dt>
                        <dd>{{ $evaluacion->docente?->contacto?->nombre ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Tipo</dt>
                        <dd>{{ $evaluacion->tipo_evaluacion ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Fecha</dt>
                        <dd>{{ $evaluacion->fecha_evaluacion?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Puntaje máximo</dt>
                        <dd>{{ $evaluacion->puntaje_maximo ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Porcentaje</dt>
                        <dd>{{ $evaluacion->porcentaje ? $evaluacion->porcentaje.'%' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Estado</dt>
                        @php $sc=['borrador'=>'bg-yellow-100 text-yellow-800','publicada'=>'bg-green-100 text-green-800','cerrada'=>'bg-gray-100 text-gray-600']; @endphp
                        <dd>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs {{ $sc[$evaluacion->estado] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst($evaluacion->estado) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Visible estudiante</dt>
                        <dd>{{ $evaluacion->visible_estudiante ? 'Sí' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Visible acudiente</dt>
                        <dd>{{ $evaluacion->visible_acudiente ? 'Sí' : 'No' }}</dd>
                    </div>
                </dl>
                @if($evaluacion->descripcion)
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <dt class="text-xs text-gray-500 uppercase mb-1">Descripción</dt>
                    <dd class="text-sm text-gray-700">{{ $evaluacion->descripcion }}</dd>
                </div>
                @endif

                @can('edu.gestionar')
                <div class="mt-6 flex gap-3">
                    <a href="{{ route('admin.edu.evaluaciones.edit', $evaluacion) }}"
                       class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Editar</a>
                    <form method="POST" action="{{ route('admin.edu.evaluaciones.destroy', $evaluacion) }}"
                          onsubmit="return confirm('¿Eliminar esta evaluación?')">
                        @csrf @method('DELETE')
                        <button class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm text-red-600 hover:bg-red-50">Eliminar</button>
                    </form>
                </div>
                @endcan
            </div>

            {{-- Calificaciones --}}
            @if($evaluacion->calificaciones->count())
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Calificaciones registradas</h3>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Estudiante</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Nota</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-gray-500">Observación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($evaluacion->calificaciones as $cal)
                        <tr>
                            <td class="px-4 py-2">{{ $cal->estudiante?->contacto?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">{{ $cal->nota ?? '—' }}</td>
                            <td class="px-4 py-2 text-center text-gray-500">{{ $cal->observacion ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
