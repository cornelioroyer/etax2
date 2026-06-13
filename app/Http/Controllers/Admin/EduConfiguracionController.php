<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduConfiguracion;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduConfiguracionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $institucionId = $request->input('institucion_id', $instituciones->first()?->id);
        $config        = null;

        if ($institucionId) {
            $config = EduConfiguracion::where('institucion_id', $institucionId)->first();
        }

        return view('admin.edu.configuracion.index', compact('instituciones', 'institucionId', 'config'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $institIds = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id'                  => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'dia_vencimiento_mensualidad'     => ['nullable', 'integer', 'min:1', 'max:31'],
            'generar_cargos_automaticos'      => ['boolean'],
            'tipo_recargo_mora'               => ['nullable', 'string', 'in:fijo,porcentaje'],
            'recargo_monto_fijo'              => ['nullable', 'numeric', 'min:0'],
            'recargo_porcentaje'              => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        EduConfiguracion::updateOrCreate(
            ['institucion_id' => $data['institucion_id']],
            [...$data, 'updated_by' => $request->user()->email]
        );

        return redirect()->route('admin.edu.configuracion.index', ['institucion_id' => $data['institucion_id']])
            ->with('status', 'Configuración guardada correctamente.');
    }
}
