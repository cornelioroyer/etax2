<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\AfiActivo;
use App\Models\AfiRevaluacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AfiRevaluacionController extends Controller
{
    use ConCompaniaActiva;

    public function create(Request $request, AfiActivo $activo): View
    {
        $companiaId = $this->companiaActivaId($request);
        abort_unless($activo->compania_id === $companiaId, 404);
        abort_unless($request->user()->can('activos.gestionar'), 403);

        $revaluaciones = AfiRevaluacion::where('activo_id', $activo->id)
            ->orderByDesc('fecha')->get();

        return view('admin.activos.revaluaciones.create', compact('activo', 'revaluaciones'));
    }

    public function store(Request $request, AfiActivo $activo): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($activo->compania_id === $companiaId, 404);

        $data = $request->validate([
            'fecha'          => ['required', 'date'],
            'valor_anterior' => ['required', 'numeric', 'min:0'],
            'valor_nuevo'    => ['required', 'numeric', 'min:0'],
        ]);

        AfiRevaluacion::create([
            'activo_id'      => $activo->id,
            'fecha'          => $data['fecha'],
            'valor_anterior' => $data['valor_anterior'],
            'valor_nuevo'    => $data['valor_nuevo'],
            'created_by'     => $request->user()->email,
        ]);

        return redirect()->route('admin.activos.show', $activo)->with('status', 'Revaluación registrada.');
    }
}
