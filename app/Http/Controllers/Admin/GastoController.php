<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Gastos directos — registro rápido de un gasto pagado al contado
 * (banco o caja). No crea CxP; posta un asiento directo.
 *
 * Útil para: caja chica, gastos menores, comisiones bancarias,
 * servicios básicos pagados en efectivo/transferencia.
 */
class GastoController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q'     => ['nullable', 'string', 'max:100'],
        ]);

        $consulta = Asiento::query()
            ->where('compania_id', $companiaId)
            ->where('modulo_origen', 'GASTO')
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($filtros['desde'] ?? null) {
            $consulta->whereDate('fecha', '>=', $filtros['desde']);
        }
        if ($filtros['hasta'] ?? null) {
            $consulta->whereDate('fecha', '<=', $filtros['hasta']);
        }
        if ($filtros['q'] ?? null) {
            $busqueda = '%'.mb_strtolower($filtros['q']).'%';
            $consulta->whereRaw('LOWER(descripcion) LIKE ?', [$busqueda]);
        }

        $gastos = $consulta->paginate(25)->withQueryString();

        return view('admin.compras.gastos.index', compact('gastos', 'filtros'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cuentasGasto = CuentaContable::where('compania_id', $companiaId)
            ->where('activa', true)
            ->where('permite_movimiento', true)
            ->whereHas('tipoCuenta', fn ($q) => $q->whereIn('codigo', ['GASTO', 'COSTO']))
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $cuentasPago = CuentaContable::where('compania_id', $companiaId)
            ->where('activa', true)
            ->where('permite_movimiento', true)
            ->whereHas('tipoCuenta', fn ($q) => $q->where('codigo', 'ACTIVO'))
            ->whereRaw("LEFT(codigo,2) = '11'") // Cuentas de efectivo/banco
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $cuentaGastoId = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');
        $cuentaPagoId  = CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
                       ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT');

        return view('admin.compras.gastos.create', compact(
            'cuentasGasto', 'cuentasPago', 'cuentaGastoId', 'cuentaPagoId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        abort_unless($request->user()->can('compras.gestionar'), 403);

        $data = $request->validate([
            'fecha'          => ['required', 'date'],
            'descripcion'    => ['required', 'string', 'max:500'],
            'cuenta_gasto_id'=> ['required', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'cuenta_pago_id' => ['required', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'monto'          => ['required', 'numeric', 'gt:0'],
            'referencia'     => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['cuenta_gasto_id'] === $data['cuenta_pago_id']) {
            throw ValidationException::withMessages(['cuenta_pago_id' => 'La cuenta de gasto y la cuenta de pago no pueden ser la misma.']);
        }

        $usuario = $request->user();
        $monto = round((float) $data['monto'], 2);

        $asiento = DB::transaction(function () use ($companiaId, $data, $monto, $usuario) {
            return app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                $data['descripcion'],
                $data['referencia'] ?? null,
                [
                    ['cuenta_id' => (int) $data['cuenta_gasto_id'], 'descripcion' => $data['descripcion'], 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => (int) $data['cuenta_pago_id'],  'descripcion' => $data['descripcion'], 'debito' => 0, 'credito' => $monto],
                ],
                'GASTO',
                null,
                null,
                $usuario
            );
        });

        return redirect()->route('admin.compras.gastos.index')
            ->with('status', "Gasto {$asiento->numero} registrado por B/. ".number_format($monto, 2).'.');
    }

    private function companiaActivaId(Request $request): int
    {
        $companiaId = session('compania_activa_id');
        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', $companiaId),
            403
        );

        return (int) $companiaId;
    }
}
