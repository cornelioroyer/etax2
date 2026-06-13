<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PhTipoCuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhTipoCuotaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('ph.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $tipos = PhTipoCuota::where('compania_id', $companiaId)->orderBy('codigo')->get();

        return view('admin.ph.tipos-cuota.index', compact('tipos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'       => ['required', 'string', 'max:30'],
            'nombre'       => ['required', 'string', 'max:150'],
            'descripcion'  => ['nullable', 'string', 'max:500'],
            'monto_base'   => ['required', 'numeric', 'min:0'],
            'periodicidad' => ['required', 'in:' . implode(',', PhTipoCuota::PERIODICIDADES)],
        ]);

        PhTipoCuota::create([
            ...$data,
            'compania_id' => $companiaId,
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Tipo de cuota creado.');
    }

    public function update(Request $request, PhTipoCuota $tipoCuota): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($tipoCuota->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre'       => ['required', 'string', 'max:150'],
            'descripcion'  => ['nullable', 'string', 'max:500'],
            'monto_base'   => ['required', 'numeric', 'min:0'],
            'periodicidad' => ['required', 'in:' . implode(',', PhTipoCuota::PERIODICIDADES)],
            'activo'       => ['boolean'],
        ]);

        $tipoCuota->update([...$data, 'updated_by' => $request->user()->email]);

        return back()->with('status', 'Tipo de cuota actualizado.');
    }

    public function destroy(Request $request, PhTipoCuota $tipoCuota): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($tipoCuota->compania_id === $this->companiaActivaId($request), 404);

        if ($tipoCuota->cuotas()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: hay cuotas asociadas a este tipo.']);
        }

        $tipoCuota->delete();

        return back()->with('status', 'Tipo de cuota eliminado.');
    }
}
