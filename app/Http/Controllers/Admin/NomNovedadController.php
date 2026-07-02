<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\NomConcepto;
use App\Models\NomEmpleado;
use App\Models\NomNovedad;
use App\Models\NomPeriodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Novedades: ingresos/deducciones por empleado que alimentan la corrida
 * (horas del período para empleados por hora, horas extra, comisiones,
 * cuotas de préstamo...). FIJA = cada período; VARIABLE = un período puntual.
 */
class NomNovedadController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $query = NomNovedad::where('compania_id', $companiaId)
            ->with(['empleado:id,codigo,nombre,apellido', 'concepto:id,codigo,descripcion,tipo', 'periodo']);

        if ($empleadoId = $request->query('empleado_id')) {
            $query->where('empleado_id', $empleadoId);
        }

        $items = $query->orderByDesc('id')->paginate(50)->withQueryString();

        $empleados = NomEmpleado::where('compania_id', $companiaId)
            ->whereIn('status', [NomEmpleado::STATUS_ACTIVO, NomEmpleado::STATUS_VACACIONES])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'apellido', 'tipo_salario']);

        $conceptos = NomConcepto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->where(function ($q) {
                $q->where('calculo', NomConcepto::CALCULO_MANUAL)
                    ->orWhere('codigo', NomConcepto::COD_SALARIO); // horas del período (por hora)
            })
            ->where('tipo', '!=', NomConcepto::TIPO_PATRONAL)
            ->orderBy('orden_impresion')
            ->get(['id', 'codigo', 'descripcion', 'tipo', 'calculo']);

        $periodos = NomPeriodo::where('compania_id', $companiaId)
            ->where('estado', NomPeriodo::ESTADO_ABIERTO)
            ->orderByDesc('anio')
            ->orderByDesc('numero')
            ->limit(40)
            ->get();

        return view('admin.nomina.novedades.index', compact('items', 'empleados', 'conceptos', 'periodos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'empleado_id' => ['required', Rule::exists('nom_empleados', 'id')->where('compania_id', $companiaId)],
            'concepto_id' => ['required', Rule::exists('nom_conceptos', 'id')->where('compania_id', $companiaId)],
            'tipo_registro' => ['required', Rule::in([NomNovedad::TIPO_FIJA, NomNovedad::TIPO_VARIABLE])],
            'periodo_id' => ['required_if:tipo_registro,VARIABLE', 'nullable', Rule::exists('nom_periodos', 'id')->where('compania_id', $companiaId)],
            'cantidad' => ['nullable', 'numeric', 'min:0'],
            'monto' => ['nullable', 'numeric', 'min:0'],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ]);

        $concepto = NomConcepto::findOrFail($data['concepto_id']);

        // Horas del período (concepto 03, empleados por hora): requiere cantidad.
        // Todo lo demás requiere monto.
        if ($concepto->codigo === NomConcepto::COD_SALARIO) {
            if (($data['cantidad'] ?? 0) <= 0) {
                return back()->withErrors(['cantidad' => 'Las horas del período deben ser mayores a cero.'])->withInput();
            }
            if ($data['tipo_registro'] !== NomNovedad::TIPO_VARIABLE) {
                return back()->withErrors(['tipo_registro' => 'Las horas del período se registran como novedad VARIABLE del período.'])->withInput();
            }
        } elseif (($data['monto'] ?? 0) <= 0) {
            return back()->withErrors(['monto' => 'El monto debe ser mayor a cero.'])->withInput();
        }

        NomNovedad::create(array_merge($data, [
            'compania_id' => $companiaId,
            'monto' => $data['monto'] ?? 0,
            'activo' => true,
            'created_by' => $request->user()->email,
        ]));

        return back()->with('status', 'Novedad registrada.');
    }

    public function toggle(Request $request, NomNovedad $novedad): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($novedad->compania_id === $this->companiaActivaId($request), 404);

        $novedad->update([
            'activo' => ! $novedad->activo,
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', $novedad->activo ? 'Novedad reactivada.' : 'Novedad desactivada.');
    }

    public function destroy(Request $request, NomNovedad $novedad): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($novedad->compania_id === $this->companiaActivaId($request), 404);

        $novedad->delete();

        return back()->with('status', 'Novedad eliminada.');
    }
}
