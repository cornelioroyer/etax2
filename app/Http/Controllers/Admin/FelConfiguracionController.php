<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\FelConfiguracion;
use App\Services\FelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FelConfiguracionController extends Controller
{
    public function edit(Request $request): View
    {
        $compania = $this->companiaActiva($request);
        $config = FelConfiguracion::firstWhere('compania_id', $compania->id);

        return view('admin.fel.configuracion', compact('compania', 'config'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('fel.gestionar'), 403);
        $compania = $this->companiaActiva($request);

        $data = $request->validate([
            'ambiente' => ['required', Rule::in(['PRUEBAS', 'PRODUCCION'])],
            'token_empresa' => ['nullable', 'string', 'max:500'],
            'token_password' => ['nullable', 'string', 'max:500'],
            'punto_facturacion' => ['required', 'string', 'max:10'],
            'codigo_sucursal' => ['required', 'string', 'max:10'],
            'correlativo' => ['required', 'integer', 'min:0'],
        ]);

        $config = FelConfiguracion::firstOrNew(['compania_id' => $compania->id]);

        // No pisar tokens guardados si el campo viene vacío
        if (empty($data['token_empresa'])) {
            unset($data['token_empresa']);
        }
        if (empty($data['token_password'])) {
            unset($data['token_password']);
        }

        $config->fill($data + [
            'proveedor' => 'The Factory HKA',
            'activa' => true,
            $config->exists ? 'updated_by' : 'created_by' => $request->user()->email,
        ])->save();

        return redirect()->route('admin.fel.configuracion')
            ->with('status', 'Configuración FEL guardada.');
    }

    /** Prueba la conexión consultando los folios restantes. */
    public function probar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('fel.gestionar'), 403);
        $compania = $this->companiaActiva($request);
        $config = FelConfiguracion::firstWhere('compania_id', $compania->id);

        if (! $config || ! $config->token_empresa) {
            return back()->withErrors(['fel' => 'Guarda primero los tokens de The Factory HKA.']);
        }

        $resp = (new FelService($config))->foliosRestantes();

        if (! empty($resp['error'])) {
            return back()->withErrors(['fel' => 'Error de conexión: '.$resp['mensaje']]);
        }

        return back()->with('status', 'Conexión OK — respuesta del PAC: '.json_encode($resp, JSON_UNESCAPED_UNICODE));
    }

    private function companiaActiva(Request $request): Compania
    {
        $companiaId = session('compania_activa_id');
        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', (int) $companiaId),
            403
        );

        return Compania::findOrFail($companiaId);
    }
}
