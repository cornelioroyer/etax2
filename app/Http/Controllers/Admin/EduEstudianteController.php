<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\EduEstudiante;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduEstudianteController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search = trim($request->input('q', ''));

        $estudiantes = EduEstudiante::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'contacto'])
            ->when($search !== '', fn ($q) => $q->whereHas('contacto', fn ($c) => $c
                ->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('identificacion', 'ilike', "%{$search}%"))
                ->orWhere('codigo_estudiante', 'ilike', "%{$search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.estudiantes.index', compact('estudiantes', 'instituciones', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId    = $this->companiaActivaId($request);
        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $contactos     = Contacto::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.estudiantes.create', compact('instituciones', 'contactos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id'   => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'contacto_id'      => ['required', 'integer', 'exists:contactos,id'],
            'codigo_estudiante'=> ['nullable', 'string', 'max:50'],
            'fecha_ingreso'    => ['nullable', 'date'],
            'estado'           => ['required', 'string', 'in:activo,inactivo,egresado,retirado'],
        ]);

        EduEstudiante::create([...$data, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.estudiantes.index')
            ->with('status', 'Estudiante registrado correctamente.');
    }

    public function show(Request $request, EduEstudiante $estudiante): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($estudiante->institucion?->compania_id === $companiaId, 404);

        $estudiante->load(['contacto', 'institucion', 'acudientes.contacto', 'matriculas.periodo', 'matriculas.grado', 'matriculas.grupo']);

        return view('admin.edu.estudiantes.show', compact('estudiante'));
    }

    public function update(Request $request, EduEstudiante $estudiante): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($estudiante->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo_estudiante' => ['nullable', 'string', 'max:50'],
            'fecha_ingreso'     => ['nullable', 'date'],
            'fecha_retiro'      => ['nullable', 'date'],
            'estado'            => ['required', 'string', 'in:activo,inactivo,egresado,retirado'],
        ]);

        $estudiante->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.estudiantes.show', $estudiante)
            ->with('status', 'Estudiante actualizado.');
    }

    public function destroy(Request $request, EduEstudiante $estudiante): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($estudiante->institucion?->compania_id === $companiaId, 404);

        $estudiante->delete();

        return redirect()->route('admin.edu.estudiantes.index')
            ->with('status', 'Estudiante eliminado.');
    }
}
