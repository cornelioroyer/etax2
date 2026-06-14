<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicons eTax2 -->
        <link rel="icon" href="/favicon.ico?v=4" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=4">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=4">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=4">
        <link rel="manifest" href="/site.webmanifest">

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
                    @php
                        $ruta = request()->route()?->getName() ?? '';
                        $moduloAyuda = match(true) {
                            str_contains($ruta, 'ventas.')       => 'ventas',
                            str_contains($ruta, 'cxc.')          => 'cxc',
                            str_contains($ruta, 'cxp.')          => 'cxp',
                            str_contains($ruta, 'compras.')      => 'compras',
                            str_contains($ruta, 'bco.') ||
                            str_contains($ruta, 'bancos.')       => 'bancos',
                            str_contains($ruta, 'inventario.')   => 'inventario',
                            str_contains($ruta, 'activos.')      => 'activos',
                            str_contains($ruta, 'caja.')         => 'caja',
                            str_contains($ruta, 'fel.')          => 'fel',
                            str_contains($ruta, 'asientos.') ||
                            str_contains($ruta, 'cuentas.') ||
                            str_contains($ruta, 'diarios.') ||
                            str_contains($ruta, 'periodos.')     => 'contabilidad',
                            default                              => null,
                        };
                    @endphp
                    <header class="border-b border-slate-200 bg-white">
                        <div class="flex items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
                            <div class="min-w-0 flex-1">{{ $header }}</div>
                            @if ($moduloAyuda)
                                <x-help-button :module="$moduloAyuda" label="Ayuda" />
                            @endif
                        </div>
                    </header>
                @endisset

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- ── Centro de Ayuda (botón flotante + drawer) ────────────────────── --}}
        <div
            x-data="{
                open: false,
                search: '',
                activeCategory: null,
                activeArticle: null,
                categories: [
                    { key: 'contabilidad', label: 'Contabilidad',      color: 'bg-blue-50 text-blue-700 ring-blue-200',    icon: 'M9 7.5h6M9 12h6M9 16.5h3M6.75 3h10.5A2.25 2.25 0 0 1 19.5 5.25v13.5A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75V5.25A2.25 2.25 0 0 1 6.75 3Z' },
                    { key: 'cxc',          label: 'Cuentas por Cobrar', color: 'bg-green-50 text-green-700 ring-green-200',  icon: 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' },
                    { key: 'cxp',          label: 'Cuentas por Pagar',  color: 'bg-orange-50 text-orange-700 ring-orange-200', icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z' },
                    { key: 'ventas',       label: 'Ventas',             color: 'bg-indigo-50 text-indigo-700 ring-indigo-200', icon: 'M4.5 6.75h15M6 6.75l1.5 12h9l1.5-12M9 10.5h6M9.75 3h4.5' },
                    { key: 'compras',      label: 'Compras',            color: 'bg-amber-50 text-amber-700 ring-amber-200',   icon: 'M3.75 6.75h16.5l-1.5 12h-13.5l-1.5-12ZM8.25 6.75a3.75 3.75 0 0 1 7.5 0' },
                    { key: 'bancos',       label: 'Bancos',             color: 'bg-cyan-50 text-cyan-700 ring-cyan-200',      icon: 'M3 10.5h18M4.5 10.5V18M8.25 10.5V18M12 10.5V18m3.75-7.5V18M19.5 10.5V18M3.75 21h16.5M12 3l8.25 4.5H3.75L12 3Z' },
                    { key: 'inventario',   label: 'Inventario',         color: 'bg-teal-50 text-teal-700 ring-teal-200',      icon: 'M21 8.25 12 3 3 8.25m18 0-9 5.25m9-5.25v7.5L12 21m0-7.5L3 8.25m9 5.25V21M3 8.25v7.5L12 21' },
                    { key: 'activos',      label: 'Activos Fijos',      color: 'bg-rose-50 text-rose-700 ring-rose-200',      icon: 'M4.5 20.25h15M6 20.25V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75v13.5M9 9h6M9 12.75h6M9 16.5h3' },
                    { key: 'caja',         label: 'Caja chica',         color: 'bg-purple-50 text-purple-700 ring-purple-200', icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z' },
                    { key: 'fel',          label: 'Facturación FEL',    color: 'bg-slate-50 text-slate-700 ring-slate-200',   icon: 'M9 12h6m-6 3h4M5.25 4.5h13.5a.75.75 0 0 1 .75.75v15l-2.625-1.5L14.25 20.25l-2.25-1.5-2.25 1.5-2.625-1.5L4.5 20.25v-15a.75.75 0 0 1 .75-.75Z' },
                ],
                articles: [
                    { id:1,  cat:'contabilidad', title:'¿Qué es un período contable y cómo abrirlo?', body:'Un período contable es el rango de fechas (normalmente un mes) en que se registran transacciones. Ve a Contabilidad → Períodos contables y haz clic en Nuevo período. Ingresa inicio y fin, y guarda. Solo puedes registrar documentos dentro de un período abierto.' },
                    { id:2,  cat:'contabilidad', title:'¿Cómo crear un asiento contable manual?', body:'Ve a Contabilidad → Asientos → Nuevo asiento. Selecciona el diario, la fecha y agrega líneas de débito y crédito. El asiento debe cuadrar (débitos = créditos) antes de guardarlo. Los módulos de Ventas, Compras y Bancos generan asientos automáticos al confirmar documentos.' },
                    { id:3,  cat:'contabilidad', title:'¿Cómo funciona el plan de cuentas?', body:'El plan de cuentas lista las cuentas contables organizadas por tipo (Activo, Pasivo, Capital, Ingreso, Gasto). Ve a Contabilidad → Plan de cuentas. Las cuentas padre son agrupadoras; las cuentas detalle son donde se registran movimientos y aparecen al crear asientos.' },
                    { id:4,  cat:'contabilidad', title:'¿Qué son las cuentas por defecto?', body:'Son las cuentas que el sistema usa automáticamente para generar asientos. Ve a Contabilidad → Cuentas por defecto. Ejemplos: CXC = clientes por cobrar, CXP = proveedores por pagar, VENTAS = ingresos, ITBMS_POR_PAGAR = impuesto. Si una no está configurada, el sistema mostrará error al confirmar documentos.' },
                    { id:5,  cat:'contabilidad', title:'¿Cómo hacer el cierre contable?', body:'Ve a Contabilidad → Cierre contable y selecciona el período. El cierre transfiere saldos de ingresos y gastos a la cuenta de utilidad/pérdida. Requisitos: período abierto, todos los documentos confirmados y Balance General cuadrado. Una vez cerrado, el período no admite nuevos registros.' },
                    { id:6,  cat:'cxc', title:'¿Cómo registrar un cliente?', body:'Ve a Cuentas por Cobrar → Clientes → Nuevo cliente. Completa nombre, RUC/cédula, correo y teléfono. El mismo contacto puede ser cliente y proveedor al mismo tiempo.' },
                    { id:7,  cat:'cxc', title:'¿Cómo crear una factura de cobro?', body:'Ve a CxC → Facturas → Nueva factura. Selecciona el cliente, fecha, diario y agrega líneas con descripción, cantidad, precio e ITBMS. Al confirmar, el sistema genera el asiento automático (débito CXC, crédito Ventas e ITBMS). La factura queda Pendiente hasta recibir cobro.' },
                    { id:8,  cat:'cxc', title:'¿Cómo registrar un cobro?', body:'Ve a CxC → Cobros → Nuevo cobro. Selecciona cliente, fecha, monto y cuenta bancaria o caja donde se recibe el dinero. En Documentos a aplicar, elige las facturas que cancela. Al confirmar, el asiento débita Banco/Caja y acredita CXC, actualizando el saldo de la factura.' },
                    { id:9,  cat:'cxc', title:'¿Qué es una nota de crédito o débito?', body:'Una nota de crédito reduce el saldo de una factura (por devoluciones o errores). Una nota de débito lo aumenta. Ve a CxC → Notas crédito/débito para crearlas. Al confirmar, se genera el asiento inverso y se aplica contra la factura original.' },
                    { id:10, cat:'cxc', title:'¿Cómo ver la antigüedad de saldos?', body:'Ve a CxC → Antigüedad de saldos. Este reporte muestra cuánto debe cada cliente agrupado por rango de días vencidos (0-30, 31-60, 61-90, +90 días). Útil para gestión de cobros y provisiones de cuentas incobrables.' },
                    { id:11, cat:'cxp', title:'¿Cómo registrar un proveedor?', body:'Ve a CxP → Proveedores → Nuevo proveedor. Ingresa nombre, RUC, correo y condiciones de pago. El mismo contacto puede ser cliente y proveedor al mismo tiempo.' },
                    { id:12, cat:'cxp', title:'¿Cómo registrar una factura de proveedor?', body:'Ve a CxP → Facturas por pagar → Nueva factura. Selecciona el proveedor, ingresa el número de factura del proveedor, fecha y líneas de gasto o producto. Al confirmar, el sistema genera el asiento (débito Gasto e ITBMS Crédito, crédito CXP).' },
                    { id:13, cat:'cxp', title:'¿Cómo registrar un pago a proveedor?', body:'Ve a CxP → Pagos → Nuevo pago. Selecciona proveedor, fecha, monto y cuenta bancaria de donde sale el dinero. En Documentos a aplicar, elige las facturas que cancela. Al confirmar, el asiento débita CXP y acredita Banco, actualizando el saldo de la factura.' },
                    { id:14, cat:'ventas', title:'¿Cómo crear una cotización?', body:'Ve a Ventas → Cotizaciones → Nueva cotización. Selecciona el cliente e ingresa productos o servicios con precios. La cotización no genera asiento — es una propuesta. Cuando el cliente aprueba, haz clic en Convertir a factura para generar la factura de venta.' },
                    { id:15, cat:'ventas', title:'¿Cómo emitir una factura de venta?', body:'Ve a Ventas → Facturas de venta → Nueva factura. Puedes crearla desde cero o convertirla desde una cotización. Agrega los ítems, verifica el ITBMS y confirma. El sistema genera el asiento contable automáticamente.' },
                    { id:16, cat:'ventas', title:'¿Cómo emitir un recibo de cobro?', body:'Ve a Ventas → Cobros / Recibos → Nuevo recibo. Selecciona el cliente y el monto recibido. Aplica el cobro a una o varias facturas pendientes. El recibo genera el asiento (débito Caja/Banco, crédito CXC) y descarga el saldo de las facturas.' },
                    { id:17, cat:'ventas', title:'¿Cómo crear una nota de crédito de venta?', body:'Ve a Ventas → Notas de crédito. Puedes crearla desde una factura existente (botón Nota de crédito en el detalle) o desde cero. Indica el motivo y los ítems o monto a acreditar. Al confirmar, el sistema invierte el asiento y reduce el saldo del cliente.' },
                    { id:18, cat:'compras', title:'¿Cómo crear una orden de compra?', body:'Ve a Compras → Órdenes de compra → Nueva orden. Selecciona el proveedor y agrega los ítems con cantidades y precios estimados. La orden no genera asiento — es un documento de autorización interna. Al recibir la mercancía, se convierte en factura de proveedor.' },
                    { id:19, cat:'compras', title:'¿Qué son los gastos directos?', body:'Son egresos que no provienen de una factura formal: servicios públicos, taxis, papelería pagada en efectivo. Ve a Compras → Gastos directos para registrarlos. Al confirmar, el sistema genera el asiento (débito Gasto, crédito Caja o Banco según el método de pago).' },
                    { id:20, cat:'bancos', title:'¿Cómo registrar una cuenta bancaria?', body:'Ve a Bancos → Cuentas bancarias → Nueva cuenta. Selecciona el banco, ingresa el número de cuenta, tipo (corriente o ahorro) y la cuenta contable asociada en el plan de cuentas. El saldo inicial se puede ingresar al crear la cuenta.' },
                    { id:21, cat:'bancos', title:'¿Cómo registrar un movimiento bancario manual?', body:'Ve a Bancos → Movimientos → Nuevo movimiento. Selecciona la cuenta bancaria, fecha, tipo (entrada o salida), monto y cuenta contable de contrapartida. Úsalo para comisiones bancarias, intereses o cargos que no vengan de otro módulo.' },
                    { id:22, cat:'bancos', title:'¿Cómo hacer una transferencia entre cuentas?', body:'Ve a Bancos → Transferencias → Nueva transferencia. Selecciona cuenta origen, cuenta destino, fecha y monto. El sistema crea automáticamente el egreso en el origen y el ingreso en el destino, con sus respectivos asientos contables.' },
                    { id:23, cat:'bancos', title:'¿Cómo conciliar una cuenta bancaria?', body:'Ve a Bancos → Conciliaciones, selecciona la cuenta y fecha de corte. El sistema muestra los movimientos del período. Marca los que aparecen en tu estado bancario como conciliados. Los que no coinciden quedan pendientes para investigar (depósito en tránsito, error de fecha, etc.).' },
                    { id:24, cat:'bancos', title:'¿Cómo registrar un cheque emitido?', body:'Ve a Bancos → Cheques → Nuevo cheque. Ingresa número de cheque, cuenta bancaria, beneficiario, fecha y monto. El cheque se puede asociar a un pago de proveedor existente. Al confirmar, se registra el egreso y actualiza el saldo de la cuenta.' },
                    { id:25, cat:'bancos', title:'¿Cómo registrar un depósito bancario?', body:'Ve a Bancos → Depósitos → Nuevo depósito. Selecciona la cuenta bancaria destino, fecha, monto y origen del dinero (puede vincularse a cobros de clientes). Al confirmar, se actualiza el saldo de la cuenta y se genera el asiento.' },
                    { id:26, cat:'inventario', title:'¿Cómo crear un almacén?', body:'Ve a Inventario → Almacenes → Nuevo almacén. Ingresa el nombre y la ubicación (bodega principal, punto de venta, etc.). Los almacenes son los contenedores donde se registra el stock de los productos.' },
                    { id:27, cat:'inventario', title:'¿Cómo registrar un movimiento de inventario?', body:'Ve a Inventario → Movimientos → Nuevo movimiento. Selecciona el tipo (entrada, salida o ajuste), el almacén, el producto y la cantidad. Entradas aumentan el stock; salidas lo reducen; ajustes corrigen diferencias detectadas en inventario físico.' },
                    { id:28, cat:'inventario', title:'¿Cómo hacer una transferencia entre almacenes?', body:'Ve a Inventario → Transferencias → Nueva transferencia. Selecciona almacén origen, destino, productos y cantidades. Al confirmar, el stock se reduce en el origen y se suma en el destino sin afectar el costo total.' },
                    { id:29, cat:'inventario', title:'¿Qué es el kardex y cómo consultarlo?', body:'El kardex es el historial cronológico de entradas y salidas de un producto, con saldo acumulado. Ve a Inventario → Kardex, selecciona el producto y opcionalmente el almacén y rango de fechas. Es la herramienta principal para auditar diferencias de inventario.' },
                    { id:30, cat:'activos', title:'¿Cómo registrar un activo fijo?', body:'Ve a Activos Fijos → Registro de activos → Nuevo activo. Ingresa nombre, categoría (determina vida útil y método de depreciación), fecha de adquisición, costo y cuenta contable. La categoría define automáticamente el porcentaje de depreciación.' },
                    { id:31, cat:'activos', title:'¿Cómo se calcula y registra la depreciación?', body:'La depreciación se calcula automáticamente según la categoría y fecha de adquisición. El sistema genera el asiento mensual (débito Gasto por Depreciación, crédito Depreciación Acumulada). Para ver el detalle, abre el activo y revisa la pestaña Depreciaciones.' },
                    { id:32, cat:'activos', title:'¿Cómo dar de baja un activo fijo?', body:'Abre el activo desde Activos Fijos → Registro y haz clic en Registrar baja. Indica la fecha, motivo (venta, obsolescencia, siniestro) y valor de rescate si aplica. El sistema genera el asiento de baja y reconoce ganancia o pérdida en la disposición.' },
                    { id:33, cat:'caja', title:'¿Cómo abrir un arqueo de caja chica?', body:'Ve a Caja chica → Arqueos → Nuevo arqueo. Ingresa el monto inicial del fondo y la fecha de apertura. A partir de ese momento puedes registrar gastos menores pagados con ese fondo. Al cerrar el arqueo, el sistema genera el asiento de reposición.' },
                    { id:34, cat:'caja', title:'¿Cómo registrar un gasto de caja chica?', body:'Dentro de un arqueo abierto, haz clic en Agregar movimiento. Ingresa la descripción del gasto, monto, cuenta contable del gasto y adjunta el comprobante si tienes el recibo escaneado. El saldo disponible del arqueo se reduce automáticamente.' },
                    { id:35, cat:'fel', title:'¿Qué es la Facturación Electrónica FEL?', body:'La Facturación Electrónica (FEL) es el sistema de la DGI de Panamá para emitir facturas con validez fiscal digital a través de un Proveedor Autorizado (PAC). eTax2 está integrado con The Factory HKA. Las facturas FEL tienen código QR y CUFE, y quedan registradas en la DGI en tiempo real.' },
                    { id:36, cat:'fel', title:'¿Cómo configurar el módulo FEL?', body:'Ve a Facturación FEL → Configuración. Necesitas: Token WS y contraseña WS (provisto por HKA), código de sucursal y punto de facturación, RUC y DV de tu empresa. Guarda y haz una emisión de prueba para verificar la conectividad antes de emitir documentos reales.' },
                    { id:37, cat:'fel', title:'¿Cómo emitir una factura electrónica?', body:'Ve a Facturación FEL → Nueva factura. Selecciona el cliente, agrega los ítems y confirma. eTax2 envía la factura a HKA en XML, HKA la valida y reporta a la DGI, y devuelve el CUFE y código QR. Si la DGI rechaza la factura, el sistema muestra el código de error para que puedas corregir.' },
                ],
                get filteredArticles() {
                    const q = this.search.trim().toLowerCase();
                    const cat = this.activeCategory;
                    return this.articles.filter(a =>
                        (cat ? a.cat === cat : true) &&
                        (q ? (a.title.toLowerCase().includes(q) || a.body.toLowerCase().includes(q)) : true)
                    );
                },
                countByCategory(key) {
                    const q = this.search.trim().toLowerCase();
                    return this.articles.filter(a =>
                        a.cat === key &&
                        (q ? (a.title.toLowerCase().includes(q) || a.body.toLowerCase().includes(q)) : true)
                    ).length;
                }
            }"
            @keydown.escape.window="open = false"
            @open-help.window="open = true; activeCategory = $event.detail.module ?? null; activeArticle = null; search = ''"
        >
            {{-- Botón flotante --}}
            <button
                type="button"
                @click="open = true"
                x-show="!open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-75"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-75"
                class="fixed bottom-6 right-6 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-[#0d2d5e] text-white shadow-lg hover:bg-[#0a2347] focus:outline-none focus:ring-2 focus:ring-[#0d2d5e] focus:ring-offset-2 print:hidden"
                title="Centro de ayuda"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.326-.67.442-.745.361-1.451.999-1.451 1.827v.75M12 18h.008v.008H12V18Zm9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </button>

            {{-- Overlay --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="open = false"
                class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm print:hidden"
                x-cloak
            ></div>

            {{-- Modal centrado --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 print:hidden"
                x-cloak
            >
            <div class="flex w-full max-w-2xl flex-col bg-white rounded-2xl shadow-2xl overflow-hidden" style="max-height: 85vh">
                {{-- Cabecera del drawer --}}
                <div class="flex shrink-0 items-center justify-between bg-[#0d2d5e] px-5 py-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-widest text-blue-300">eTax2</p>
                        <h2 class="text-base font-semibold text-white">Centro de Ayuda</h2>
                    </div>
                    <button type="button" @click="open = false" class="rounded-md p-1.5 text-blue-300 hover:bg-white/10 hover:text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Buscador --}}
                <div class="shrink-0 border-b border-slate-200 px-4 py-3">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                        </svg>
                        <input
                            x-model.debounce.150ms="search"
                            @input="activeCategory = null; activeArticle = null"
                            type="search"
                            placeholder="Buscar en la ayuda..."
                            class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-400"
                        >
                    </div>
                    <p x-show="search !== ''" class="mt-1.5 text-xs text-slate-500">
                        <span x-text="filteredArticles.length"></span> resultado(s)
                    </p>
                </div>

                {{-- Categorías (solo cuando no hay búsqueda activa) --}}
                <div x-show="search === ''" class="shrink-0 border-b border-slate-200 px-4 py-3">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Módulos</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="cat in categories" :key="cat.key">
                            <button
                                type="button"
                                @click="activeCategory = (activeCategory === cat.key ? null : cat.key); activeArticle = null"
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 transition-all',
                                    activeCategory === cat.key
                                        ? 'bg-[#0d2d5e] text-white ring-[#0d2d5e]'
                                        : cat.color
                                ]"
                            >
                                <span x-text="cat.label"></span>
                                <span
                                    :class="activeCategory === cat.key ? 'bg-white/20 text-white' : 'bg-white/60 text-current'"
                                    class="rounded-full px-1 text-[10px] font-bold"
                                    x-text="countByCategory(cat.key)"
                                ></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Lista de artículos (scrollable) --}}
                <div class="flex-1 overflow-y-auto">

                    {{-- Sin resultados --}}
                    <div x-show="filteredArticles.length === 0" class="px-5 py-12 text-center">
                        <svg class="mx-auto mb-3 h-8 w-8 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.326-.67.442-.745.361-1.451.999-1.451 1.827v.75M12 18h.008v.008H12V18Zm9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="text-sm font-medium text-slate-500">No se encontraron artículos</p>
                        <p class="mt-1 text-xs text-slate-400">Intenta con otra búsqueda</p>
                    </div>

                    {{-- Artículos agrupados --}}
                    <template x-for="cat in categories" :key="'g-' + cat.key">
                        <div x-show="filteredArticles.some(a => a.cat === cat.key)">

                            {{-- Encabezado de grupo --}}
                            <div class="sticky top-0 flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2">
                                <span :class="['inline-flex h-5 w-5 items-center justify-center rounded ring-1', cat.color]">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="cat.icon" />
                                    </svg>
                                </span>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500" x-text="cat.label"></span>
                            </div>

                            {{-- Artículos del grupo --}}
                            <template x-for="article in filteredArticles.filter(a => a.cat === cat.key)" :key="article.id">
                                <div class="border-b border-slate-100">
                                    <button
                                        type="button"
                                        @click="activeArticle = (activeArticle === article.id ? null : article.id)"
                                        class="flex w-full items-start gap-3 px-4 py-3.5 text-left hover:bg-slate-50"
                                    >
                                        <svg
                                            :class="activeArticle === article.id ? 'rotate-90 text-[#0d2d5e]' : 'text-slate-300'"
                                            class="mt-0.5 h-4 w-4 shrink-0 transition-transform duration-150"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                                        </svg>
                                        <span
                                            class="text-sm leading-snug"
                                            :class="activeArticle === article.id ? 'font-semibold text-[#0d2d5e]' : 'font-medium text-slate-700'"
                                            x-text="article.title"
                                        ></span>
                                    </button>
                                    <div
                                        x-show="activeArticle === article.id"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="border-t border-slate-100 bg-blue-50/50 px-4 pb-4 pt-3 text-sm leading-relaxed text-slate-600"
                                        x-text="article.body"
                                    ></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Pie del modal --}}
                <div class="shrink-0 border-t border-slate-200 bg-white px-4 py-3 text-center">
                    <p class="text-xs text-slate-500">¿No encontraste tu respuesta? Escríbenos a <a href="mailto:CornelioRoyer@gmail.com" class="font-medium text-[#0d2d5e] hover:underline">CornelioRoyer@gmail.com</a></p>
                </div>
            </div>{{-- /modal-box --}}
            </div>{{-- /modal-centering --}}
        </div>
        {{-- ── /Centro de Ayuda ──────────────────────────────────────────────── --}}

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

        @stack('scripts')
    </body>
</html>
