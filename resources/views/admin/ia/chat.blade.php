<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Asistente IA</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <div
                x-data="asistenteIa()"
                style="height:70vh"
                class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                {{-- Cabecera --}}
                <div class="flex items-center gap-3 border-b border-slate-200 bg-[#0d2d5e] px-5 py-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-white">Asistente etax2</p>
                        <p class="truncate text-xs text-blue-200">Pregunta en lenguaje natural sobre tu contabilidad</p>
                    </div>
                </div>

                {{-- Mensajes --}}
                <div x-ref="scroll" class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                    <template x-for="(m, i) in mensajes" :key="i">
                        <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                            <div
                                :class="m.role === 'user'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-slate-100 text-slate-800'"
                                style="max-width:85%"
                                class="whitespace-pre-wrap rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
                                x-text="m.content"
                            ></div>
                        </div>
                    </template>

                    <div x-show="cargando" class="flex justify-start">
                        <div class="rounded-2xl bg-slate-100 px-4 py-2.5 text-sm text-slate-500">
                            <span class="inline-flex gap-1">
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style="animation-delay:0ms"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style="animation-delay:120ms"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" style="animation-delay:240ms"></span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Sugerencias rápidas --}}
                <div x-show="mensajes.length <= 1" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-3">
                    <template x-for="s in sugerencias" :key="s">
                        <button type="button" @click="entrada = s; enviar()" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600 hover:bg-slate-100" x-text="s"></button>
                    </template>
                </div>

                {{-- Entrada --}}
                <form @submit.prevent="enviar()" class="flex items-end gap-2 border-t border-slate-200 px-4 py-3">
                    <textarea
                        x-model="entrada"
                        @keydown.enter.prevent="enviar()"
                        rows="1"
                        placeholder="Escribe tu pregunta..."
                        class="max-h-32 flex-1 resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="cargando || entrada.trim() === ''"
                        class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
                    >
                        Enviar
                    </button>
                </form>
            </div>

            <p class="mt-3 px-1 text-xs text-slate-400">
                El asistente consulta solo datos de la compañía activa y según tus permisos. Es una herramienta de apoyo: verifica las cifras en los reportes oficiales antes de tomar decisiones.
            </p>
        </div>
    </div>

    @push('scripts')
    <script>
        function asistenteIa() {
            return {
                entrada: '',
                cargando: false,
                mensajes: [
                    { role: 'assistant', content: '¡Hola! Soy el asistente de etax2. Consulto cuentas por cobrar y por pagar, ventas, compras, bancos, caja menuda, inventario, activos fijos, estados financieros, balance de comprobación, liquidación de ITBMS, facturación electrónica y los módulos de taller, propiedad horizontal y educación de tu compañía activa. ¿En qué te ayudo?' },
                ],
                sugerencias: [
                    '¿Cuál es mi utilidad este año?',
                    '¿Cuál es mi situación financiera?',
                    '¿Cuál es mi cartera total por cobrar?',
                    '¿Cuánto debo a proveedores?',
                    '¿Cuánto vendí este mes?',
                    '¿Cuánto dinero hay en bancos?',
                    '¿Cuánto valen mis activos fijos?',
                ],
                async enviar() {
                    const texto = this.entrada.trim();
                    if (texto === '' || this.cargando) return;

                    this.mensajes.push({ role: 'user', content: texto });
                    this.entrada = '';
                    this.cargando = true;
                    this.scrollAbajo();

                    // Historial = todo menos el saludo inicial del asistente.
                    const historial = this.mensajes
                        .slice(1, -1)
                        .map(m => ({ role: m.role, content: m.content }));

                    try {
                        const resp = await fetch(@json(route('admin.asistente.enviar')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ mensaje: texto, historial }),
                        });
                        const json = await resp.json();
                        this.mensajes.push({ role: 'assistant', content: json.respuesta ?? 'Sin respuesta.' });
                    } catch (e) {
                        this.mensajes.push({ role: 'assistant', content: 'No pude contactar al asistente. Revisa tu conexión e intenta de nuevo.' });
                    } finally {
                        this.cargando = false;
                        this.scrollAbajo();
                    }
                },
                scrollAbajo() {
                    this.$nextTick(() => {
                        this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
                    });
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
