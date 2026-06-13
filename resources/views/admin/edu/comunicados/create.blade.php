<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuevo comunicado</h2>
            <a href="{{ route('admin.edu.comunicados.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Comunicados</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.edu.comunicados.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="com_institucion_id" value="Institución *" />
                            <select id="com_institucion_id" name="institucion_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">— seleccione —</option>
                                @foreach ($instituciones as $i)
                                    <option value="{{ $i->id }}" @selected(old('institucion_id') == $i->id)>{{ $i->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="com_asunto" value="Asunto *" />
                            <x-text-input id="com_asunto" name="asunto" type="text" class="mt-1 block w-full"
                                :value="old('asunto')" required maxlength="300" />
                        </div>
                        <div>
                            <x-input-label for="com_canal" value="Canal" />
                            <select id="com_canal" name="canal"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— ninguno —</option>
                                <option value="email" @selected(old('canal')=='email')>Email</option>
                                <option value="whatsapp" @selected(old('canal')=='whatsapp')>WhatsApp</option>
                                <option value="sms" @selected(old('canal')=='sms')>SMS</option>
                                <option value="app" @selected(old('canal')=='app')>App</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="com_grado_id" value="Grado (opcional)" />
                            <select id="com_grado_id" name="grado_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— todos los grados —</option>
                                @foreach ($grados as $g)
                                    <option value="{{ $g->id }}" @selected(old('grado_id') == $g->id)>{{ $g->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="com_grupo_id" value="Grupo (opcional)" />
                            <select id="com_grupo_id" name="grupo_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">— todos los grupos —</option>
                                @foreach ($grupos as $g)
                                    <option value="{{ $g->id }}" @selected(old('grupo_id') == $g->id)>{{ $g->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="com_fecha_envio" value="Fecha de envío" />
                            <x-text-input id="com_fecha_envio" name="fecha_envio" type="datetime-local" class="mt-1 block w-full"
                                :value="old('fecha_envio')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="com_cuerpo" value="Cuerpo del mensaje *" />
                            <textarea id="com_cuerpo" name="cuerpo" rows="6" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('cuerpo') }}</textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <x-primary-button>Crear comunicado</x-primary-button>
                        <a href="{{ route('admin.edu.comunicados.index') }}"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
