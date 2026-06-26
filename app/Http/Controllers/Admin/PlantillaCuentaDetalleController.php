<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlantillaCuenta;
use App\Models\PlantillaCuentaDetalle;
use App\Models\TipoCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cuentas dentro de una plantilla de plan de cuentas
 * (core_plantillas_cuentas_detalle). Solo super_admin (grupo 'admin').
 *
 * La jerarquía se referencia por `codigo_padre` (código de otra cuenta de la
 * MISMA plantilla); el `nivel` se deriva del padre.
 */
class PlantillaCuentaDetalleController extends Controller
{
    public function store(Request $request, PlantillaCuenta $plantilla): RedirectResponse
    {
        $data = $this->validated($request, $plantilla);
        $data['plantilla_id'] = $plantilla->id;
        $data['nivel'] = $this->nivelDesdePadre($plantilla, $data['codigo_padre'] ?? null);
        $data['created_by'] = $request->user()->email;

        PlantillaCuentaDetalle::create($data);

        return redirect()->route('admin.plantillas-cuentas.show', $plantilla)
            ->with('status', "Cuenta {$data['codigo']} agregada.");
    }

    public function edit(PlantillaCuenta $plantilla, PlantillaCuentaDetalle $detalle): View
    {
        $this->verificarPertenencia($plantilla, $detalle);

        $padres = $plantilla->detalles()
            ->where('id', '!=', $detalle->id)
            ->orderBy('codigo')
            ->get(['codigo', 'nombre']);

        return view('admin.plantillas-cuentas.detalle-edit', [
            'plantilla' => $plantilla,
            'detalle'   => $detalle,
            'tipos'     => TipoCuenta::orderBy('id')->get(),
            'padres'    => $padres,
            'claves'    => PlantillaCuentaDetalle::clavesConocidas(),
        ]);
    }

    public function update(Request $request, PlantillaCuenta $plantilla, PlantillaCuentaDetalle $detalle): RedirectResponse
    {
        $this->verificarPertenencia($plantilla, $detalle);

        $data = $this->validated($request, $plantilla, $detalle);
        $data['nivel'] = $this->nivelDesdePadre($plantilla, $data['codigo_padre'] ?? null);
        $data['updated_by'] = $request->user()->email;

        $detalle->update($data);

        return redirect()->route('admin.plantillas-cuentas.show', $plantilla)
            ->with('status', "Cuenta {$detalle->codigo} actualizada.");
    }

    public function destroy(PlantillaCuenta $plantilla, PlantillaCuentaDetalle $detalle): RedirectResponse
    {
        $this->verificarPertenencia($plantilla, $detalle);

        $tieneHijos = $plantilla->detalles()
            ->where('codigo_padre', $detalle->codigo)
            ->exists();

        if ($tieneHijos) {
            return back()->withErrors([
                'detalle' => "No se puede eliminar {$detalle->codigo}: otras cuentas la tienen como padre. Reasígnalas primero.",
            ]);
        }

        $detalle->delete();

        return redirect()->route('admin.plantillas-cuentas.show', $plantilla)
            ->with('status', "Cuenta {$detalle->codigo} eliminada.");
    }

    private function validated(Request $request, PlantillaCuenta $plantilla, ?PlantillaCuentaDetalle $detalle = null): array
    {
        $tipos = TipoCuenta::pluck('codigo')->all();

        $data = $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('core_plantillas_cuentas_detalle', 'codigo')
                    ->where('plantilla_id', $plantilla->id)
                    ->ignore($detalle?->id),
            ],
            'nombre'       => ['required', 'string', 'max:200'],
            'codigo_padre' => [
                'nullable', 'string', 'max:50',
                // Debe existir como código dentro de la MISMA plantilla y no ser sí misma.
                Rule::exists('core_plantillas_cuentas_detalle', 'codigo')
                    ->where('plantilla_id', $plantilla->id),
            ],
            'tipo_cuenta_codigo' => ['required', 'string', Rule::in($tipos)],
            'naturaleza'         => ['required', Rule::in(['DEBITO', 'CREDITO'])],
            'permite_movimiento' => ['required', 'boolean'],
            'conciliable'        => ['required', 'boolean'],
            'clave_default'      => [
                'nullable', 'string', 'max:100',
                // Una clave por defecto solo puede mapear a UNA cuenta dentro de la plantilla.
                Rule::unique('core_plantillas_cuentas_detalle', 'clave_default')
                    ->where('plantilla_id', $plantilla->id)
                    ->ignore($detalle?->id),
            ],
            'renglon_isr' => ['nullable', 'integer', 'min:0'],
        ]);

        // Una cuenta no puede ser su propio padre.
        if (($data['codigo_padre'] ?? null) === $data['codigo']) {
            abort(422, 'Una cuenta no puede ser su propia cuenta padre.');
        }

        $data['codigo_padre'] = $data['codigo_padre'] ?: null;
        $data['clave_default'] = $data['clave_default'] ?: null;
        $data['renglon_isr'] = $data['renglon_isr'] !== null && $data['renglon_isr'] !== '' ? (int) $data['renglon_isr'] : null;

        return $data;
    }

    private function nivelDesdePadre(PlantillaCuenta $plantilla, ?string $codigoPadre): int
    {
        if (! $codigoPadre) {
            return 1;
        }

        $padre = $plantilla->detalles()
            ->where('codigo', $codigoPadre)
            ->first(['nivel']);

        return $padre ? $padre->nivel + 1 : 1;
    }

    private function verificarPertenencia(PlantillaCuenta $plantilla, PlantillaCuentaDetalle $detalle): void
    {
        abort_unless($detalle->plantilla_id === $plantilla->id, 404);
    }
}
