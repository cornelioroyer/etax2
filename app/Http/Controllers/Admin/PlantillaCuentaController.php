<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlantillaCuenta;
use App\Models\PlantillaCuentaDetalle;
use App\Models\TipoCuenta;
use App\Services\PlantillaCuentas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administración de las plantillas de plan de cuentas (maestro GLOBAL
 * core_plantillas_cuentas / _detalle). Solo super_admin (grupo middleware 'admin').
 *
 * Estas plantillas se copian a una compañía nueva cuando su plan está vacío
 * (App\Services\PlantillaCuentas). Editarlas NO afecta a ninguna compañía ya
 * creada; solo cambia lo que recibirán las compañías futuras.
 */
class PlantillaCuentaController extends Controller
{
    public function index(): View
    {
        $plantillas = PlantillaCuenta::query()
            ->withCount('detalles')
            ->orderBy('codigo')
            ->get();

        return view('admin.plantillas-cuentas.index', [
            'plantillas' => $plantillas,
            'porDefecto' => PlantillaCuentas::POR_DEFECTO,
        ]);
    }

    public function create(): View
    {
        return view('admin.plantillas-cuentas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->email;

        $plantilla = PlantillaCuenta::create($data);

        return redirect()->route('admin.plantillas-cuentas.show', $plantilla)
            ->with('status', 'Plantilla creada. Agrega sus cuentas.');
    }

    public function show(PlantillaCuenta $plantilla): View
    {
        $detalles = $plantilla->detalles()
            ->orderBy('codigo')
            ->get();

        return view('admin.plantillas-cuentas.show', [
            'plantilla' => $plantilla,
            'detalles'  => $detalles,
            'tipos'     => TipoCuenta::orderBy('id')->get(),
            'claves'    => PlantillaCuentaDetalle::clavesConocidas(),
        ]);
    }

    public function edit(PlantillaCuenta $plantilla): View
    {
        return view('admin.plantillas-cuentas.edit', ['plantilla' => $plantilla]);
    }

    public function update(Request $request, PlantillaCuenta $plantilla): RedirectResponse
    {
        $data = $this->validated($request, $plantilla);
        $data['updated_by'] = $request->user()->email;

        $plantilla->update($data);

        return redirect()->route('admin.plantillas-cuentas.show', $plantilla)
            ->with('status', 'Plantilla actualizada.');
    }

    public function destroy(PlantillaCuenta $plantilla): RedirectResponse
    {
        if ($plantilla->codigo === PlantillaCuentas::POR_DEFECTO) {
            return back()->withErrors([
                'plantilla' => "No se puede eliminar «{$plantilla->codigo}»: es la plantilla por defecto que se aplica a las compañías nuevas.",
            ]);
        }

        DB::transaction(function () use ($plantilla) {
            $plantilla->detalles()->delete();
            $plantilla->delete();
        });

        return redirect()->route('admin.plantillas-cuentas.index')
            ->with('status', "Plantilla «{$plantilla->codigo}» eliminada.");
    }

    private function validated(Request $request, ?PlantillaCuenta $plantilla = null): array // phpcs:ignore
    {
        return $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('core_plantillas_cuentas', 'codigo')->ignore($plantilla?->id),
            ],
            'nombre'      => ['required', 'string', 'max:200'],
            'pais'        => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activa'      => ['required', 'boolean'],
        ]);
    }
}
