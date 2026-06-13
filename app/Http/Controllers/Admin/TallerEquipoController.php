<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerClienteEquipo;
use App\Models\TallerEquipo;
use App\Models\TallerEquipoMedicion;
use App\Models\TallerTaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerEquipoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search   = trim($request->input('q', ''));
        $tallerId = $request->input('taller_id');

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $equipos = TallerEquipo::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q
                ->where(fn ($sub) => $sub
                    ->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('numero_serie', 'ilike', "%{$search}%")
                    ->orWhere('placa', 'ilike', "%{$search}%")
                    ->orWhere('vin', 'ilike', "%{$search}%")
                    ->orWhere('codigo', 'ilike', "%{$search}%")
                )
            )
            ->with(['tipoEquipo', 'marca', 'modelo', 'clientePrincipal.cliente'])
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.taller.equipos.index', compact('equipos', 'search', 'talleres', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $tallerId   = $request->input('taller_id');
        $taller     = $tallerId ? $talleres->firstWhere('id', $tallerId) : null;
        $tiposEquipo = collect();
        $marcas     = collect();
        $modelos    = collect();

        if ($taller) {
            $taller->load(['tiposEquipo', 'marcas', 'modelos']);
            $tiposEquipo = $taller->tiposEquipo;
            $marcas     = $taller->marcas;
            $modelos    = $taller->modelos;
        }

        return view('admin.taller.equipos.create', compact('talleres', 'taller', 'tallerId', 'tiposEquipo', 'marcas', 'modelos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'taller_id'       => ['required', 'integer'],
            'tipo_equipo_id'  => ['nullable', 'integer'],
            'marca_id'        => ['nullable', 'integer'],
            'modelo_id'       => ['nullable', 'integer'],
            'codigo'          => ['nullable', 'string', 'max:50'],
            'nombre'          => ['nullable', 'string', 'max:200'],
            'numero_serie'    => ['nullable', 'string', 'max:100'],
            'placa'           => ['nullable', 'string', 'max:50'],
            'vin'             => ['nullable', 'string', 'max:100'],
            'anio'            => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'color'           => ['nullable', 'string', 'max:50'],
            'descripcion'     => ['nullable', 'string'],
            'activo'          => ['boolean'],
        ]);

        $taller = TallerTaller::findOrFail($data['taller_id']);
        abort_unless($taller->compania_id === $companiaId, 403);

        $equipo = TallerEquipo::create([
            ...$data,
            'activo'     => $request->boolean('activo', true),
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.equipos.show', $equipo)
            ->with('status', 'Equipo creado correctamente.');
    }

    public function show(Request $request, TallerEquipo $equipo): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $equipo->load('taller');
        abort_unless($equipo->taller->compania_id === $this->companiaActivaId($request), 404);

        $equipo->load([
            'taller', 'tipoEquipo', 'marca', 'modelo',
            'clientes.cliente',
        ]);

        $mediciones = TallerEquipoMedicion::where('equipo_id', $equipo->id)
            ->orderBy('fecha', 'desc')
            ->limit(20)
            ->get();

        return view('admin.taller.equipos.show', compact('equipo', 'mediciones'));
    }

    public function edit(Request $request, TallerEquipo $equipo): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $equipo->load('taller');
        abort_unless($equipo->taller->compania_id === $this->companiaActivaId($request), 404);

        $companiaId = $this->companiaActivaId($request);

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $equipo->taller->load(['tiposEquipo', 'marcas', 'modelos']);
        $tiposEquipo = $equipo->taller->tiposEquipo;
        $marcas      = $equipo->taller->marcas;
        $modelos     = $equipo->taller->modelos;

        return view('admin.taller.equipos.edit', compact('equipo', 'talleres', 'tiposEquipo', 'marcas', 'modelos'));
    }

    public function update(Request $request, TallerEquipo $equipo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $equipo->load('taller');
        abort_unless($equipo->taller->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'taller_id'       => ['required', 'integer'],
            'tipo_equipo_id'  => ['nullable', 'integer'],
            'marca_id'        => ['nullable', 'integer'],
            'modelo_id'       => ['nullable', 'integer'],
            'codigo'          => ['nullable', 'string', 'max:50'],
            'nombre'          => ['nullable', 'string', 'max:200'],
            'numero_serie'    => ['nullable', 'string', 'max:100'],
            'placa'           => ['nullable', 'string', 'max:50'],
            'vin'             => ['nullable', 'string', 'max:100'],
            'anio'            => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'color'           => ['nullable', 'string', 'max:50'],
            'descripcion'     => ['nullable', 'string'],
            'activo'          => ['boolean'],
        ]);

        $equipo->update([
            ...$data,
            'activo'     => $request->boolean('activo'),
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.equipos.show', $equipo)
            ->with('status', 'Equipo actualizado.');
    }

    public function destroy(Request $request, TallerEquipo $equipo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $equipo->load('taller');
        abort_unless($equipo->taller->compania_id === $this->companiaActivaId($request), 404);

        // Verificar que no tenga órdenes (tabla futura)
        // if ($equipo->ordenes()->exists()) {
        //     return back()->withErrors(['destroy' => 'No se puede eliminar: el equipo tiene órdenes registradas.']);
        // }

        $equipo->delete();

        return redirect()->route('admin.taller.equipos.index')
            ->with('status', 'Equipo eliminado.');
    }

    public function storeCliente(Request $request, TallerEquipo $equipo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $equipo->load('taller');
        abort_unless($equipo->taller->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'cliente_id'   => ['required', 'integer'],
            'relacion'     => ['required', 'string', 'in:' . implode(',', array_keys(TallerClienteEquipo::RELACIONES))],
            'principal'    => ['boolean'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date'],
        ]);

        $esPrincipal = $request->boolean('principal');

        if ($esPrincipal) {
            TallerClienteEquipo::where('equipo_id', $equipo->id)
                ->where('principal', true)
                ->update(['principal' => false, 'updated_by' => $request->user()->email]);
        }

        TallerClienteEquipo::create([
            'taller_id'    => $equipo->taller_id,
            'equipo_id'    => $equipo->id,
            'cliente_id'   => $data['cliente_id'],
            'relacion'     => $data['relacion'],
            'principal'    => $esPrincipal,
            'fecha_inicio' => $data['fecha_inicio'] ?? null,
            'fecha_fin'    => $data['fecha_fin'] ?? null,
            'activo'       => true,
            'created_by'   => $request->user()->email,
        ]);

        return back()->with('status', 'Cliente agregado al equipo.');
    }

    public function destroyCliente(Request $request, TallerEquipo $equipo, TallerClienteEquipo $clienteEquipo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $equipo->load('taller');
        abort_unless($equipo->taller->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($clienteEquipo->equipo_id === $equipo->id, 404);

        $clienteEquipo->delete();

        return back()->with('status', 'Relación cliente-equipo eliminada.');
    }

    public function storeMedicion(Request $request, TallerEquipo $equipo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $equipo->load('taller');
        abort_unless($equipo->taller->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'tipo_medicion' => ['required', 'string', 'max:100'],
            'valor'         => ['required', 'numeric'],
            'unidad'        => ['nullable', 'string', 'max:50'],
            'observacion'   => ['nullable', 'string'],
            'fecha'         => ['nullable', 'date'],
        ]);

        TallerEquipoMedicion::create([
            'equipo_id'     => $equipo->id,
            'fecha'         => $data['fecha'] ?? now(),
            'tipo_medicion' => $data['tipo_medicion'],
            'valor'         => $data['valor'],
            'unidad'        => $data['unidad'] ?? null,
            'observacion'   => $data['observacion'] ?? null,
            'created_by'    => $request->user()->email,
        ]);

        return back()->with('status', 'Medición registrada.');
    }
}
