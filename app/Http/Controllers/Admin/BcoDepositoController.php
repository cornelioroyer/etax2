<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BcoCuenta;
use App\Models\BcoDeposito;
use App\Models\BcoMovimiento;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BcoDepositoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $desde  = $request->query('desde', now()->startOfMonth()->toDateString());
        $hasta  = $request->query('hasta', now()->toDateString());
        $cuentaId = $request->query('cuenta_id');

        $depositos = BcoDeposito::with('cuentaBancaria')
            ->where('compania_id', $companiaId)
            ->when($cuentaId, fn ($q) => $q->where('cuenta_bancaria_id', $cuentaId))
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')
            ->paginate(20)->withQueryString();

        $cuentas = BcoCuenta::where('compania_id', $companiaId)->where('activa', true)->orderBy('nombre')->get();

        return view('admin.bco.depositos.index', compact('depositos', 'cuentas', 'desde', 'hasta', 'cuentaId'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $cuentas = BcoCuenta::where('compania_id', $companiaId)->where('activa', true)->orderBy('nombre')->get();

        return view('admin.bco.depositos.create', compact('cuentas'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('bancos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cuenta_bancaria_id' => ['required', 'integer', 'exists:bco_cuentas,id'],
            'fecha'              => ['required', 'date'],
            'referencia'         => ['nullable', 'string', 'max:100'],
            'monto'              => ['required', 'numeric', 'min:0.01'],
            'cuenta_origen_id'   => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        $cuenta = BcoCuenta::where('compania_id', $companiaId)->findOrFail($data['cuenta_bancaria_id']);
        $usuario = $request->user();

        \DB::transaction(function () use ($data, $companiaId, $cuenta, $usuario) {
            $deposito = BcoDeposito::create([
                'compania_id'        => $companiaId,
                'cuenta_bancaria_id' => $cuenta->id,
                'fecha'              => $data['fecha'],
                'referencia'         => $data['referencia'] ?? null,
                'monto'              => $data['monto'],
                'created_by'         => $usuario->email,
            ]);

            // Crear movimiento bancario (crédito = ingreso a la cuenta)
            BcoMovimiento::create([
                'compania_id'        => $companiaId,
                'cuenta_bancaria_id' => $cuenta->id,
                'fecha'              => $data['fecha'],
                'tipo_movimiento'    => BcoMovimiento::TIPO_DEPOSITO,
                'descripcion'        => 'Depósito' . (! empty($data['referencia']) ? ' – ' . $data['referencia'] : ''),
                'referencia'         => $data['referencia'] ?? null,
                'debito'             => 0,
                'credito'            => $data['monto'],
                'conciliado'         => false,
                'created_by'         => $usuario->email,
            ]);

            // Asiento contable: DR banco / CR cuenta origen (ej. Caja, Efectivo en tránsito)
            if ($cuenta->cuenta_contable_id && ! empty($data['cuenta_origen_id'])) {
                $descripcion = "Depósito {$cuenta->nombre}" . (! empty($data['referencia']) ? " Ref:{$data['referencia']}" : '');
                $asiento = app(AsientoAutomatico::class)->postear(
                    $companiaId,
                    $data['fecha'],
                    $descripcion,
                    $data['referencia'] ?? "DEP-{$deposito->id}",
                    [
                        ['cuenta_id' => $cuenta->cuenta_contable_id, 'descripcion' => $descripcion, 'debito' => $data['monto'], 'credito' => 0],
                        ['cuenta_id' => (int) $data['cuenta_origen_id'], 'descripcion' => $descripcion, 'debito' => 0, 'credito' => $data['monto']],
                    ],
                    'BANCOS',
                    'bco_depositos',
                    $deposito->id,
                    $usuario,
                );

                $deposito->update(['asiento_id' => $asiento->id]);
            }
        });

        return redirect()->route('admin.bco.depositos.index')->with('status', 'Depósito registrado.');
    }

    public function show(Request $request, BcoDeposito $deposito): View
    {
        abort_unless($deposito->compania_id === $this->companiaActivaId($request), 404);
        $deposito->load('cuentaBancaria', 'asiento');

        return view('admin.bco.depositos.show', compact('deposito'));
    }
}
