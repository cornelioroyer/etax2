<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduEsquemaCalificacion;
use App\Models\EduEsquemaCalificacionDetalle;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduEsquemaCalificacionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $esquemas = EduEsquemaCalificacion::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'detalles'])
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.esquemas.index', compact('esquemas', 'instituciones'));
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
        ]);

        EduEsquemaCalificacion::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.esquemas.index')
            ->with('status', 'Esquema de calificación creado.');
    }

    public function update(Request $request, EduEsquemaCalificacion $esquema): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($esquema->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'activo'      => ['boolean'],
        ]);

        $esquema->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.esquemas.index')
            ->with('status', 'Esquema actualizado.');
    }

    public function destroy(Request $request, EduEsquemaCalificacion $esquema): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($esquema->institucion?->compania_id === $companiaId, 404);

        $esquema->delete();

        return redirect()->route('admin.edu.esquemas.index')
            ->with('status', 'Esquema eliminado.');
    }

    public function storeDetalle(Request $request, EduEsquemaCalificacion $esquema): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($esquema->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'          => ['required', 'string', 'max:30'],
            'nombre'          => ['required', 'string', 'max:200'],
            'tipo_evaluacion' => ['nullable', 'string', 'max:50'],
            'porcentaje'      => ['required', 'numeric', 'min:0', 'max:100'],
            'orden'           => ['nullable', 'integer', 'min:0'],
        ]);

        EduEsquemaCalificacionDetalle::create([
            ...$data,
            'esquema_id' => $esquema->id,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.edu.esquemas.index')
            ->with('status', 'Componente agregado al esquema.');
    }

    public function destroyDetalle(Request $request, EduEsquemaCalificacion $esquema, EduEsquemaCalificacionDetalle $detalle): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($esquema->institucion?->compania_id === $companiaId, 404);
        abort_unless($detalle->esquema_id === $esquema->id, 404);

        $detalle->delete();

        return redirect()->route('admin.edu.esquemas.index')
            ->with('status', 'Componente eliminado.');
    }
}
