@php
    $can = fn (string $permission): bool => Auth::user()->is_admin || Auth::user()->can($permission);

    $groups = [
        [
            'key' => 'inicio',
            'label' => 'Dashboard',
            'icon' => 'M3 12l9-9 9 9M5.25 10.5v9h13.5v-9',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'show' => true,
            'children' => [],
        ],
        [
            'key' => 'contabilidad',
            'label' => 'Contabilidad',
            'icon' => 'M9 7.5h6M9 12h6M9 16.5h3M6.75 3h10.5A2.25 2.25 0 0 1 19.5 5.25v13.5A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75V5.25A2.25 2.25 0 0 1 6.75 3Z',
            'active' => request()->routeIs('admin.cuentas.*') || request()->routeIs('admin.asientos.*') || request()->routeIs('admin.periodos.*') || request()->routeIs('admin.cuentas-default.*') || request()->routeIs('admin.diarios.*'),
            'show' => $can('contabilidad.ver'),
            'children' => [
                ['label' => 'Estados Financieros', 'href' => route('dashboard'), 'active' => false, 'show' => $can('contabilidad.ver')],
                ['label' => 'Plan de cuentas', 'href' => route('admin.cuentas.index'), 'active' => request()->routeIs('admin.cuentas.*'), 'show' => $can('contabilidad.ver')],
                ['label' => 'Cuentas por defecto', 'href' => route('admin.cuentas-default.index'), 'active' => request()->routeIs('admin.cuentas-default.*'), 'show' => $can('contabilidad.ver')],
                ['label' => 'Diarios', 'href' => route('admin.diarios.index'), 'active' => request()->routeIs('admin.diarios.*'), 'show' => $can('contabilidad.ver')],
                ['label' => 'Asientos', 'href' => route('admin.asientos.index'), 'active' => request()->routeIs('admin.asientos.*'), 'show' => $can('contabilidad.ver')],
                ['label' => 'Períodos contables', 'href' => route('admin.periodos.index'), 'active' => request()->routeIs('admin.periodos.*'), 'show' => $can('contabilidad.ver')],
                ['label' => 'Cierre contable', 'href' => route('admin.contabilidad.cierres.index'), 'active' => request()->routeIs('admin.contabilidad.cierres.*'), 'show' => $can('contabilidad.ver')],
            ],
        ],
        [
            'key' => 'reportes',
            'label' => 'Reportes',
            'icon' => 'M4.5 19.5V4.5m0 15h15M8.25 15l3-3 2.25 2.25L18 9',
            'show' => $can('reportes.ver'),
            'active' => request()->routeIs('admin.reportes.*'),
            'children' => [
                ['label' => 'Balance de Situación', 'href' => route('admin.reportes.balance'), 'active' => request()->routeIs('admin.reportes.balance'), 'show' => $can('reportes.ver')],
                ['label' => 'Estado de Resultado', 'href' => route('admin.reportes.resultado'), 'active' => request()->routeIs('admin.reportes.resultado'), 'show' => $can('reportes.ver')],
                ['label' => 'Comparativo Mensual', 'href' => route('admin.reportes.comparativo'), 'active' => request()->routeIs('admin.reportes.comparativo'), 'show' => $can('reportes.ver')],
                ['label' => 'Flujo de Efectivo', 'href' => route('admin.reportes.flujo-caja'), 'active' => request()->routeIs('admin.reportes.flujo-caja'), 'show' => $can('reportes.ver')],
                ['label' => 'Liquidación ITBMS', 'href' => route('admin.reportes.liquidacion-itbms'), 'active' => request()->routeIs('admin.reportes.liquidacion-itbms'), 'show' => $can('reportes.ver')],
            ],
        ],
        [
            'key' => 'cxc',
            'label' => 'Cuentas por Cobrar',
            'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
            'show' => $can('cxc.ver') || $can('contactos.ver'),
            'active' => (request()->routeIs('admin.contactos.*') && request('tipo') === 'CLIENTE') || request()->routeIs('admin.cxc.*'),
            'children' => [
                ['label' => 'Clientes', 'href' => route('admin.contactos.index', ['tipo' => 'CLIENTE']), 'active' => request()->routeIs('admin.contactos.*') && request('tipo') === 'CLIENTE', 'show' => $can('contactos.ver')],
                ['label' => 'Facturas', 'href' => route('admin.cxc.facturas.index'), 'active' => request()->routeIs('admin.cxc.facturas.*'), 'show' => $can('cxc.ver')],
                ['label' => 'Cobros', 'href' => route('admin.cxc.cobros.index'), 'active' => request()->routeIs('admin.cxc.cobros.*'), 'show' => $can('cxc.ver')],
                ['label' => 'Notas crédito/débito', 'href' => route('admin.cxc.notas.index'), 'active' => request()->routeIs('admin.cxc.notas.*'), 'show' => $can('cxc.ver')],
                ['label' => 'Antigüedad de saldos', 'href' => route('admin.cxc.antiguedad'), 'active' => request()->routeIs('admin.cxc.antiguedad'), 'show' => $can('cxc.ver')],
                ['label' => 'Estado de cuenta', 'href' => route('admin.cxc.estado-cuenta'), 'active' => request()->routeIs('admin.cxc.estado-cuenta'), 'show' => $can('cxc.ver')],
            ],
        ],
        [
            'key' => 'cxp',
            'label' => 'Cuentas por Pagar',
            'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
            'show' => $can('cxp.ver') || $can('contactos.ver'),
            'active' => (request()->routeIs('admin.contactos.*') && request('tipo') === 'PROVEEDOR') || request()->routeIs('admin.cxp.*'),
            'children' => [
                ['label' => 'Proveedores', 'href' => route('admin.contactos.index', ['tipo' => 'PROVEEDOR']), 'active' => request()->routeIs('admin.contactos.*') && request('tipo') === 'PROVEEDOR', 'show' => $can('contactos.ver')],
                ['label' => 'Facturas por pagar', 'href' => route('admin.cxp.facturas.index'), 'active' => request()->routeIs('admin.cxp.facturas.*'), 'show' => $can('cxp.ver')],
                ['label' => 'Pagos', 'href' => route('admin.cxp.pagos.index'), 'active' => request()->routeIs('admin.cxp.pagos.*'), 'show' => $can('cxp.ver')],
                ['label' => 'Notas crédito/débito', 'href' => route('admin.cxp.notas.index'), 'active' => request()->routeIs('admin.cxp.notas.*'), 'show' => $can('cxp.ver')],
                ['label' => 'Antigüedad de saldos', 'href' => route('admin.cxp.antiguedad'), 'active' => request()->routeIs('admin.cxp.antiguedad'), 'show' => $can('cxp.ver')],
                ['label' => 'Estado de cuenta', 'href' => route('admin.cxp.estado-cuenta'), 'active' => request()->routeIs('admin.cxp.estado-cuenta'), 'show' => $can('cxp.ver')],
            ],
        ],
        [
            'key' => 'compras',
            'label' => 'Compras',
            'icon' => 'M3.75 6.75h16.5l-1.5 12h-13.5l-1.5-12ZM8.25 6.75a3.75 3.75 0 0 1 7.5 0',
            'active' => request()->routeIs('admin.compras.*') || request()->routeIs('admin.cxp.*'),
            'show' => $can('compras.ver'),
            'children' => [
                ['label' => 'Órdenes de compra', 'href' => route('admin.compras.ordenes.index'), 'active' => request()->routeIs('admin.compras.ordenes.*'), 'show' => $can('compras.ver')],
                ['label' => 'Facturas de compra', 'href' => route('admin.cxp.facturas.index'), 'active' => request()->routeIs('admin.cxp.facturas.*'), 'show' => $can('cxp.ver')],
                ['label' => 'Gastos directos', 'href' => route('admin.compras.gastos.index'), 'active' => request()->routeIs('admin.compras.gastos.*'), 'show' => $can('compras.ver')],
            ],
        ],
        [
            'key' => 'caja',
            'label' => 'Caja menuda',
            'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
            'active' => request()->routeIs('admin.caja.*'),
            'show' => $can('caja.ver'),
            'href' => route('admin.caja.index'),
            'children' => [],
        ],
        [
            'key' => 'ventas',
            'label' => 'Ventas',
            'icon' => 'M4.5 6.75h15M6 6.75l1.5 12h9l1.5-12M9 10.5h6M9.75 3h4.5',
            'active' => request()->routeIs('admin.ventas.*') || request()->routeIs('admin.cxc.*'),
            'show' => $can('ventas.ver'),
            'children' => [
                ['label' => 'Cotizaciones', 'href' => route('admin.ventas.cotizaciones.index'), 'active' => request()->routeIs('admin.ventas.cotizaciones.*'), 'show' => $can('ventas.ver')],
                ['label' => 'Facturas de venta', 'href' => route('admin.ventas.facturas.index'), 'active' => request()->routeIs('admin.ventas.facturas.*'), 'show' => $can('ventas.ver')],
                ['label' => 'Cobros / Recibos', 'href' => route('admin.ventas.recibos.index'), 'active' => request()->routeIs('admin.ventas.recibos.*'), 'show' => $can('ventas.ver')],
                ['label' => 'Notas de crédito', 'href' => route('admin.ventas.notas-credito.index'), 'active' => request()->routeIs('admin.ventas.notas-credito.*'), 'show' => $can('ventas.ver')],
                ['label' => 'Vendedores', 'href' => route('admin.ventas.vendedores.index'), 'active' => request()->routeIs('admin.ventas.vendedores.*'), 'show' => $can('ventas.ver')],
            ],
        ],
        [
            'key' => 'fel',
            'label' => 'Facturación FEL',
            'icon' => 'M9 12h6m-6 3h4M5.25 4.5h13.5a.75.75 0 0 1 .75.75v15l-2.625-1.5L14.25 20.25l-2.25-1.5-2.25 1.5-2.625-1.5L4.5 20.25v-15a.75.75 0 0 1 .75-.75Z',
            'active' => request()->routeIs('admin.fel.*'),
            'show' => $can('fel.ver'),
            'children' => [
                ['label' => 'Documentos emitidos', 'href' => route('admin.fel.index'), 'active' => request()->routeIs('admin.fel.index'), 'show' => $can('fel.ver')],
                ['label' => 'Nueva factura', 'href' => route('admin.fel.create'), 'active' => request()->routeIs('admin.fel.create'), 'show' => $can('fel.gestionar')],
                ['label' => 'Configuración', 'href' => route('admin.fel.configuracion'), 'active' => request()->routeIs('admin.fel.configuracion'), 'show' => $can('fel.gestionar')],
            ],
        ],
        [
            'key' => 'bancos',
            'label' => 'Bancos',
            'icon' => 'M3 10.5h18M4.5 10.5V18M8.25 10.5V18M12 10.5V18m3.75-7.5V18M19.5 10.5V18M3.75 21h16.5M12 3l8.25 4.5H3.75L12 3Z',
            'active' => request()->routeIs('admin.bancos.*') || request()->routeIs('admin.caja.*') || request()->routeIs('admin.bco.*'),
            'show' => $can('bancos.ver') || $can('caja.ver'),
            'children' => [
                ['label' => 'Cuentas bancarias', 'href' => route('admin.bco.cuentas.index'), 'active' => request()->routeIs('admin.bco.cuentas.*'), 'show' => $can('bancos.ver')],
                ['label' => 'Movimientos', 'href' => route('admin.bco.movimientos.index'), 'active' => request()->routeIs('admin.bco.movimientos.*'), 'show' => $can('bancos.ver')],
                ['label' => 'Transferencias', 'href' => route('admin.bco.transferencias.index'), 'active' => request()->routeIs('admin.bco.transferencias.*'), 'show' => $can('bancos.ver')],
                ['label' => 'Conciliaciones', 'href' => route('admin.bco.conciliaciones.index'), 'active' => request()->routeIs('admin.bco.conciliaciones.*'), 'show' => $can('bancos.ver')],
                ['label' => 'Cheques', 'href' => route('admin.bco.cheques.index'), 'active' => request()->routeIs('admin.bco.cheques.*'), 'show' => $can('bancos.ver')],
                ['label' => 'Depósitos', 'href' => route('admin.bco.depositos.index'), 'active' => request()->routeIs('admin.bco.depositos.*'), 'show' => $can('bancos.ver')],
                ['label' => 'Caja chica', 'href' => route('admin.caja.index'), 'active' => request()->routeIs('admin.caja.*'), 'show' => $can('caja.ver')],
            ],
        ],
        [
            'key' => 'inventario',
            'label' => 'Inventario',
            'icon' => 'M21 8.25 12 3 3 8.25m18 0-9 5.25m9-5.25v7.5L12 21m0-7.5L3 8.25m9 5.25V21M3 8.25v7.5L12 21',
            'active' => request()->routeIs('admin.items.*') || request()->routeIs('admin.inventario.*'),
            'show' => $can('inventario.ver'),
            'children' => [
                ['label' => 'Productos / Servicios', 'href' => route('admin.items.index'), 'active' => request()->routeIs('admin.items.*'), 'show' => $can('inventario.ver')],
                ['label' => 'Almacenes', 'href' => route('admin.inventario.almacenes.index'), 'active' => request()->routeIs('admin.inventario.almacenes.*'), 'show' => $can('inventario.ver')],
                ['label' => 'Movimientos', 'href' => route('admin.inventario.movimientos.index'), 'active' => request()->routeIs('admin.inventario.movimientos.*'), 'show' => $can('inventario.ver')],
                ['label' => 'Transferencias', 'href' => route('admin.inventario.transferencias.index'), 'active' => request()->routeIs('admin.inventario.transferencias.*'), 'show' => $can('inventario.ver')],
                ['label' => 'Kardex', 'href' => route('admin.inventario.kardex.index'), 'active' => request()->routeIs('admin.inventario.kardex.*'), 'show' => $can('inventario.ver')],
            ],
        ],
        [
            'key' => 'ph',
            'label' => 'Prop. Horizontal',
            'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
            'active' => request()->routeIs('admin.ph.*'),
            'show' => $can('ph.ver'),
            'children' => [
                ['label' => 'Edificios', 'href' => route('admin.ph.edificios.index'), 'active' => request()->routeIs('admin.ph.edificios.*'), 'show' => $can('ph.ver')],
                ['label' => 'Propietarios', 'href' => route('admin.ph.propietarios.index'), 'active' => request()->routeIs('admin.ph.propietarios.*'), 'show' => $can('ph.ver')],
                ['label' => 'Tipos de cuota', 'href' => route('admin.ph.tipos-cuota.index'), 'active' => request()->routeIs('admin.ph.tipos-cuota.*'), 'show' => $can('ph.ver')],
                ['label' => 'Cuotas', 'href' => route('admin.ph.cuotas.index'), 'active' => request()->routeIs('admin.ph.cuotas.*'), 'show' => $can('ph.ver')],
                ['label' => 'Pagos', 'href' => route('admin.ph.pagos.index'), 'active' => request()->routeIs('admin.ph.pagos.*'), 'show' => $can('ph.ver')],
            ],
        ],
        [
            'key' => 'taller',
            'label' => 'Taller',
            'icon' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z',
            'active' => request()->routeIs('admin.taller.*'),
            'show' => $can('taller.ver'),
            'children' => [
                ['label' => 'Talleres', 'href' => route('admin.taller.talleres.index'), 'active' => request()->routeIs('admin.taller.talleres.*'), 'show' => $can('taller.ver')],
                ['label' => 'Sucursales', 'href' => route('admin.taller.sucursales.index'), 'active' => request()->routeIs('admin.taller.sucursales.*'), 'show' => $can('taller.ver')],
                ['label' => 'Áreas', 'href' => route('admin.taller.areas.index'), 'active' => request()->routeIs('admin.taller.areas.*'), 'show' => $can('taller.ver')],
                ['label' => 'Tipos de equipo', 'href' => route('admin.taller.tipos-equipo.index'), 'active' => request()->routeIs('admin.taller.tipos-equipo.*'), 'show' => $can('taller.ver')],
                ['label' => 'Marcas', 'href' => route('admin.taller.marcas.index'), 'active' => request()->routeIs('admin.taller.marcas.*'), 'show' => $can('taller.ver')],
                ['label' => 'Modelos', 'href' => route('admin.taller.modelos.index'), 'active' => request()->routeIs('admin.taller.modelos.*'), 'show' => $can('taller.ver')],
                ['label' => 'Especialidades', 'href' => route('admin.taller.especialidades.index'), 'active' => request()->routeIs('admin.taller.especialidades.*'), 'show' => $can('taller.ver')],
                ['label' => 'Síntomas', 'href' => route('admin.taller.sintomas.index'), 'active' => request()->routeIs('admin.taller.sintomas.*'), 'show' => $can('taller.ver')],
                ['label' => 'Servicios estándar', 'href' => route('admin.taller.servicios.index'), 'active' => request()->routeIs('admin.taller.servicios.*'), 'show' => $can('taller.ver')],
                ['label' => 'Checklists', 'href' => route('admin.taller.checklists.index'), 'active' => request()->routeIs('admin.taller.checklists.*'), 'show' => $can('taller.ver')],
                ['label' => 'Técnicos', 'href' => route('admin.taller.tecnicos.index'), 'active' => request()->routeIs('admin.taller.tecnicos.*'), 'show' => $can('taller.ver')],
            ],
        ],
        [
            'key' => 'activos',
            'label' => 'Activos Fijos',
            'icon' => 'M4.5 20.25h15M6 20.25V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75v13.5M9 9h6M9 12.75h6M9 16.5h3',
            'active' => request()->routeIs('admin.activos.*'),
            'show' => $can('activos.ver'),
            'children' => [
                ['label' => 'Registro de activos', 'href' => route('admin.activos.index'), 'active' => request()->routeIs('admin.activos.index') || request()->routeIs('admin.activos.show') || request()->routeIs('admin.activos.create'), 'show' => $can('activos.ver')],
                ['label' => 'Categorías', 'href' => route('admin.activos.categorias.index'), 'active' => request()->routeIs('admin.activos.categorias.*'), 'show' => $can('activos.ver')],
                ['label' => 'Ubicaciones', 'href' => route('admin.activos.ubicaciones.index'), 'active' => request()->routeIs('admin.activos.ubicaciones.*'), 'show' => $can('activos.ver')],
            ],
        ],
        [
            'key' => 'ia',
            'label' => 'Documentos IA',
            'icon' => 'M9 3.75h6M9 20.25h6M4.5 9v6M19.5 9v6M7.5 6.75h9v10.5h-9V6.75ZM10.5 10.5h.008v.008H10.5V10.5Zm3 0h.008v.008H13.5V10.5Zm-3 3h3',
            'show' => $can('ia.ver'),
            'children' => [
                ['label' => 'Por registrar', 'href' => null, 'active' => false, 'show' => $can('ia.ver')],
                ['label' => 'Fuentes (Drive)', 'href' => null, 'active' => false, 'show' => $can('ia.ver')],
            ],
        ],
        [
            'key' => 'companias',
            'label' => 'Compañías',
            'icon' => 'M3.75 21h16.5M4.5 3h15l-.75 18H5.25L4.5 3ZM9 7.5h1.5M13.5 7.5H15M9 12h1.5M13.5 12H15M9 16.5h1.5M13.5 16.5H15',
            'active' => request()->routeIs('admin.companias.*'),
            'show' => $can('companias.ver'),
            'children' => [
                ['label' => 'Directorio', 'href' => route('admin.companias.index'), 'active' => request()->routeIs('admin.companias.index'), 'show' => $can('companias.ver')],
                ['label' => 'Nueva compañía', 'href' => route('admin.companias.create'), 'active' => request()->routeIs('admin.companias.create'), 'show' => $can('companias.crear')],
            ],
        ],
        [
            'key' => 'configuracion',
            'label' => 'Configuración',
            'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
            'active' => request()->routeIs('admin.zonas.*') || request()->routeIs('admin.configuracion.*'),
            'show' => $can('zonas.ver') || $can('contabilidad.ver'),
            'children' => [
                ['label' => 'Zonas', 'href' => route('admin.zonas.index'), 'active' => request()->routeIs('admin.zonas.*'), 'show' => $can('zonas.ver')],
                ['label' => 'General (catálogos)', 'href' => route('admin.configuracion.index'), 'active' => request()->routeIs('admin.configuracion.*'), 'show' => $can('contabilidad.ver')],
            ],
        ],
        [
            'key' => 'seguridad',
            'label' => 'Seguridad',
            'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5A2.25 2.25 0 0 0 19.5 19.5v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
            'active' => request()->routeIs('admin.users.*') || request()->routeIs('admin.usuarios-compania.*'),
            'show' => Auth::user()->is_admin || $can('usuarios_compania.ver'),
            'children' => [
                ['label' => 'Usuarios', 'href' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*'), 'show' => Auth::user()->is_admin],
                ['label' => 'Accesos por compañía', 'href' => route('admin.usuarios-compania.index'), 'active' => request()->routeIs('admin.usuarios-compania.*'), 'show' => $can('usuarios_compania.ver')],
            ],
        ],
        [
            'key' => 'ayuda',
            'label' => 'Ayuda',
            'icon' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.326-.67.442-.745.361-1.451.999-1.451 1.827v.75M12 18h.008v.008H12V18Zm9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'show' => true,
            'active' => request()->routeIs('admin.ayuda.*'),
            'children' => [
                ['label' => 'Centro de ayuda', 'href' => route('admin.ayuda.index'), 'active' => request()->routeIs('admin.ayuda.index'), 'show' => true],
            ],
        ],
    ];

    $visibleGroups = collect($groups)->filter(function ($group) {
        if (! $group['show']) {
            return false;
        }

        if (($group['href'] ?? null) !== null) {
            return true;
        }

        return collect($group['children'] ?? [])->contains(fn ($child) => $child['show']);
    });
@endphp

<div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false"></div>

<aside
    :class="[
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        sidebarCollapsed ? 'lg:w-20' : 'lg:w-72'
    ]"
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#0d2d5e] transition-all duration-200 ease-out lg:translate-x-0"
>
    {{-- Logo eTax2 sobre franja blanca --}}
    <div class="flex h-16 shrink-0 items-center justify-between bg-white px-4 shadow-sm">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-1" x-show="! sidebarCollapsed">
            <span class="text-2xl font-extrabold tracking-tight text-[#0d2d5e]">eTax<span class="text-red-600">2</span></span>
        </a>
        <a href="{{ route('dashboard') }}" class="mx-auto" x-show="sidebarCollapsed" x-cloak>
            <span class="text-2xl font-extrabold text-red-600">2</span>
        </a>
        <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden" @click="sidebarOpen = false" aria-label="Cerrar menu">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <div x-show="! sidebarCollapsed" class="border-b border-white/10 p-4">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-blue-200/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
            <input x-model.debounce.100ms="menuQuery" type="search" placeholder="Buscar opción" class="h-9 w-full rounded-md border-0 bg-white/10 pl-9 pr-3 text-sm text-white placeholder-blue-200/60 focus:bg-white/15 focus:ring-1 focus:ring-blue-300">
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        <div class="space-y-1">
            @foreach ($visibleGroups as $group)
                @php
                    $children = collect($group['children'] ?? [])->filter(fn ($child) => $child['show']);
                    $isActive = $group['active'] ?? $children->contains(fn ($child) => $child['active']);
                    $href = $group['href'] ?? null;
                @endphp

                <div x-data="{ open: {{ $isActive ? 'true' : 'false' }} }" x-show="menuQuery === '' || '{{ Str::lower($group['label']) }}'.includes(menuQuery.toLowerCase()) || $el.textContent.toLowerCase().includes(menuQuery.toLowerCase())">
                    @if ($href)
                        <a href="{{ $href }}" class="group flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium {{ $isActive ? 'bg-blue-600 text-white shadow' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0 {{ $isActive ? 'text-white' : 'text-blue-300 group-hover:text-white' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $group['icon'] }}" />
                            </svg>
                            <span x-show="! sidebarCollapsed" class="truncate">{{ $group['label'] }}</span>
                        </a>
                    @else
                        <button type="button" @click="open = ! open; if (sidebarCollapsed) sidebarCollapsed = false" class="group flex h-10 w-full items-center gap-3 rounded-md px-3 text-left text-sm font-medium {{ $isActive ? 'bg-blue-600 text-white shadow' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0 {{ $isActive ? 'text-white' : 'text-blue-300 group-hover:text-white' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $group['icon'] }}" />
                            </svg>
                            <span x-show="! sidebarCollapsed" class="flex-1 truncate">{{ $group['label'] }}</span>
                            <svg x-show="! sidebarCollapsed" :class="open ? 'rotate-90' : ''" class="h-4 w-4 shrink-0 text-blue-300 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                        </button>
                    @endif

                    @if ($children->isNotEmpty())
                        <div x-show="(! sidebarCollapsed && open) || menuQuery !== ''" class="mt-1 space-y-1 pl-8">
                            @foreach ($children as $child)
                                <a
                                    href="{{ $child['href'] ?? '#' }}"
                                    @if (! $child['href']) @click.prevent @endif
                                    x-show="menuQuery === '' || '{{ Str::lower($child['label']) }}'.includes(menuQuery.toLowerCase())"
                                    class="block rounded-md px-3 py-2 text-sm {{ $child['active'] ? 'bg-white/15 font-semibold text-white' : (($child['href'] ?? null) ? 'text-blue-200 hover:bg-white/10 hover:text-white' : 'text-blue-400/50') }}"
                                >
                                    {{ $child['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </nav>

    {{-- Onda de la bandera de Panamá --}}
    <div x-show="! sidebarCollapsed" class="pointer-events-none shrink-0 select-none">
        <svg viewBox="0 0 288 90" class="block w-full" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M0 38 C 60 10, 120 60, 288 28 L288 90 L0 90 Z" fill="#ffffff"/>
            <path d="M0 62 C 70 38, 150 80, 288 52 L288 90 L0 90 Z" fill="#d21034"/>
            <path d="M52 30 l3.5 7.5 8 .9 -6 5.5 1.7 7.9 -7.2-4.2 -7.2 4.2 1.7-7.9 -6-5.5 8-.9 Z" fill="#005293"/>
            <path d="M210 60 l3 6.3 6.8.8 -5.1 4.6 1.5 6.7 -6.2-3.6 -6.2 3.6 1.5-6.7 -5.1-4.6 6.8-.8 Z" fill="#ffffff"/>
        </svg>
    </div>
</aside>

<div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'" class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur transition-all duration-200 print:hidden">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
        <button type="button" class="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="sidebarOpen = true" aria-label="Abrir menu">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>

        <button type="button" class="hidden rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:inline-flex" @click="sidebarCollapsed = ! sidebarCollapsed" aria-label="Colapsar menu">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h16" /></svg>
        </button>

        {{-- Selector de compañía con RUC --}}
        @isset($companiasDisponibles)
            @if ($companiasDisponibles->count() > 0)
                <form id="form-compania-activa" method="POST" action="{{ route('compania.activa') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="compania_id" id="input-compania-activa">
                </form>
                <div x-data="{ open: false }" class="relative min-w-0">
                    <button type="button" @click="open = ! open" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-slate-100 sm:gap-3">
                        <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-md bg-blue-50 text-[#0d2d5e] sm:flex">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15l-.75 18H5.25L4.5 3ZM9 7.5h1.5M13.5 7.5H15M9 12h1.5M13.5 12H15M9 16.5h1.5M13.5 16.5H15" /></svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block max-w-36 truncate text-sm font-semibold text-slate-900 sm:max-w-52">{{ $companiaActiva->nombre ?? 'Sin compañía' }}</span>
                            @if ($companiaActiva?->ruc)
                                <span class="hidden max-w-52 truncate text-xs text-slate-500 sm:block">RUC: {{ $companiaActiva->ruc }} DV {{ $companiaActiva->dv }}</span>
                            @endif
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute left-0 z-50 mt-1 w-72 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg">
                        @foreach ($companiasDisponibles as $cia)
                            <button
                                type="button"
                                @click="document.getElementById('input-compania-activa').value = '{{ $cia->id }}'; document.getElementById('form-compania-activa').submit();"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-slate-50 {{ ($companiaActiva?->id ?? null) === $cia->id ? 'bg-blue-50' : '' }}"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-slate-900">{{ $cia->nombre }}</span>
                                    @if ($cia->ruc)
                                        <span class="block truncate text-xs text-slate-500">RUC: {{ $cia->ruc }} DV {{ $cia->dv }}</span>
                                    @endif
                                </span>
                                @if (($companiaActiva?->id ?? null) === $cia->id)
                                    <svg class="ml-auto h-4 w-4 shrink-0 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        @endisset

        <div class="ml-auto flex items-center gap-2">
            {{-- Fecha --}}
            <span class="hidden items-center gap-2 rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-[#0d2d5e] md:inline-flex">
                <svg class="h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h12a2.25 2.25 0 0 1 2.25 2.25v11.25m-16.5 0a2.25 2.25 0 0 0 2.25 2.25h12a2.25 2.25 0 0 0 2.25-2.25m-16.5 0v-7.5h16.5v7.5" /></svg>
                {{ now()->format('d/m/Y') }}
            </span>

            {{-- Notificaciones --}}
            <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100" aria-label="Notificaciones">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022 23.848 23.848 0 0 0 5.455 1.31m5.714 0a3 3 0 0 1-5.714 0" /></svg>
                <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">0</span>
            </button>

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="flex h-10 items-center gap-2 rounded-md px-2 text-left hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#0d2d5e] text-sm font-semibold text-white">
                            {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 2, 'UTF-8'), 'UTF-8') }}
                        </span>
                        <span class="hidden max-w-36 truncate text-sm font-medium text-slate-700 lg:block">{{ Auth::user()->name }}</span>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="px-4 py-2">
                        <div class="truncate text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</div>
                        <div class="truncate text-xs text-slate-500">{{ Auth::user()->email }}</div>
                    </div>
                    <x-dropdown-link :href="route('profile.edit')">Perfil</x-dropdown-link>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Salir</x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</div>
