<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BcoCuenta;
use App\Models\BcoMovimiento;
use App\Models\BcoTransferencia;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BcoTransferenciaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'cuenta_id' => ['nullable', 'integer'],
            'desde'     => ['nullable', 'date'],
            'hasta'     => ['nullable', 'date'],
        ]);

        $transferencias = BcoTransferencia::with('cuentaOrigen.banco', 'cuentaDestino.banco')
            ->where('compania_id', $companiaId)
            ->when($filtros['cuenta_id'] ?? null, fn ($q, $v) => $q->where(
                fn ($q) => $q->where('cuenta_origen_id', $v)->orWhere('cuenta_destino_id', $v)
            ))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $cuentas = BcoCuenta::where('compania_id', $companiaId)
            ->where('activa', true)
            ->with('banco')
            ->orderBy('nombre')
            ->get();

        return view('admin.bco.transferencias.index', compact('transferencias', 'filtros', 'cuentas'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cuentas = BcoCuenta::where('compania_id', $companiaId)
            ->where('activa', true)
            ->with('banco')
            ->orderBy('nombre')
            ->get();

        return view('admin.bco.transferencias.create', compact('cuentas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cuenta_origen_id'  => ['required', 'integer', 'exists:bco_cuentas,id'],
            'cuenta_destino_id' => ['required', 'integer', 'exists:bco_cuentas,id', 'different:cuenta_origen_id'],
            'fecha'             => ['required', 'date'],
            'monto'             => ['required', 'numeric', 'min:0.01'],
            'referencia'        => ['nullable', 'string', 'max:100'],
        ]);

        $monto = round((float) $data['monto'], 2);

        $cuentaOrigen  = BcoCuenta::where('compania_id', $companiaId)->findOrFail($data['cuenta_origen_id']);
        $cuentaDestino = BcoCuenta::where('compania_id', $companiaId)->findOrFail($data['cuenta_destino_id']);

        $transferencia = DB::transaction(function () use ($companiaId, $data, $monto, $cuentaOrigen, $cuentaDestino, $usuario) {
            $transferencia = BcoTransferencia::create([
                'compania_id'       => $companiaId,
                'cuenta_origen_id'  => $cuentaOrigen->id,
                'cuenta_destino_id' => $cuentaDestino->id,
                'fecha'             => $data['fecha'],
                'monto'             => $monto,
                'referencia'        => $data['referencia'] ?? null,
                'estado'            => BcoTransferencia::ESTADO_APLICADA,
                'created_by'        => $usuario->email,
                'updated_by'        => $usuario->email,
            ]);

            $desc = "Transferencia {$cuentaOrigen->nombre} → {$cuentaDestino->nombre}";

            // Crear movimientos en ambas cuentas
            BcoMovimiento::create([
                'compania_id'       => $companiaId,
                'cuenta_bancaria_id' => $cuentaOrigen->id,
                'fecha'             => $data['fecha'],
                'tipo_movimiento'   => BcoMovimiento::TIPO_TRANSFERENCIA,
                'descripcion'       => $desc,
                'referencia'        => $data['referencia'] ?? null,
                'debito'            => $monto,
                'credito'           => 0,
                'documento_origen'  => 'bco_transferencias',
                'documento_id'      => $transferencia->id,
                'created_by'        => $usuario->email,
                'updated_by'        => $usuario->email,
            ]);

            BcoMovimiento::create([
                'compania_id'       => $companiaId,
                'cuenta_bancaria_id' => $cuentaDestino->id,
                'fecha'             => $data['fecha'],
                'tipo_movimiento'   => BcoMovimiento::TIPO_TRANSFERENCIA,
                'descripcion'       => $desc,
                'referencia'        => $data['referencia'] ?? null,
                'debito'            => 0,
                'credito'           => $monto,
                'documento_origen'  => 'bco_transferencias',
                'documento_id'      => $transferencia->id,
                'created_by'        => $usuario->email,
                'updated_by'        => $usuario->email,
            ]);

            // Asiento si ambas cuentas tienen cuenta contable mapeada
            if ($cuentaOrigen->cuenta_contable_id && $cuentaDestino->cuenta_contable_id) {
                $asiento = app(AsientoAutomatico::class)->postear(
                    $companiaId,
                    $data['fecha'],
                    $desc,
                    $data['referencia'] ?? "TRF-{$transferencia->id}",
                    [
                        ['cuenta_id' => $cuentaDestino->cuenta_contable_id, 'descripcion' => $desc, 'debito' => $monto, 'credito' => 0],
                        ['cuenta_id' => $cuentaOrigen->cuenta_contable_id,  'descripcion' => $desc, 'debito' => 0, 'credito' => $monto],
                    ],
                    'BANCOS',
                    'bco_transferencias',
                    $transferencia->id,
                    $usuario,
                );

                $transferencia->update(['asiento_id' => $asiento->id]);
            }

            return $transferencia;
        });

        return redirect()->route('admin.bco.transferencias.show', $transferencia)
            ->with('status', 'Transferencia registrada por B/. ' . number_format($monto, 2) . '.');
    }

    public function show(Request $request, BcoTransferencia $transferencia): View
    {
        abort_unless($transferencia->compania_id === $this->companiaActivaId($request), 404);

        $transferencia->load('cuentaOrigen.banco', 'cuentaDestino.banco', 'asiento');

        return view('admin.bco.transferencias.show', compact('transferencia'));
    }
}
