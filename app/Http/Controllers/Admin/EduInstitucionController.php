<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduInstitucionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $instituciones = EduInstitucion::where('compania_id', $companiaId)
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.edu.instituciones.index', compact('instituciones'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'           => ['required', 'string', 'max:30'],
            'nombre'           => ['required', 'string', 'max:200'],
            'tipo_institucion' => ['nullable', 'string', 'max:50'],
            'direccion'        => ['nullable', 'string', 'max:300'],
            'telefono'         => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:150'],
            'sitio_web'        => ['nullable', 'string', 'max:200'],
        ]);

        EduInstitucion::create([
            ...$data,
            'compania_id' => $companiaId,
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.edu.instituciones.index')
            ->with('status', 'Institución creada correctamente.');
    }

    public function update(Request $request, EduInstitucion $institucion): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($institucion->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'           => ['required', 'string', 'max:30'],
            'nombre'           => ['required', 'string', 'max:200'],
            'tipo_institucion' => ['nullable', 'string', 'max:50'],
            'direccion'        => ['nullable', 'string', 'max:300'],
            'telefono'         => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:150'],
            'sitio_web'        => ['nullable', 'string', 'max:200'],
            'activo'           => ['boolean'],
        ]);

        $institucion->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.instituciones.index')
            ->with('status', 'Institución actualizada.');
    }

    public function destroy(Request $request, EduInstitucion $institucion): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($institucion->compania_id === $companiaId, 404);

        if ($institucion->sedes()->exists() || $institucion->estudiantes()->exists()) {
            return back()->with('error', 'No se puede eliminar una institución con sedes o estudiantes registrados.');
        }

        $institucion->delete();

        return redirect()->route('admin.edu.instituciones.index')
            ->with('status', 'Institución eliminada.');
    }
}
