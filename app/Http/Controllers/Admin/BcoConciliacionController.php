<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BcoConciliacion;
use App\Models\BcoConciliacionDetalle;
use App\Models\BcoCuenta;
use App\Models\BcoMovimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BcoConciliacionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $conciliaciones = BcoConciliacion::with('cuentaBancaria.banco')
            ->where('compania_id', $companiaId)
            ->orderByDesc('fecha_corte')
            ->paginate(20);

        $cuentas = BcoCuenta::where('compania_id', $companiaId)
            ->where('activa', true)
            ->with('banco')
            ->orderBy('nombre')
            ->get();

        return view('admin.bco.conciliaciones.index', compact('conciliaciones', 'cuentas'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cuentas = BcoCuenta::where('compania_id', $companiaId)
            ->where('activa', true)
            ->with('banco', 'cuentaContable')
            ->orderBy('nombre')
            ->get();

        return view('admin.bco.conciliaciones.create', compact('cuentas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cuenta_bancaria_id' => ['required', 'integer', 'exists:bco_cuentas,id'],
            'fecha_corte'        => ['required', 'date'],
            'saldo_banco'        => ['required', 'numeric'],
        ]);

        $cuenta = BcoCuenta::where('compania_id', $companiaId)->findOrFail($data['cuenta_bancaria_id']);

        // Verificar que no exista conciliación abierta para esta cuenta y fecha
        $existe = BcoConciliacion::where('compania_id', $companiaId)
            ->where('cuenta_bancaria_id', $cuenta->id)
            ->where('fecha_corte', $data['fecha_corte'])
            ->whereIn('estado', [BcoConciliacion::ESTADO_ABIERTA])
            ->exists();

        if ($existe) {
            return back()->withErrors(['fecha_corte' => 'Ya existe una conciliación abierta para esta cuenta y fecha.'])->withInput();
        }

        // Calcular saldo en libros: movimientos hasta la fecha de corte.
        // saldoDesdeNeto aplica el signo correcto (activo banco vs pasivo tarjeta).
        $neto = (float) BcoMovimiento::where('cuenta_bancaria_id', $cuenta->id)
            ->whereDate('fecha', '<=', $data['fecha_corte'])
            ->selectRaw('COALESCE(SUM(credito),0) - COALESCE(SUM(debito),0) as neto')
            ->value('neto');

        $saldoLibros = $cuenta->saldoDesdeNeto($neto);
        $saldoBanco  = round((float) $data['saldo_banco'], 2);

        $conciliacion = BcoConciliacion::create([
            'compania_id'        => $companiaId,
            'cuenta_bancaria_id' => $cuenta->id,
            'cuenta_contable_id' => $cuenta->cuenta_contable_id,
            'fecha_corte'        => $data['fecha_corte'],
            'saldo_banco'        => $saldoBanco,
            'saldo_libros'       => $saldoLibros,
            'diferencia'         => round($saldoBanco - $saldoLibros, 2),
            'estado'             => BcoConciliacion::ESTADO_ABIERTA,
            'usuario_id'         => $usuario->id,
            'created_by'         => $usuario->email,
            'updated_by'         => $usuario->email,
        ]);

        return redirect()->route('admin.bco.conciliaciones.show', $conciliacion)
            ->with('status', 'Conciliación iniciada. Marca los movimientos conciliados.');
    }

    public function show(Request $request, BcoConciliacion $conciliacion): View
    {
        abort_unless($conciliacion->compania_id === $this->companiaActivaId($request), 404);

        $conciliacion->load('cuentaBancaria.banco', 'detalle.movimiento');

        // Movimientos no conciliados de la cuenta hasta la fecha de corte
        $movimientosNoConciliados = BcoMovimiento::where('cuenta_bancaria_id', $conciliacion->cuenta_bancaria_id)
            ->whereDate('fecha', '<=', $conciliacion->fecha_corte)
            ->where('conciliado', false)
            ->orderBy('fecha')
            ->get();

        // IDs ya marcados en esta conciliación
        $conciliadosIds = $conciliacion->detalle->pluck('movimiento_id')->toArray();

        return view('admin.bco.conciliaciones.show', compact('conciliacion', 'movimientosNoConciliados', 'conciliadosIds'));
    }

    public function marcar(Request $request, BcoConciliacion $conciliacion): RedirectResponse
    {
        abort_unless($conciliacion->compania_id === $this->companiaActivaId($request), 404);
        abort_if($conciliacion->esCerrada(), 403, 'La conciliación ya está cerrada.');

        $data = $request->validate([
            'movimiento_ids'   => ['nullable', 'array'],
            'movimiento_ids.*' => ['integer'],
        ]);

        $idsSeleccionados = collect($data['movimiento_ids'] ?? []);
        $usuario = $request->user();

        DB::transaction(function () use ($conciliacion, $idsSeleccionados, $usuario) {
            // Eliminar marcas existentes y crear las nuevas
            $conciliacion->detalle()->delete();

            foreach ($idsSeleccionados as $movId) {
                BcoConciliacionDetalle::create([
                    'conciliacion_id' => $conciliacion->id,
                    'movimiento_id'   => $movId,
                    'conciliado'      => true,
                    'created_by'      => $usuario->email,
                    'updated_by'      => $usuario->email,
                ]);
            }

            // Actualizar campo conciliado en movimientos
            BcoMovimiento::where('cuenta_bancaria_id', $conciliacion->cuenta_bancaria_id)
                ->whereDate('fecha', '<=', $conciliacion->fecha_corte)
                ->update(['conciliado' => false]);

            if ($idsSeleccionados->isNotEmpty()) {
                BcoMovimiento::whereIn('id', $idsSeleccionados)->update(['conciliado' => true]);
            }

            // Recalcular diferencia
            $saldoConciliado = BcoMovimiento::whereIn('id', $idsSeleccionados)
                ->selectRaw('COALESCE(SUM(credito),0) - COALESCE(SUM(debito),0) as neto')
                ->value('neto');

            $cuenta      = $conciliacion->cuentaBancaria;
            $saldoLibros = $cuenta->saldoDesdeNeto((float) $saldoConciliado);

            $conciliacion->update([
                'saldo_libros' => $saldoLibros,
                'diferencia'   => round((float) $conciliacion->saldo_banco - $saldoLibros, 2),
                'updated_by'   => $usuario->email,
            ]);
        });

        return back()->with('status', 'Movimientos actualizados.');
    }

    public function cerrar(Request $request, BcoConciliacion $conciliacion): RedirectResponse
    {
        abort_unless($conciliacion->compania_id === $this->companiaActivaId($request), 404);
        abort_if($conciliacion->esCerrada(), 403, 'Ya está cerrada.');

        $conciliacion->update([
            'estado'     => BcoConciliacion::ESTADO_CERRADA,
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.bco.conciliaciones.show', $conciliacion)
            ->with('status', 'Conciliación cerrada.');
    }
}
