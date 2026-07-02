<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\NomCargo;
use App\Models\NomDepartamento;
use App\Models\NomEmpleado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NomEmpleadoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $query = NomEmpleado::where('compania_id', $companiaId)
            ->with(['departamento:id,nombre', 'cargo:id,nombre']);

        if ($buscar = trim((string) $request->query('buscar'))) {
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo', 'ilike', "%$buscar%")
                    ->orWhere('nombre', 'ilike', "%$buscar%")
                    ->orWhere('apellido', 'ilike', "%$buscar%")
                    ->orWhere('cedula', 'ilike', "%$buscar%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->orderBy('codigo')->paginate(50)->withQueryString();

        return view('admin.nomina.empleados.index', compact('items'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);

        return view('admin.nomina.empleados.form', array_merge(
            $this->catalogos($request),
            ['empleado' => null],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $this->datosValidados($request, $companiaId);

        if (NomEmpleado::where('compania_id', $companiaId)->where('codigo', $data['codigo'])->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe un empleado con ese código.'])->withInput();
        }

        $empleado = NomEmpleado::create(array_merge($data, [
            'compania_id' => $companiaId,
            'created_by' => $request->user()->email,
        ]));

        return redirect()->route('admin.nomina.empleados.edit', $empleado)
            ->with('status', 'Empleado creado.');
    }

    public function edit(Request $request, NomEmpleado $empleado): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        abort_unless($empleado->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.nomina.empleados.form', array_merge(
            $this->catalogos($request),
            ['empleado' => $empleado],
        ));
    }

    public function update(Request $request, NomEmpleado $empleado): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($empleado->compania_id === $companiaId, 404);

        $data = $this->datosValidados($request, $companiaId);

        $duplicado = NomEmpleado::where('compania_id', $companiaId)
            ->where('codigo', $data['codigo'])
            ->where('id', '!=', $empleado->id)
            ->exists();

        if ($duplicado) {
            return back()->withErrors(['codigo' => 'Ya existe otro empleado con ese código.'])->withInput();
        }

        $empleado->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Empleado actualizado.');
    }

    /** @return array<string, mixed> */
    private function datosValidados(Request $request, int $companiaId): array
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:150'],
            'apellido' => ['required', 'string', 'max:150'],
            'cedula' => ['nullable', 'string', 'max:30'],
            'seguro_social' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sexo' => ['nullable', Rule::in(['M', 'F'])],
            'estado_civil' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_terminacion' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'tipo_salario' => ['required', Rule::in([NomEmpleado::TIPO_SALARIO_FIJO, NomEmpleado::TIPO_SALARIO_POR_HORA])],
            'salario_mensual' => ['nullable', 'numeric', 'min:0'],
            'tasa_hora' => ['nullable', 'numeric', 'min:0'],
            'horas_semanales' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'tipo_planilla' => ['required', Rule::in(array_keys(NomEmpleado::TIPOS_PLANILLA))],
            'forma_pago' => ['required', Rule::in(array_keys(NomEmpleado::FORMAS_PAGO))],
            'banco' => ['nullable', 'string', 'max:100'],
            'cuenta_bancaria' => ['nullable', 'string', 'max:50'],
            'tipo_cuenta' => ['nullable', Rule::in(['AHORRO', 'CORRIENTE'])],
            'departamento_id' => ['nullable', Rule::exists('nom_departamentos', 'id')->where('compania_id', $companiaId)],
            'cargo_id' => ['nullable', Rule::exists('nom_cargos', 'id')->where('compania_id', $companiaId)],
            'dependientes' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(array_keys(NomEmpleado::STATUSES))],
            'observacion' => ['nullable', 'string'],
        ]);

        $data['codigo'] = strtoupper(trim($data['codigo']));
        $data['salario_mensual'] = $data['salario_mensual'] ?? 0;
        $data['tasa_hora'] = $data['tasa_hora'] ?? 0;
        $data['horas_semanales'] = $data['horas_semanales'] ?? 48;
        $data['dependientes'] = $data['dependientes'] ?? 0;

        if ($data['tipo_salario'] === NomEmpleado::TIPO_SALARIO_FIJO && $data['salario_mensual'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'salario_mensual' => 'Un empleado de salario fijo necesita salario mensual mayor a cero.',
            ]);
        }

        if ($data['tipo_salario'] === NomEmpleado::TIPO_SALARIO_POR_HORA && $data['tasa_hora'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tasa_hora' => 'Un empleado por hora necesita tasa por hora mayor a cero.',
            ]);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function catalogos(Request $request): array
    {
        $companiaId = $this->companiaActivaId($request);

        return [
            'departamentos' => NomDepartamento::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'cargos' => NomCargo::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
