<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PrhTipoCuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrhTipoCuotaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('prh.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $tipos = PrhTipoCuota::where('compania_id', $companiaId)->orderBy('codigo')->get();

        return view('admin.prh.tipos-cuota.index', compact('tipos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'       => ['required', 'string', 'max:30'],
            'nombre'       => ['required', 'string', 'max:150'],
            'descripcion'  => ['nullable', 'string', 'max:500'],
            'monto_base'   => ['required', 'numeric', 'min:0'],
            'periodicidad' => ['required', 'in:' . implode(',', PrhTipoCuota::PERIODICIDADES)],
        ]);

        PrhTipoCuota::create([
            ...$data,
            'compania_id' => $companiaId,
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Tipo de cuota creado.');
    }

    public function update(Request $request, PrhTipoCuota $tipoCuota): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($tipoCuota->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre'       => ['required', 'string', 'max:150'],
            'descripcion'  => ['nullable', 'string', 'max:500'],
            'monto_base'   => ['required', 'numeric', 'min:0'],
            'periodicidad' => ['required', 'in:' . implode(',', PrhTipoCuota::PERIODICIDADES)],
            'activo'       => ['boolean'],
        ]);

        $tipoCuota->update([...$data, 'updated_by' => $request->user()->email]);

        return back()->with('status', 'Tipo de cuota actualizado.');
    }

    public function destroy(Request $request, PrhTipoCuota $tipoCuota): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($tipoCuota->compania_id === $this->companiaActivaId($request), 404);

        if ($tipoCuota->cuotas()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: hay cuotas asociadas a este tipo.']);
        }

        $tipoCuota->delete();

        return back()->with('status', 'Tipo de cuota eliminado.');
    }
}
