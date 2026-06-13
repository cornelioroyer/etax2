<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\EduDocente;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduDocenteController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search = trim($request->input('q', ''));

        $docentes = EduDocente::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'contacto'])
            ->when($search !== '', fn ($q) => $q->whereHas('contacto', fn ($c) => $c
                ->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('identificacion', 'ilike', "%{$search}%"))
                ->orWhere('codigo_docente', 'ilike', "%{$search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.docentes.index', compact('docentes', 'instituciones', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId    = $this->companiaActivaId($request);
        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $contactos     = Contacto::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.docentes.create', compact('instituciones', 'contactos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'contacto_id'    => ['required', 'integer', 'exists:contactos,id'],
            'codigo_docente' => ['nullable', 'string', 'max:50'],
            'especialidad'   => ['nullable', 'string', 'max:200'],
            'fecha_ingreso'  => ['nullable', 'date'],
            'estado'         => ['required', 'string', 'in:activo,inactivo'],
        ]);

        EduDocente::create([...$data, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.docentes.index')
            ->with('status', 'Docente registrado correctamente.');
    }

    public function update(Request $request, EduDocente $docente): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($docente->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo_docente' => ['nullable', 'string', 'max:50'],
            'especialidad'   => ['nullable', 'string', 'max:200'],
            'fecha_ingreso'  => ['nullable', 'date'],
            'fecha_salida'   => ['nullable', 'date'],
            'estado'         => ['required', 'string', 'in:activo,inactivo'],
        ]);

        $docente->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.docentes.index')
            ->with('status', 'Docente actualizado.');
    }

    public function destroy(Request $request, EduDocente $docente): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($docente->institucion?->compania_id === $companiaId, 404);

        $docente->delete();

        return redirect()->route('admin.edu.docentes.index')
            ->with('status', 'Docente eliminado.');
    }
}
