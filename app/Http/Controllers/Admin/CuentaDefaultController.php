<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuentaDefaultController extends Controller
{
    private const CLAVES = [
        'CXC'                 => 'Cuentas por Cobrar Clientes',
        'CXP'                 => 'Cuentas por Pagar Proveedores',
        'VENTAS'              => 'Ventas y Prestación de Servicios',
        'ITBMS_POR_PAGAR'     => 'ITBMS por Pagar',
        'ITBMS_CREDITO'       => 'ITBMS Crédito Fiscal',
        'DESCUENTOS_VENTA'    => 'Devoluciones y Descuentos en Ventas',
        'GASTO_DEFAULT'       => 'Otros Gastos (gasto por defecto CxP)',
        'BANCO_DEFAULT'       => 'Banco (cobros/pagos por defecto)',
        'CAJA_DEFAULT'        => 'Caja General',
        'INVENTARIO'          => 'Inventario',
        'COSTO_VENTAS'        => 'Costo de Ventas',
        'ANTICIPO_CLIENTE'    => 'Anticipos de Clientes',
        'ANTICIPO_PROVEEDOR'  => 'Anticipos a Proveedores',
        'RETENCIONES'         => 'Retenciones por Pagar',
        'REDONDEO'            => 'Diferencias de Redondeo',
        'UTILIDADES_RETENIDAS'=> 'Superávit Acumulado',
        'SALARIOS_POR_PAGAR'  => 'Salarios por Pagar',
    ];

    public function index(Request $request): View
    {
        $compania = $this->companiaActiva($request);

        $defaults = CuentaDefault::with('cuenta')
            ->where('compania_id', $compania->id)
            ->get()
            ->keyBy('clave');

        $cuentas = CuentaContable::where('compania_id', $compania->id)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.cuentas-default.index', [
            'compania' => $compania,
            'claves'   => self::CLAVES,
            'defaults' => $defaults,
            'cuentas'  => $cuentas,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $compania = $this->companiaActiva($request);

        $data = $request->validate([
            'defaults'         => ['nullable', 'array'],
            'defaults.*'       => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        $usuario = $request->user()->email;

        foreach (self::CLAVES as $clave => $descripcion) {
            $cuentaId = $data['defaults'][$clave] ?? null;

            if ($cuentaId) {
                CuentaDefault::updateOrCreate(
                    ['compania_id' => $compania->id, 'clave' => $clave],
                    [
                        'cuenta_id'   => (int) $cuentaId,
                        'descripcion' => $descripcion,
                        'updated_by'  => $usuario,
                        'created_by'  => $usuario,
                    ]
                );
            } else {
                CuentaDefault::where('compania_id', $compania->id)
                    ->where('clave', $clave)
                    ->delete();
            }
        }

        return back()->with('status', 'Cuentas por defecto actualizadas.');
    }

    private function companiaActiva(Request $request): Compania
    {
        $companiaId = session('compania_activa_id');
        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', (int) $companiaId),
            403
        );

        return Compania::findOrFail($companiaId);
    }
}
