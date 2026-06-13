<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduInstitucion;
use App\Models\EduNivelAcademico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduNivelAcademicoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $niveles = EduNivelAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('institucion')
            ->orderBy('orden')
            ->paginate(30)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.niveles.index', compact('niveles', 'instituciones'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string'],
            'orden'          => ['nullable', 'integer', 'min:0'],
        ]);

        EduNivelAcademico::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.niveles.index')
            ->with('status', 'Nivel académico creado.');
    }

    public function update(Request $request, EduNivelAcademico $nivel): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($nivel->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'orden'       => ['nullable', 'integer', 'min:0'],
            'activo'      => ['boolean'],
        ]);

        $nivel->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.niveles.index')
            ->with('status', 'Nivel académico actualizado.');
    }

    public function destroy(Request $request, EduNivelAcademico $nivel): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($nivel->institucion?->compania_id === $companiaId, 404);

        $nivel->delete();

        return redirect()->route('admin.edu.niveles.index')
            ->with('status', 'Nivel eliminado.');
    }
}
