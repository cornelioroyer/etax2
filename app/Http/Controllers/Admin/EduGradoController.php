<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduGrado;
use App\Models\EduInstitucion;
use App\Models\EduNivelAcademico;
use App\Models\EduPrograma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduGradoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $grados = EduGrado::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'nivel', 'programa'])
            ->orderBy('orden')
            ->paginate(30)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $niveles = EduNivelAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->orderBy('orden')->get();
        $programas = EduPrograma::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->orderBy('nombre')->get();

        return view('admin.edu.grados.index', compact('grados', 'instituciones', 'niveles', 'programas'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'nivel_id'       => ['nullable', 'integer', 'exists:edu_niveles_academicos,id'],
            'programa_id'    => ['nullable', 'integer', 'exists:edu_programas,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'orden'          => ['nullable', 'integer', 'min:0'],
        ]);

        EduGrado::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.grados.index')
            ->with('status', 'Grado creado.');
    }

    public function update(Request $request, EduGrado $grado): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($grado->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'nivel_id'    => ['nullable', 'integer', 'exists:edu_niveles_academicos,id'],
            'programa_id' => ['nullable', 'integer', 'exists:edu_programas,id'],
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'orden'       => ['nullable', 'integer', 'min:0'],
            'activo'      => ['boolean'],
        ]);

        $grado->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.grados.index')
            ->with('status', 'Grado actualizado.');
    }

    public function destroy(Request $request, EduGrado $grado): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($grado->institucion?->compania_id === $companiaId, 404);

        $grado->delete();

        return redirect()->route('admin.edu.grados.index')
            ->with('status', 'Grado eliminado.');
    }
}
