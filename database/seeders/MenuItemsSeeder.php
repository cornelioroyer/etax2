<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Carga inicial del menú lateral en core_menu_items, equivalente 1:1 al array
 * estático que vivía en resources/views/layouts/navigation.blade.php.
 *
 * Idempotente: usa updateOrCreate por 'clave', así re-ejecutarlo no duplica y
 * refleja cambios. No borra opciones que ya no estén aquí (para no perder
 * personalizaciones hechas desde la futura UI de administración).
 */
class MenuItemsSeeder extends Seeder
{
    public function run(): void
    {
        $this->insertar($this->arbol(), null);

        Cache::forget(MenuItem::CACHE_KEY);
    }

    /** Inserta recursivamente, fijando parent_id y orden (10,20,30…). */
    private function insertar(array $items, ?int $parentId): void
    {
        $orden = 0;

        foreach ($items as $item) {
            $orden += 10;

            $hijos = $item['children'] ?? [];
            unset($item['children']);

            $fila = MenuItem::updateOrCreate(
                ['clave' => $item['clave']],
                array_merge($item, [
                    'parent_id' => $parentId,
                    'orden' => $orden,
                    'activo' => true,
                ]),
            );

            if ($hijos !== []) {
                $this->insertar($hijos, (int) $fila->id);
            }
        }
    }

    /** Árbol completo del menú (mismo contenido/orden que el Blade heredado). */
    private function arbol(): array
    {
        return [
            [
                'clave' => 'inicio', 'etiqueta' => 'Dashboard', 'modulo' => 'inicio',
                'icono' => 'M3 12l9-9 9 9M5.25 10.5v9h13.5v-9',
                'ruta_nombre' => 'dashboard', 'ruta_activa_patron' => 'dashboard',
            ],
            [
                'clave' => 'asistente', 'etiqueta' => 'Asistente IA', 'modulo' => 'asistente',
                'icono' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z',
                'ruta_nombre' => 'admin.asistente', 'ruta_activa_patron' => 'admin.asistente',
                'solo_admin' => true,
            ],
            [
                'clave' => 'companias', 'etiqueta' => 'Compañías', 'modulo' => 'companias',
                'icono' => 'M3.75 21h16.5M4.5 3h15l-.75 18H5.25L4.5 3ZM9 7.5h1.5M13.5 7.5H15M9 12h1.5M13.5 12H15M9 16.5h1.5M13.5 16.5H15',
                'permiso' => 'companias.ver',
                'children' => [
                    ['clave' => 'companias.directorio', 'etiqueta' => 'Directorio', 'permiso' => 'companias.ver', 'ruta_nombre' => 'admin.companias.index', 'ruta_activa_patron' => 'admin.companias.index'],
                    ['clave' => 'companias.crear', 'etiqueta' => 'Nueva compañía', 'permiso' => 'companias.crear', 'ruta_nombre' => 'admin.companias.create', 'ruta_activa_patron' => 'admin.companias.create'],
                ],
            ],
            [
                'clave' => 'compras', 'etiqueta' => 'Compras', 'modulo' => 'compras',
                'icono' => 'M3.75 6.75h16.5l-1.5 12h-13.5l-1.5-12ZM8.25 6.75a3.75 3.75 0 0 1 7.5 0',
                'permiso' => 'compras.ver',
                'children' => [
                    ['clave' => 'compras.proveedores', 'etiqueta' => 'Proveedores', 'permiso' => 'contactos.ver', 'ruta_nombre' => 'admin.contactos.index', 'ruta_params' => ['tipo' => 'PROVEEDOR'], 'ruta_activa_patron' => 'admin.contactos.*', 'activa_query_key' => 'tipo', 'activa_query_val' => 'PROVEEDOR'],
                    ['clave' => 'compras.ordenes', 'etiqueta' => 'Órdenes de compra', 'permiso' => 'compras.ver', 'ruta_nombre' => 'admin.compras.ordenes.index', 'ruta_activa_patron' => 'admin.compras.ordenes.*'],
                    ['clave' => 'compras.facturas', 'etiqueta' => 'Facturas de compra', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.facturas.index', 'ruta_activa_patron' => 'admin.cxp.facturas.*'],
                    ['clave' => 'compras.qr_cufe', 'etiqueta' => 'Registrar por QR / CUFE', 'permiso' => 'cxp.gestionar', 'ruta_nombre' => 'admin.cxp.facturas.desde-cufe.form', 'ruta_activa_patron' => 'admin.cxp.facturas.desde-cufe*'],
                ],
            ],
            [
                'clave' => 'ventas', 'etiqueta' => 'Ventas', 'modulo' => 'ventas',
                'icono' => 'M4.5 6.75h15M6 6.75l1.5 12h9l1.5-12M9 10.5h6M9.75 3h4.5',
                'permiso' => 'ventas.ver',
                'children' => [
                    ['clave' => 'ventas.cotizaciones', 'etiqueta' => 'Cotizaciones', 'permiso' => 'ventas.ver', 'ruta_nombre' => 'admin.ventas.cotizaciones.index', 'ruta_activa_patron' => 'admin.ventas.cotizaciones.*'],
                    ['clave' => 'ventas.facturas', 'etiqueta' => 'Facturas de venta', 'permiso' => 'ventas.ver', 'ruta_nombre' => 'admin.ventas.facturas.index', 'ruta_activa_patron' => 'admin.ventas.facturas.*'],
                    ['clave' => 'ventas.recibos', 'etiqueta' => 'Cobros / Recibos', 'permiso' => 'ventas.ver', 'ruta_nombre' => 'admin.ventas.recibos.index', 'ruta_activa_patron' => 'admin.ventas.recibos.*'],
                    ['clave' => 'ventas.vendedores', 'etiqueta' => 'Vendedores', 'permiso' => 'ventas.ver', 'ruta_nombre' => 'admin.ventas.vendedores.index', 'ruta_activa_patron' => 'admin.ventas.vendedores.*'],
                ],
            ],
            [
                'clave' => 'fel', 'etiqueta' => 'Factura Electrónica', 'modulo' => 'fel',
                'icono' => 'M9 12h6m-6 3h4M5.25 4.5h13.5a.75.75 0 0 1 .75.75v15l-2.625-1.5L14.25 20.25l-2.25-1.5-2.25 1.5-2.625-1.5L4.5 20.25v-15a.75.75 0 0 1 .75-.75Z',
                'permiso' => 'fel.ver',
                'children' => [
                    ['clave' => 'fel.documentos', 'etiqueta' => 'Documentos emitidos', 'permiso' => 'fel.ver', 'ruta_nombre' => 'admin.fel.index', 'ruta_activa_patron' => 'admin.fel.index'],
                    ['clave' => 'fel.nueva', 'etiqueta' => 'Nueva factura', 'permiso' => 'fel.gestionar', 'ruta_nombre' => 'admin.fel.create', 'ruta_activa_patron' => 'admin.fel.create'],
                    ['clave' => 'fel.configuracion', 'etiqueta' => 'Configuración', 'permiso' => 'fel.gestionar', 'ruta_nombre' => 'admin.fel.configuracion', 'ruta_activa_patron' => 'admin.fel.configuracion'],
                ],
            ],
            [
                'clave' => 'cxp', 'etiqueta' => 'Cuentas por Pagar', 'modulo' => 'cxp',
                'icono' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
                'children' => [
                    ['clave' => 'cxp.proveedores', 'etiqueta' => 'Proveedores', 'permiso' => 'contactos.ver', 'ruta_nombre' => 'admin.contactos.index', 'ruta_params' => ['tipo' => 'PROVEEDOR'], 'ruta_activa_patron' => 'admin.contactos.*', 'activa_query_key' => 'tipo', 'activa_query_val' => 'PROVEEDOR'],
                    ['clave' => 'cxp.facturas', 'etiqueta' => 'Facturas de Compras', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.facturas.index', 'ruta_activa_patron' => 'admin.cxp.facturas.*'],
                    ['clave' => 'cxp.recurrentes', 'etiqueta' => 'Facturas recurrentes', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.recurrentes.index', 'ruta_activa_patron' => 'admin.cxp.recurrentes.*'],
                    ['clave' => 'cxp.pagos', 'etiqueta' => 'Pagos', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.pagos.index', 'ruta_activa_patron' => 'admin.cxp.pagos.*'],
                    ['clave' => 'cxp.anticipos', 'etiqueta' => 'Anticipos', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.anticipos.index', 'ruta_activa_patron' => 'admin.cxp.anticipos.*'],
                    ['clave' => 'cxp.notas', 'etiqueta' => 'Notas crédito/débito', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.notas.index', 'ruta_activa_patron' => 'admin.cxp.notas.*'],
                    ['clave' => 'cxp.antiguedad', 'etiqueta' => 'Antigüedad de saldos', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.antiguedad', 'ruta_activa_patron' => 'admin.cxp.antiguedad'],
                    ['clave' => 'cxp.estado_cuenta', 'etiqueta' => 'Estado de cuenta', 'permiso' => 'cxp.ver', 'ruta_nombre' => 'admin.cxp.estado-cuenta', 'ruta_activa_patron' => 'admin.cxp.estado-cuenta'],
                ],
            ],
            [
                'clave' => 'cxc', 'etiqueta' => 'Cuentas por Cobrar', 'modulo' => 'cxc',
                'icono' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
                'children' => [
                    ['clave' => 'cxc.clientes', 'etiqueta' => 'Clientes', 'permiso' => 'contactos.ver', 'ruta_nombre' => 'admin.contactos.index', 'ruta_params' => ['tipo' => 'CLIENTE'], 'ruta_activa_patron' => 'admin.contactos.*', 'activa_query_key' => 'tipo', 'activa_query_val' => 'CLIENTE'],
                    ['clave' => 'cxc.facturas', 'etiqueta' => 'Facturas', 'permiso' => 'cxc.ver', 'ruta_nombre' => 'admin.cxc.facturas.index', 'ruta_activa_patron' => 'admin.cxc.facturas.*'],
                    ['clave' => 'cxc.cobros', 'etiqueta' => 'Cobros', 'permiso' => 'cxc.ver', 'ruta_nombre' => 'admin.cxc.cobros.index', 'ruta_activa_patron' => 'admin.cxc.cobros.*'],
                    ['clave' => 'cxc.notas', 'etiqueta' => 'Notas crédito/débito', 'permiso' => 'cxc.ver', 'ruta_nombre' => 'admin.cxc.notas.index', 'ruta_activa_patron' => 'admin.cxc.notas.*'],
                    ['clave' => 'cxc.antiguedad', 'etiqueta' => 'Antigüedad de saldos', 'permiso' => 'cxc.ver', 'ruta_nombre' => 'admin.cxc.antiguedad', 'ruta_activa_patron' => 'admin.cxc.antiguedad'],
                    ['clave' => 'cxc.estado_cuenta', 'etiqueta' => 'Estado de cuenta', 'permiso' => 'cxc.ver', 'ruta_nombre' => 'admin.cxc.estado-cuenta', 'ruta_activa_patron' => 'admin.cxc.estado-cuenta'],
                ],
            ],
            [
                'clave' => 'contabilidad', 'etiqueta' => 'Contabilidad', 'modulo' => 'contabilidad',
                'icono' => 'M9 7.5h6M9 12h6M9 16.5h3M6.75 3h10.5A2.25 2.25 0 0 1 19.5 5.25v13.5A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75V5.25A2.25 2.25 0 0 1 6.75 3Z',
                'permiso' => 'contabilidad.ver',
                'children' => [
                    ['clave' => 'contabilidad.estados', 'etiqueta' => 'Estados Financieros', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'dashboard'],
                    ['clave' => 'contabilidad.plan_cuentas', 'etiqueta' => 'Plan de cuentas', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.cuentas.index', 'ruta_activa_patron' => 'admin.cuentas.*'],
                    ['clave' => 'contabilidad.cuentas_default', 'etiqueta' => 'Cuentas por defecto', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.cuentas-default.index', 'ruta_activa_patron' => 'admin.cuentas-default.*'],
                    ['clave' => 'contabilidad.plantillas_cuentas', 'etiqueta' => 'Plantillas de cuentas', 'solo_admin' => true, 'ruta_nombre' => 'admin.plantillas-cuentas.index', 'ruta_activa_patron' => 'admin.plantillas-cuentas.*'],
                    ['clave' => 'contabilidad.diarios', 'etiqueta' => 'Diarios', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.diarios.index', 'ruta_activa_patron' => 'admin.diarios.*'],
                    ['clave' => 'contabilidad.asientos', 'etiqueta' => 'Asientos', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.asientos.index', 'ruta_activa_patron' => 'admin.asientos.*'],
                    ['clave' => 'contabilidad.asientos_recurrentes', 'etiqueta' => 'Asientos recurrentes', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.asientos-recurrentes.index', 'ruta_activa_patron' => 'admin.asientos-recurrentes.*'],
                    ['clave' => 'contabilidad.periodos', 'etiqueta' => 'Períodos contables', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.periodos.index', 'ruta_activa_patron' => 'admin.periodos.*'],
                    ['clave' => 'contabilidad.cierre', 'etiqueta' => 'Cierre contable', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.contabilidad.cierres.index', 'ruta_activa_patron' => 'admin.contabilidad.cierres.*'],
                    ['clave' => 'contabilidad.cierre_anual', 'etiqueta' => 'Cierre anual', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.cierre-anual.index', 'ruta_activa_patron' => 'admin.cierre-anual.*'],
                    ['clave' => 'contabilidad.clases', 'etiqueta' => 'Clases de asiento', 'permiso' => 'dimensiones.ver', 'ruta_nombre' => 'admin.dimensiones.clases.index', 'ruta_activa_patron' => 'admin.dimensiones.clases.*'],
                    ['clave' => 'contabilidad.lineas_negocio', 'etiqueta' => 'Líneas de negocio', 'permiso' => 'dimensiones.ver', 'ruta_nombre' => 'admin.dimensiones.lineas-negocio.index', 'ruta_activa_patron' => 'admin.dimensiones.lineas-negocio.*'],
                    ['clave' => 'contabilidad.ubicaciones', 'etiqueta' => 'Ubicaciones', 'permiso' => 'dimensiones.ver', 'ruta_nombre' => 'admin.dimensiones.ubicaciones.index', 'ruta_activa_patron' => 'admin.dimensiones.ubicaciones.*'],
                ],
            ],
            [
                'clave' => 'reportes', 'etiqueta' => 'Reportes', 'modulo' => 'reportes',
                'icono' => 'M4.5 19.5V4.5m0 15h15M8.25 15l3-3 2.25 2.25L18 9',
                'permiso' => 'reportes.ver',
                'children' => [
                    ['clave' => 'reportes.comprobacion', 'etiqueta' => 'Balance de Comprobación', 'permiso' => 'reportes.ver', 'ruta_nombre' => 'admin.reportes.comprobacion', 'ruta_activa_patron' => 'admin.reportes.comprobacion'],
                    ['clave' => 'reportes.balance', 'etiqueta' => 'Balance de Situación', 'permiso' => 'reportes.ver', 'ruta_nombre' => 'admin.reportes.balance', 'ruta_activa_patron' => 'admin.reportes.balance'],
                    ['clave' => 'reportes.resultado', 'etiqueta' => 'Estado de Resultado', 'permiso' => 'reportes.ver', 'ruta_nombre' => 'admin.reportes.resultado', 'ruta_activa_patron' => 'admin.reportes.resultado'],
                    ['clave' => 'reportes.comparativo', 'etiqueta' => 'Comparativo Mensual', 'permiso' => 'reportes.ver', 'ruta_nombre' => 'admin.reportes.comparativo', 'ruta_activa_patron' => 'admin.reportes.comparativo'],
                    ['clave' => 'reportes.flujo', 'etiqueta' => 'Flujo de Efectivo', 'permiso' => 'reportes.ver', 'ruta_nombre' => 'admin.reportes.flujo-caja', 'ruta_activa_patron' => 'admin.reportes.flujo-caja'],
                    ['clave' => 'reportes.itbms', 'etiqueta' => 'Liquidación ITBMS', 'permiso' => 'reportes.ver', 'ruta_nombre' => 'admin.reportes.liquidacion-itbms', 'ruta_activa_patron' => 'admin.reportes.liquidacion-itbms'],
                    ['clave' => 'reportes.cuadre', 'etiqueta' => 'Cuadre de Auxiliares', 'permiso' => 'reportes.ver', 'ruta_nombre' => 'admin.reportes.cuadre-auxiliares', 'ruta_activa_patron' => 'admin.reportes.cuadre-auxiliares'],
                ],
            ],
            [
                'clave' => 'presupuestos', 'etiqueta' => 'Presupuestos', 'modulo' => 'presupuestos',
                'icono' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z',
                'permiso' => 'presupuestos.ver',
                'children' => [
                    ['clave' => 'presupuestos.presupuestos', 'etiqueta' => 'Presupuestos', 'permiso' => 'presupuestos.ver', 'ruta_nombre' => 'admin.presupuestos.index', 'ruta_activa_patron' => 'admin.presupuestos.index admin.presupuestos.show admin.presupuestos.create admin.presupuestos.edit'],
                    ['clave' => 'presupuestos.escenarios', 'etiqueta' => 'Escenarios', 'permiso' => 'presupuestos.ver', 'ruta_nombre' => 'admin.presupuestos.escenarios.index', 'ruta_activa_patron' => 'admin.presupuestos.escenarios.*'],
                    ['clave' => 'presupuestos.versiones', 'etiqueta' => 'Versiones', 'permiso' => 'presupuestos.ver', 'ruta_nombre' => 'admin.presupuestos.versiones.index', 'ruta_activa_patron' => 'admin.presupuestos.versiones.*'],
                ],
            ],
            [
                'clave' => 'bancos', 'etiqueta' => 'Bancos', 'modulo' => 'bancos',
                'icono' => 'M3 10.5h18M4.5 10.5V18M8.25 10.5V18M12 10.5V18m3.75-7.5V18M19.5 10.5V18M3.75 21h16.5M12 3l8.25 4.5H3.75L12 3Z',
                'children' => [
                    ['clave' => 'bancos.cuentas', 'etiqueta' => 'Cuentas bancarias', 'permiso' => 'bancos.ver', 'ruta_nombre' => 'admin.bco.cuentas.index', 'ruta_activa_patron' => 'admin.bco.cuentas.*'],
                    ['clave' => 'bancos.movimientos', 'etiqueta' => 'Movimientos', 'permiso' => 'bancos.ver', 'ruta_nombre' => 'admin.bco.movimientos.index', 'ruta_activa_patron' => 'admin.bco.movimientos.*'],
                    ['clave' => 'bancos.transferencias', 'etiqueta' => 'Transferencias', 'permiso' => 'bancos.ver', 'ruta_nombre' => 'admin.bco.transferencias.index', 'ruta_activa_patron' => 'admin.bco.transferencias.*'],
                    ['clave' => 'bancos.conciliaciones', 'etiqueta' => 'Conciliaciones', 'permiso' => 'bancos.ver', 'ruta_nombre' => 'admin.bco.conciliaciones.index', 'ruta_activa_patron' => 'admin.bco.conciliaciones.*'],
                    ['clave' => 'bancos.cheques', 'etiqueta' => 'Cheques', 'permiso' => 'bancos.ver', 'ruta_nombre' => 'admin.bco.cheques.index', 'ruta_activa_patron' => 'admin.bco.cheques.*'],
                    ['clave' => 'bancos.depositos', 'etiqueta' => 'Depósitos', 'permiso' => 'bancos.ver', 'ruta_nombre' => 'admin.bco.depositos.index', 'ruta_activa_patron' => 'admin.bco.depositos.*'],
                    ['clave' => 'bancos.caja_menuda', 'etiqueta' => 'Caja Menuda', 'permiso' => 'caja.ver', 'ruta_nombre' => 'admin.caja.index', 'ruta_activa_patron' => 'admin.caja.*'],
                ],
            ],
            [
                'clave' => 'inventario', 'etiqueta' => 'Inventario', 'modulo' => 'inventario',
                'icono' => 'M21 8.25 12 3 3 8.25m18 0-9 5.25m9-5.25v7.5L12 21m0-7.5L3 8.25m9 5.25V21M3 8.25v7.5L12 21',
                'permiso' => 'inventario.ver',
                'children' => [
                    ['clave' => 'inventario.productos', 'etiqueta' => 'Productos / Servicios', 'permiso' => 'inventario.ver', 'ruta_nombre' => 'admin.items.index', 'ruta_activa_patron' => 'admin.items.*'],
                    ['clave' => 'inventario.almacenes', 'etiqueta' => 'Almacenes', 'permiso' => 'inventario.ver', 'ruta_nombre' => 'admin.inventario.almacenes.index', 'ruta_activa_patron' => 'admin.inventario.almacenes.*'],
                    ['clave' => 'inventario.existencias', 'etiqueta' => 'Existencias (consolidado)', 'permiso' => 'inventario.ver', 'ruta_nombre' => 'admin.inventario.existencias.consolidado', 'ruta_activa_patron' => 'admin.inventario.existencias.*'],
                    ['clave' => 'inventario.movimientos', 'etiqueta' => 'Movimientos', 'permiso' => 'inventario.ver', 'ruta_nombre' => 'admin.inventario.movimientos.index', 'ruta_activa_patron' => 'admin.inventario.movimientos.*'],
                    ['clave' => 'inventario.transferencias', 'etiqueta' => 'Transferencias', 'permiso' => 'inventario.ver', 'ruta_nombre' => 'admin.inventario.transferencias.index', 'ruta_activa_patron' => 'admin.inventario.transferencias.*'],
                    ['clave' => 'inventario.kardex', 'etiqueta' => 'Kardex', 'permiso' => 'inventario.ver', 'ruta_nombre' => 'admin.inventario.kardex.index', 'ruta_activa_patron' => 'admin.inventario.kardex.*'],
                ],
            ],
            [
                'clave' => 'activos', 'etiqueta' => 'Activos Fijos', 'modulo' => 'activos',
                'icono' => 'M4.5 20.25h15M6 20.25V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75v13.5M9 9h6M9 12.75h6M9 16.5h3',
                'permiso' => 'activos.ver',
                'children' => [
                    ['clave' => 'activos.registro', 'etiqueta' => 'Registro de activos', 'permiso' => 'activos.ver', 'ruta_nombre' => 'admin.activos.index', 'ruta_activa_patron' => 'admin.activos.index admin.activos.show admin.activos.create'],
                    ['clave' => 'activos.categorias', 'etiqueta' => 'Categorías', 'permiso' => 'activos.ver', 'ruta_nombre' => 'admin.activos.categorias.index', 'ruta_activa_patron' => 'admin.activos.categorias.*'],
                    ['clave' => 'activos.ubicaciones', 'etiqueta' => 'Ubicaciones', 'permiso' => 'activos.ver', 'ruta_nombre' => 'admin.activos.ubicaciones.index', 'ruta_activa_patron' => 'admin.activos.ubicaciones.*'],
                ],
            ],
            [
                'clave' => 'ia', 'etiqueta' => 'Documentos IA', 'modulo' => 'ia',
                'icono' => 'M9 3.75h6M9 20.25h6M4.5 9v6M19.5 9v6M7.5 6.75h9v10.5h-9V6.75ZM10.5 10.5h.008v.008H10.5V10.5Zm3 0h.008v.008H13.5V10.5Zm-3 3h3',
                'permiso' => 'ia.ver',
                'children' => [
                    ['clave' => 'ia.por_registrar', 'etiqueta' => 'Por registrar', 'permiso' => 'ia.ver'],
                    ['clave' => 'ia.fuentes', 'etiqueta' => 'Fuentes (Drive)', 'permiso' => 'ia.ver'],
                ],
            ],
            [
                'clave' => 'ph', 'etiqueta' => 'Prop. Horizontal', 'modulo' => 'ph',
                'icono' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
                'permiso' => 'ph.ver',
                'children' => [
                    ['clave' => 'ph.edificios', 'etiqueta' => 'Edificios', 'permiso' => 'ph.ver', 'ruta_nombre' => 'admin.ph.edificios.index', 'ruta_activa_patron' => 'admin.ph.edificios.*'],
                    ['clave' => 'ph.propietarios', 'etiqueta' => 'Propietarios', 'permiso' => 'ph.ver', 'ruta_nombre' => 'admin.ph.propietarios.index', 'ruta_activa_patron' => 'admin.ph.propietarios.*'],
                    ['clave' => 'ph.tipos_cuota', 'etiqueta' => 'Tipos de cuota', 'permiso' => 'ph.ver', 'ruta_nombre' => 'admin.ph.tipos-cuota.index', 'ruta_activa_patron' => 'admin.ph.tipos-cuota.*'],
                    ['clave' => 'ph.cuotas', 'etiqueta' => 'Cuotas', 'permiso' => 'ph.ver', 'ruta_nombre' => 'admin.ph.cuotas.index', 'ruta_activa_patron' => 'admin.ph.cuotas.*'],
                    ['clave' => 'ph.pagos', 'etiqueta' => 'Pagos', 'permiso' => 'ph.ver', 'ruta_nombre' => 'admin.ph.pagos.index', 'ruta_activa_patron' => 'admin.ph.pagos.*'],
                ],
            ],
            $this->grupoSimple('taller', 'Taller', 'taller.ver',
                'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z',
                'admin.taller', [
                    'ordenes' => 'Órdenes', 'presupuestos' => 'Presupuestos', 'citas' => 'Citas',
                    'talleres' => 'Talleres', 'sucursales' => 'Sucursales', 'areas' => 'Áreas',
                    'tipos-equipo' => 'Tipos de equipo', 'marcas' => 'Marcas', 'modelos' => 'Modelos',
                    'especialidades' => 'Especialidades', 'sintomas' => 'Síntomas',
                    'servicios' => 'Servicios estándar', 'checklists' => 'Checklists',
                    'tecnicos' => 'Técnicos', 'equipos' => 'Equipos',
                ]),
            $this->grupoSimple('edu', 'Admin. Educativa', 'edu.ver',
                'M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
                'admin.edu', [
                    'instituciones' => 'Instituciones', 'sedes' => 'Sedes', 'configuracion' => 'Configuración',
                    'niveles' => 'Niveles académicos', 'programas' => 'Programas', 'grados' => 'Grados',
                    'grupos' => 'Grupos', 'periodos' => 'Períodos académicos', 'asignaturas' => 'Asignaturas',
                    'esquemas' => 'Esquemas de calificación', 'estudiantes' => 'Estudiantes',
                    'docentes' => 'Docentes', 'matriculas' => 'Matrículas', 'horarios' => 'Horarios',
                    'evaluaciones' => 'Evaluaciones', 'asistencias' => 'Asistencias',
                    'conceptos-cobro' => 'Conceptos de cobro', 'planes-cobro' => 'Planes de cobro',
                    'generaciones-cobro' => 'Generaciones de cobro', 'comunicados' => 'Comunicados',
                ]),
            [
                'clave' => 'configuracion', 'etiqueta' => 'Configuración', 'modulo' => 'configuracion',
                'icono' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
                'children' => [
                    ['clave' => 'configuracion.zonas', 'etiqueta' => 'Zonas', 'permiso' => 'zonas.ver', 'ruta_nombre' => 'admin.zonas.index', 'ruta_activa_patron' => 'admin.zonas.*'],
                    ['clave' => 'configuracion.general', 'etiqueta' => 'General (catálogos)', 'permiso' => 'contabilidad.ver', 'ruta_nombre' => 'admin.configuracion.index', 'ruta_activa_patron' => 'admin.configuracion.*'],
                ],
            ],
            [
                'clave' => 'seguridad', 'etiqueta' => 'Seguridad', 'modulo' => 'seguridad',
                'icono' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5A2.25 2.25 0 0 0 19.5 19.5v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
                'children' => [
                    ['clave' => 'seguridad.usuarios', 'etiqueta' => 'Usuarios', 'solo_admin' => true, 'ruta_nombre' => 'admin.users.index', 'ruta_activa_patron' => 'admin.users.*'],
                    ['clave' => 'seguridad.roles', 'etiqueta' => 'Roles', 'solo_admin' => true, 'ruta_nombre' => 'admin.roles.index', 'ruta_activa_patron' => 'admin.roles.*'],
                    ['clave' => 'seguridad.accesos', 'etiqueta' => 'Accesos por compañía', 'permiso' => 'usuarios_compania.gestionar', 'ruta_nombre' => 'admin.usuarios-compania.index', 'ruta_activa_patron' => 'admin.usuarios-compania.*'],
                    ['clave' => 'seguridad.respaldos', 'etiqueta' => 'Respaldo de datos', 'permiso' => 'respaldos.gestionar', 'ruta_nombre' => 'admin.respaldos.index', 'ruta_activa_patron' => 'admin.respaldos.*'],
                    ['clave' => 'seguridad.auditoria', 'etiqueta' => 'Auditoría', 'solo_admin' => true, 'ruta_nombre' => 'admin.auditoria.index', 'ruta_activa_patron' => 'admin.auditoria.index admin.auditoria.show'],
                    ['clave' => 'seguridad.auditoria_global', 'etiqueta' => 'Auditoría global', 'solo_admin' => true, 'ruta_nombre' => 'admin.auditoria.global', 'ruta_activa_patron' => 'admin.auditoria.global'],
                    ['clave' => 'seguridad.menu', 'etiqueta' => 'Menú del sistema', 'solo_admin' => true, 'ruta_nombre' => 'admin.menu-items.index', 'ruta_activa_patron' => 'admin.menu-items.*'],
                ],
            ],
            [
                'clave' => 'ayuda', 'etiqueta' => 'Ayuda', 'modulo' => 'ayuda',
                'icono' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.326-.67.442-.745.361-1.451.999-1.451 1.827v.75M12 18h.008v.008H12V18Zm9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                'children' => [
                    ['clave' => 'ayuda.centro', 'etiqueta' => 'Centro de ayuda', 'dispatch_evento' => 'open-help'],
                ],
            ],
        ];
    }

    /**
     * Grupo cuyos hijos siguen el patrón admin.<modulo>.<x>.index /
     * admin.<modulo>.<x>.* y comparten un mismo permiso (taller, edu).
     *
     * @param  array<string,string>  $hijos  [segmento => etiqueta]
     */
    private function grupoSimple(string $clave, string $etiqueta, string $permiso, string $icono, string $prefijoRuta, array $hijos): array
    {
        $children = [];
        foreach ($hijos as $seg => $label) {
            $children[] = [
                'clave' => $clave.'.'.str_replace('-', '_', $seg),
                'etiqueta' => $label,
                'permiso' => $permiso,
                'ruta_nombre' => $prefijoRuta.'.'.$seg.'.index',
                'ruta_activa_patron' => $prefijoRuta.'.'.$seg.'.*',
            ];
        }

        return [
            'clave' => $clave, 'etiqueta' => $etiqueta, 'modulo' => $clave,
            'icono' => $icono, 'permiso' => $permiso, 'children' => $children,
        ];
    }
}
