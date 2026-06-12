<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div
            x-data="{
                sidebarOpen: false,
                sidebarCollapsed: false,
                menuQuery: '',
                openGroups: {
                    companias: true,
                    configuracion: false,
                    contabilidad: false,
                    compras: false,
                    ventas: false,
                    bancos: false,
                    inventario: false,
                    activos: false,
                    reportes: false,
                    ia: false,
                    seguridad: false,
                    ayuda: false
                }
            }"
            class="min-h-screen bg-slate-100 text-slate-900"
        >
            @include('layouts.navigation')

            <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="transition-all duration-200">
                @isset($header)
                    <header class="border-b border-slate-200 bg-white">
                        <div class="px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        @if (config('services.chatwoot.website_token') && config('services.chatwoot.base_url'))
            <script>
                window.chatwootSettings = {
                    locale: 'es',
                    position: 'right',
                    launcherTitle: 'Soporte'
                };

                (function(d, t) {
                    const BASE_URL = @json(rtrim(config('services.chatwoot.base_url'), '/'));
                    const g = d.createElement(t);
                    const s = d.getElementsByTagName(t)[0];

                    g.src = BASE_URL + '/packs/js/sdk.js';
                    g.defer = true;
                    g.async = true;
                    s.parentNode.insertBefore(g, s);

                    g.onload = function() {
                        window.chatwootSDK.run({
                            websiteToken: @json(config('services.chatwoot.website_token')),
                            baseUrl: BASE_URL,
                            locale: 'es'
                        });
                    };
                })(document, 'script');

                window.addEventListener('chatwoot:ready', function() {
                    if (! window.$chatwoot) {
                        return;
                    }

                    window.$chatwoot.setUser(@json((string) Auth::id()), {
                        name: @json(Auth::user()->name),
                        email: @json(Auth::user()->email)
                    });

                    window.$chatwoot.setCustomAttributes({
                        compania_id: @json($companiaActiva->id ?? null),
                        compania_nombre: @json($companiaActiva->nombre ?? null),
                        app: 'etax2'
                    });
                });
            </script>
        @endif
    </body>
</html>
