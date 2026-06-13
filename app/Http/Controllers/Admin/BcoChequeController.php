<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BcoCheque;
use App\Models\BcoCuenta;
use App\Models\BcoMovimiento;
use App\Models\Contacto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BcoChequeController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $cuentaId = $request->query('cuenta_id');
        $estado   = strtoupper((string) $request->query('estado', ''));
        $desde    = $request->query('desde', now()->startOfMonth()->toDateString());
        $hasta    = $request->query('hasta', now()->toDateString());

        $cheques = BcoCheque::with(['cuentaBancaria', 'beneficiario'])
            ->where('compania_id', $companiaId)
            ->when($cuentaId, fn ($q) => $q->where('cuenta_bancaria_id', $cuentaId))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')->orderByDesc('numero_cheque')
            ->paginate(20)->withQueryString();

        $cuentas = BcoCuenta::where('compania_id', $companiaId)->where('activa', true)->orderBy('nombre')->get();
        $estados = BcoCheque::ESTADOS;

        return view('admin.bco.cheques.index', compact('cheques', 'cuentas', 'estados', 'estado', 'cuentaId', 'desde', 'hasta'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $cuentas    = BcoCuenta::where('compania_id', $companiaId)->where('activa', true)->orderBy('nombre')->get();
        $contactos  = Contacto::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();

        return view('admin.bco.cheques.create', compact('cuentas', 'contactos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('bancos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cuenta_bancaria_id' => ['required', 'integer', 'exists:bco_cuentas,id'],
            'numero_cheque'      => ['required', 'string', 'max:50'],
            'fecha'              => ['required', 'date'],
            'beneficiario_id'    => ['nullable', 'integer', 'exists:contact_contactos,id'],
            'monto'              => ['required', 'numeric', 'min:0.01'],
        ]);

        $cuenta = BcoCuenta::where('compania_id', $companiaId)->findOrFail($data['cuenta_bancaria_id']);

        \DB::transaction(function () use ($data, $companiaId, $cuenta, $request) {
            BcoCheque::create([
                'compania_id'        => $companiaId,
                'cuenta_bancaria_id' => $cuenta->id,
                'numero_cheque'      => $data['numero_cheque'],
                'fecha'              => $data['fecha'],
                'beneficiario_id'    => $data['beneficiario_id'] ?? null,
                'monto'              => $data['monto'],
                'estado'             => BcoCheque::ESTADO_EMITIDO,
                'created_by'         => $request->user()->email,
            ]);

            // Registrar el cheque como débito en movimientos bancarios
            BcoMovimiento::create([
                'compania_id'        => $companiaId,
                'cuenta_bancaria_id' => $cuenta->id,
                'fecha'              => $data['fecha'],
                'tipo_movimiento'    => BcoMovimiento::TIPO_CHEQUE,
                'descripcion'        => "Cheque #{$data['numero_cheque']}",
                'referencia'         => $data['numero_cheque'],
                'debito'             => $data['monto'],
                'credito'            => 0,
                'conciliado'         => false,
                'created_by'         => $request->user()->email,
            ]);
        });

        return redirect()->route('admin.bco.cheques.index')->with('status', "Cheque #{$data['numero_cheque']} registrado.");
    }

    public function show(Request $request, BcoCheque $cheque): View
    {
        abort_unless($cheque->compania_id === $this->companiaActivaId($request), 404);
        $cheque->load('cuentaBancaria', 'beneficiario');

        return view('admin.bco.cheques.show', compact('cheque'));
    }

    public function cambiarEstado(Request $request, BcoCheque $cheque): RedirectResponse
    {
        abort_unless($request->user()->can('bancos.gestionar'), 403);
        abort_unless($cheque->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'estado' => ['required', 'in:' . implode(',', array_keys(BcoCheque::ESTADOS))],
        ]);

        $cheque->update(['estado' => $data['estado'], 'updated_by' => $request->user()->email]);

        return back()->with('status', "Cheque #{$cheque->numero_cheque} marcado como " . BcoCheque::ESTADOS[$data['estado']] . '.');
    }
}
