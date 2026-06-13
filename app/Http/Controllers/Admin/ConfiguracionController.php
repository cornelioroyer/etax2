<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\CoreCentroCosto;
use App\Models\CoreDepartamento;
use App\Models\CoreMoneda;
use App\Models\CoreProyecto;
use App\Models\CoreSucursal;
use App\Models\CoreTasaCambio;
use App\Models\TaxRetencion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId  = $this->companiaActivaId($request);
        $tab         = $request->query('tab', 'sucursales');

        $sucursales     = CoreSucursal::where('compania_id', $companiaId)->orderBy('codigo')->get();
        $departamentos  = CoreDepartamento::where('compania_id', $companiaId)->orderBy('codigo')->get();
        $centrosCostos  = CoreCentroCosto::where('compania_id', $companiaId)->orderBy('codigo')->get();
        $proyectos      = CoreProyecto::where('compania_id', $companiaId)->orderBy('codigo')->get();
        $monedas        = CoreMoneda::where('compania_id', $companiaId)->orderBy('codigo')->with('tasas')->get();
        $retenciones    = TaxRetencion::where('compania_id', $companiaId)->orderBy('codigo')->get();

        return view('admin.configuracion.index', compact(
            'sucursales', 'departamentos', 'centrosCostos', 'proyectos',
            'monedas', 'retenciones', 'tab'
        ));
    }

    // ── Sucursales ──────────────────────────────────────────────────────────

    public function storeSucursal(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'    => ['required', 'string', 'max:30',
                Rule::unique('core_sucursales')->where('compania_id', $companiaId)],
            'nombre'    => ['required', 'string', 'max:150'],
            'direccion' => ['nullable', 'string'],
            'telefono'  => ['nullable', 'string', 'max:50'],
        ]);

        CoreSucursal::create(['compania_id' => $companiaId, ...$data,
            'activa' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'sucursales'])->with('status', 'Sucursal creada.');
    }

    public function updateSucursal(Request $request, CoreSucursal $sucursal): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        abort_unless($sucursal->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre'    => ['required', 'string', 'max:150'],
            'direccion' => ['nullable', 'string'],
            'telefono'  => ['nullable', 'string', 'max:50'],
        ]);

        $sucursal->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'sucursales'])->with('status', 'Sucursal actualizada.');
    }

    public function toggleSucursal(Request $request, CoreSucursal $sucursal): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        abort_unless($sucursal->compania_id === $this->companiaActivaId($request), 404);

        $sucursal->update(['activa' => ! $sucursal->activa, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'sucursales'])
            ->with('status', "Sucursal {$sucursal->codigo} " . ($sucursal->activa ? 'activada' : 'desactivada') . '.');
    }

    // ── Departamentos ────────────────────────────────────────────────────────

    public function storeDepartamento(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30',
                Rule::unique('core_departamentos')->where('compania_id', $companiaId)],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        CoreDepartamento::create(['compania_id' => $companiaId, ...$data,
            'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'departamentos'])->with('status', 'Departamento creado.');
    }

    public function updateDepartamento(Request $request, CoreDepartamento $departamento): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        abort_unless($departamento->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate(['nombre' => ['required', 'string', 'max:150']]);
        $departamento->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'departamentos'])->with('status', 'Departamento actualizado.');
    }

    // ── Centros de Costo ────────────────────────────────────────────────────

    public function storeCentroCosto(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30',
                Rule::unique('core_centros_costos')->where('compania_id', $companiaId)],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        CoreCentroCosto::create(['compania_id' => $companiaId, ...$data,
            'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'centros-costo'])->with('status', 'Centro de costo creado.');
    }

    public function updateCentroCosto(Request $request, CoreCentroCosto $centroCosto): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        abort_unless($centroCosto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate(['nombre' => ['required', 'string', 'max:150']]);
        $centroCosto->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'centros-costo'])->with('status', 'Centro de costo actualizado.');
    }

    // ── Proyectos ────────────────────────────────────────────────────────────

    public function storeProyecto(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'       => ['required', 'string', 'max:30',
                Rule::unique('core_proyectos')->where('compania_id', $companiaId)],
            'nombre'       => ['required', 'string', 'max:150'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        CoreProyecto::create(['compania_id' => $companiaId, ...$data,
            'estado' => CoreProyecto::ESTADO_ACTIVO, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'proyectos'])->with('status', 'Proyecto creado.');
    }

    public function updateProyecto(Request $request, CoreProyecto $proyecto): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        abort_unless($proyecto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre'    => ['required', 'string', 'max:150'],
            'estado'    => ['required', Rule::in([CoreProyecto::ESTADO_ACTIVO, CoreProyecto::ESTADO_COMPLETADO, CoreProyecto::ESTADO_SUSPENDIDO])],
            'fecha_fin' => ['nullable', 'date'],
        ]);
        $proyecto->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'proyectos'])->with('status', 'Proyecto actualizado.');
    }

    // ── Monedas ──────────────────────────────────────────────────────────────

    public function storeMoneda(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'  => ['required', 'string', 'max:10',
                Rule::unique('core_monedas')->where('compania_id', $companiaId)],
            'nombre'  => ['required', 'string', 'max:100'],
            'simbolo' => ['nullable', 'string', 'max:5'],
        ]);

        CoreMoneda::create(['compania_id' => $companiaId, ...$data,
            'activa' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'monedas'])->with('status', 'Moneda creada.');
    }

    public function storeTasa(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'moneda_id' => ['required', 'integer', 'exists:core_monedas,id'],
            'fecha'     => ['required', 'date'],
            'tasa'      => ['required', 'numeric', 'min:0.000001'],
        ]);

        $moneda = CoreMoneda::where('compania_id', $companiaId)->findOrFail($data['moneda_id']);

        CoreTasaCambio::create([...$data, 'moneda_id' => $moneda->id, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'monedas'])->with('status', 'Tasa registrada.');
    }

    // ── Retenciones ──────────────────────────────────────────────────────────

    public function storeRetencion(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'     => ['required', 'string', 'max:30',
                Rule::unique('tax_retenciones')->where('compania_id', $companiaId)],
            'nombre'     => ['required', 'string', 'max:150'],
            'tipo'       => ['required', Rule::in(['ITBMS', 'ISR', 'OTRO'])],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'cuenta_id'  => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        TaxRetencion::create(['compania_id' => $companiaId, ...$data,
            'activa' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'retenciones'])->with('status', 'Retención creada.');
    }

    public function updateRetencion(Request $request, TaxRetencion $retencion): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        abort_unless($retencion->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre'     => ['required', 'string', 'max:150'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'cuenta_id'  => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);
        $retencion->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.configuracion.index', ['tab' => 'retenciones'])->with('status', 'Retención actualizada.');
    }
}
