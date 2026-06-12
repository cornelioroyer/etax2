<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use App\Models\TipoCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CuentaContableController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cuentas = CuentaContable::query()
            ->with('tipo')
            ->where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        $plantillas = $cuentas->isEmpty()
            ? DB::table('core_plantillas_cuentas')->where('activa', true)->orderBy('codigo')->get()
            : collect();

        return view('admin.cuentas.index', compact('cuentas', 'plantillas'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        return view('admin.cuentas.create', $this->datosFormulario($request));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);
        $data = $this->validated($request, $companiaId);
        $data['compania_id'] = $companiaId;
        $data['created_by'] = $request->user()->email;

        CuentaContable::create($data);

        return redirect()->route('admin.cuentas.index')->with('status', 'Cuenta creada.');
    }

    public function edit(Request $request, CuentaContable $cuenta): View
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $cuenta);

        return view('admin.cuentas.edit', ['cuenta' => $cuenta] + $this->datosFormulario($request, $cuenta));
    }

    public function update(Request $request, CuentaContable $cuenta): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $cuenta);

        $data = $this->validated($request, $cuenta->compania_id, $cuenta);
        $data['updated_by'] = $request->user()->email;

        $cuenta->update($data);

        return redirect()->route('admin.cuentas.index')->with('status', 'Cuenta actualizada.');
    }

    public function destroy(Request $request, CuentaContable $cuenta): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.eliminar'), 403);
        $this->verificarCompania($request, $cuenta);

        if ($cuenta->hijos()->exists()) {
            return back()->withErrors(['cuenta' => 'No se puede eliminar: la cuenta tiene subcuentas.']);
        }

        if (DB::table('cgl_asientos_detalle')->where('cuenta_id', $cuenta->id)->exists()) {
            return back()->withErrors(['cuenta' => 'No se puede eliminar: la cuenta tiene movimientos. Desactívala en su lugar.']);
        }

        $cuenta->delete();

        return redirect()->route('admin.cuentas.index')->with('status', 'Cuenta eliminada.');
    }

    /**
     * Copia la plantilla PA_BASICO a la compañía activa y configura
     * las cuentas por defecto (core_cuentas_default).
     */
    public function aplicarPlantilla(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);

        if (CuentaContable::where('compania_id', $companiaId)->exists()) {
            return back()->withErrors(['plantilla' => 'La compañía ya tiene plan de cuentas; la plantilla solo aplica sobre un plan vacío.']);
        }

        $codigo = $request->validate([
            'plantilla' => ['required', 'string', Rule::exists('core_plantillas_cuentas', 'codigo')],
        ])['plantilla'];

        $plantillaId = DB::table('core_plantillas_cuentas')->where('codigo', $codigo)->value('id');

        abort_if(! $plantillaId, 404, 'Plantilla no encontrada.');

        $detalle = DB::table('core_plantillas_cuentas_detalle')
            ->where('plantilla_id', $plantillaId)
            ->orderBy('codigo')
            ->get();

        $tipos = TipoCuenta::pluck('id', 'codigo');
        $usuario = $request->user()->email;

        DB::transaction(function () use ($detalle, $tipos, $companiaId, $usuario) {
            $idsPorCodigo = [];

            foreach ($detalle as $fila) {
                $cuenta = CuentaContable::create([
                    'compania_id' => $companiaId,
                    'codigo' => $fila->codigo,
                    'nombre' => $fila->nombre,
                    'cuenta_padre_id' => $fila->codigo_padre ? ($idsPorCodigo[$fila->codigo_padre] ?? null) : null,
                    'nivel' => $fila->nivel,
                    'tipo_cuenta_id' => $tipos[$fila->tipo_cuenta_codigo] ?? null,
                    'naturaleza' => $fila->naturaleza,
                    'permite_movimiento' => $fila->permite_movimiento,
                    'conciliable' => $fila->conciliable,
                    'activa' => true,
                    'renglon_isr' => $fila->renglon_isr ?? null,
                    'created_by' => $usuario,
                ]);

                $idsPorCodigo[$fila->codigo] = $cuenta->id;

                if ($fila->clave_default) {
                    DB::table('core_cuentas_default')->insert([
                        'compania_id' => $companiaId,
                        'clave' => $fila->clave_default,
                        'cuenta_id' => $cuenta->id,
                        'descripcion' => $fila->nombre,
                        'created_by' => $usuario,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return redirect()->route('admin.cuentas.index')
            ->with('status', "Plantilla aplicada: {$detalle->count()} cuentas creadas.");
    }

    private function datosFormulario(Request $request, ?CuentaContable $excluir = null): array
    {
        $companiaId = $this->companiaActivaId($request);

        $padres = CuentaContable::where('compania_id', $companiaId)
            ->when($excluir, fn ($q) => $q->where('id', '!=', $excluir->id))
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'nivel']);

        return [
            'tipos' => TipoCuenta::orderBy('id')->get(),
            'padres' => $padres,
        ];
    }

    private function validated(Request $request, int $companiaId, ?CuentaContable $cuenta = null): array
    {
        $data = $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('cgl_cuentas')->where('compania_id', $companiaId)->ignore($cuenta?->id),
            ],
            'nombre' => ['required', 'string', 'max:200'],
            'cuenta_padre_id' => [
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'tipo_cuenta_id' => ['required', 'integer', 'exists:cgl_tipos_cuenta,id'],
            'naturaleza' => ['required', Rule::in(['DEBITO', 'CREDITO'])],
            'permite_movimiento' => ['required', 'boolean'],
            'conciliable' => ['required', 'boolean'],
            'activa' => ['required', 'boolean'],
        ]);

        $padre = $data['cuenta_padre_id'] ? CuentaContable::find($data['cuenta_padre_id']) : null;
        $data['nivel'] = $padre ? $padre->nivel + 1 : 1;

        return $data;
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

    private function verificarCompania(Request $request, CuentaContable $cuenta): void
    {
        abort_unless($cuenta->compania_id === $this->companiaActivaId($request), 404);
    }
}
