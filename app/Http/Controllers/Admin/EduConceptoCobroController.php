<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduConceptoCobro;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduConceptoCobroController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $conceptos = EduConceptoCobro::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('institucion')
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.conceptos-cobro.index', compact('conceptos', 'instituciones'));
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
            'tipo_concepto'  => ['nullable', 'string', 'max:50'],
            'frecuencia'     => ['nullable', 'string', 'max:50'],
            'monto_base'     => ['nullable', 'numeric', 'min:0'],
        ]);

        EduConceptoCobro::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.conceptos-cobro.index')
            ->with('status', 'Concepto de cobro creado.');
    }

    public function update(Request $request, EduConceptoCobro $concepto): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($concepto->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'        => ['required', 'string', 'max:30'],
            'nombre'        => ['required', 'string', 'max:200'],
            'descripcion'   => ['nullable', 'string'],
            'tipo_concepto' => ['nullable', 'string', 'max:50'],
            'frecuencia'    => ['nullable', 'string', 'max:50'],
            'monto_base'    => ['nullable', 'numeric', 'min:0'],
            'activo'        => ['boolean'],
        ]);

        $concepto->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.conceptos-cobro.index')
            ->with('status', 'Concepto actualizado.');
    }

    public function destroy(Request $request, EduConceptoCobro $concepto): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($concepto->institucion?->compania_id === $companiaId, 404);

        $concepto->delete();

        return redirect()->route('admin.edu.conceptos-cobro.index')
            ->with('status', 'Concepto eliminado.');
    }
}
