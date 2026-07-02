<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use App\Models\NomConcepto;
use App\Services\NomCatalogoDefault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NomConceptoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $items = NomConcepto::where('compania_id', $companiaId)
            ->with(['cuentaGasto:id,codigo,nombre', 'cuentaPasivo:id,codigo,nombre'])
            ->orderBy('orden_impresion')
            ->orderBy('codigo')
            ->get();

        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.nomina.conceptos.index', compact('items', 'cuentas'));
    }

    /** Siembra los conceptos default (misma numeración que planilla legacy). */
    public function aplicarCatalogo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        NomCatalogoDefault::aplicarParametrosLegales();
        $creados = NomCatalogoDefault::aplicar($companiaId, $request->user()->email);

        return back()->with('status', $creados > 0
            ? "Catálogo aplicado: $creados conceptos creados."
            : 'El catálogo ya estaba aplicado; no se duplicó nada.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:10'],
            'descripcion' => ['required', 'string', 'max:200'],
            'tipo' => ['required', Rule::in([NomConcepto::TIPO_INGRESO, NomConcepto::TIPO_DEDUCCION])],
            'gravable_css' => ['boolean'],
            'gravable_isr' => ['boolean'],
        ]);

        $codigo = strtoupper(trim($data['codigo']));

        if (NomConcepto::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe un concepto con ese código.'])->withInput();
        }

        NomConcepto::create([
            'compania_id' => $companiaId,
            'codigo' => $codigo,
            'descripcion' => $data['descripcion'],
            'tipo' => $data['tipo'],
            'calculo' => NomConcepto::CALCULO_MANUAL,
            'gravable_css' => $data['tipo'] === NomConcepto::TIPO_INGRESO && $request->boolean('gravable_css'),
            'gravable_isr' => $data['tipo'] === NomConcepto::TIPO_INGRESO && $request->boolean('gravable_isr'),
            'acumula_xiii' => $data['tipo'] === NomConcepto::TIPO_INGRESO,
            'acumula_vacaciones' => $data['tipo'] === NomConcepto::TIPO_INGRESO,
            'orden_impresion' => $data['tipo'] === NomConcepto::TIPO_INGRESO ? 60 : 150,
            'de_sistema' => false,
            'activo' => true,
            'created_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Concepto creado.');
    }

    public function update(Request $request, NomConcepto $concepto): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($concepto->compania_id === $companiaId, 404);

        $data = $request->validate([
            'descripcion' => ['required', 'string', 'max:200'],
            'cuenta_gasto_id' => ['nullable', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'cuenta_pasivo_id' => ['nullable', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'gravable_css' => ['boolean'],
            'gravable_isr' => ['boolean'],
            'activo' => ['boolean'],
        ]);

        // Los conceptos del motor no cambian su naturaleza, solo cuentas/descripción
        if ($concepto->de_sistema) {
            unset($data['gravable_css'], $data['gravable_isr']);
        } else {
            $data['gravable_css'] = $concepto->esIngreso() && $request->boolean('gravable_css');
            $data['gravable_isr'] = $concepto->esIngreso() && $request->boolean('gravable_isr');
        }

        $concepto->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Concepto actualizado.');
    }

    public function destroy(Request $request, NomConcepto $concepto): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($concepto->compania_id === $this->companiaActivaId($request), 404);

        if ($concepto->de_sistema) {
            return back()->withErrors(['concepto' => 'Los conceptos del sistema no se eliminan; puedes inactivarlos.']);
        }

        if ($concepto->movimientos()->exists() || $concepto->novedades()->exists()) {
            return back()->withErrors(['concepto' => 'No se puede eliminar: tiene movimientos de planilla. Inactívalo.']);
        }

        $concepto->delete();

        return back()->with('status', 'Concepto eliminado.');
    }
}
