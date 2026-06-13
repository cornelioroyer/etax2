<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduComunicado;
use App\Models\EduGrado;
use App\Models\EduGrupo;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduComunicadoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $comunicados = EduComunicado::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'grado', 'grupo'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $grados        = EduGrado::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $grupos        = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.comunicados.index', compact('comunicados', 'instituciones', 'grados', 'grupos'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId    = $this->companiaActivaId($request);
        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $grados        = EduGrado::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $grupos        = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.comunicados.create', compact('instituciones', 'grados', 'grupos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'titulo'         => ['required', 'string', 'max:200'],
            'mensaje'        => ['required', 'string'],
            'dirigido_a'     => ['nullable', 'string', 'in:todos,grado,grupo,individual'],
            'grado_id'       => ['nullable', 'integer', 'exists:edu_grados,id'],
            'grupo_id'       => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'estado'         => ['required', 'string', 'in:borrador,enviado'],
        ]);

        EduComunicado::create([
            ...$data,
            'fecha_envio' => $data['estado'] === 'enviado' ? now() : null,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.edu.comunicados.index')
            ->with('status', 'Comunicado creado.');
    }

    public function show(Request $request, EduComunicado $comunicado): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($comunicado->institucion?->compania_id === $companiaId, 404);

        $comunicado->load(['institucion', 'grado', 'grupo', 'destinatarios']);

        return view('admin.edu.comunicados.show', compact('comunicado'));
    }

    public function update(Request $request, EduComunicado $comunicado): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($comunicado->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'titulo'     => ['required', 'string', 'max:200'],
            'mensaje'    => ['required', 'string'],
            'dirigido_a' => ['nullable', 'string', 'in:todos,grado,grupo,individual'],
            'grado_id'   => ['nullable', 'integer', 'exists:edu_grados,id'],
            'grupo_id'   => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'estado'     => ['required', 'string', 'in:borrador,enviado'],
        ]);

        $comunicado->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.comunicados.index')
            ->with('status', 'Comunicado actualizado.');
    }

    public function destroy(Request $request, EduComunicado $comunicado): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($comunicado->institucion?->compania_id === $companiaId, 404);

        $comunicado->delete();

        return redirect()->route('admin.edu.comunicados.index')
            ->with('status', 'Comunicado eliminado.');
    }
}
