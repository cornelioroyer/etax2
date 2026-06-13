<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerOrden;
use App\Models\TallerOrdenAprobacion;
use App\Models\TallerOrdenDiagnostico;
use App\Models\TallerOrdenHistorial;
use App\Models\TallerOrdenManoObra;
use App\Models\TallerOrdenRepuesto;
use App\Models\TallerOrdenServicio;
use App\Models\TallerOrdenSintoma;
use App\Models\TallerControlCalidad;
use App\Models\TallerEntrega;
use App\Models\TallerFacturacion;
use App\Models\TallerTaller;
use App\Models\TallerTecnico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerOrdenController extends Controller
{
    use ConCompaniaActiva;

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search   = trim($request->input('q', ''));
        $tallerId = $request->input('taller_id');
        $estado   = $request->input('estado');

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $ordenes = TallerOrden::where('compania_id', $companiaId)
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('numero', 'ilike', "%{$search}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'ilike', "%{$search}%"))
            ))
            ->with(['taller', 'cliente', 'equipo.tipoEquipo'])
            ->orderBy('fecha_recepcion', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.taller.ordenes.index', compact(
            'ordenes', 'search', 'talleres', 'tallerId', 'estado'
        ));
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $tallerId = $request->input('taller_id');
        $taller   = $tallerId ? $talleres->firstWhere('id', $tallerId) : null;

        $equipos  = collect();
        $tecnicos = collect();

        if ($taller) {
            $taller->load(['tiposEquipo', 'tecnicos']);
            $equipos  = \App\Models\TallerEquipo::where('taller_id', $taller->id)
                ->orderBy('nombre')
                ->get();
            $tecnicos = $taller->tecnicos;
        }

        return view('admin.taller.ordenes.create', compact(
            'talleres', 'taller', 'tallerId', 'equipos', 'tecnicos'
        ));
    }

    // ── Guardar ───────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'taller_id'              => ['required', 'integer'],
            'equipo_id'              => ['nullable', 'integer'],
            'cliente_id'             => ['nullable', 'integer'],
            'tipo_servicio'          => ['required', 'string', 'in:' . implode(',', array_keys(TallerOrden::TIPOS_SERVICIO))],
            'origen'                 => ['required', 'string', 'in:' . implode(',', array_keys(TallerOrden::ORIGENES))],
            'prioridad'              => ['required', 'string', 'in:' . implode(',', array_keys(TallerOrden::PRIORIDADES))],
            'fecha_prometida'        => ['nullable', 'date'],
            'sintomas_reportados'    => ['nullable', 'string'],
            'observacion_recepcion'  => ['nullable', 'string'],
            'medidor_valor'          => ['nullable', 'numeric'],
            'medidor_unidad'         => ['nullable', 'string', 'max:50'],
        ]);

        $taller = TallerTaller::findOrFail($data['taller_id']);
        abort_unless($taller->compania_id === $companiaId, 403);

        $numero = TallerOrden::siguienteNumero($taller->id);

        $orden = TallerOrden::create([
            ...$data,
            'compania_id'     => $companiaId,
            'numero'          => $numero,
            'estado'          => 'recibida',
            'fecha_recepcion' => now(),
            'created_by'      => $request->user()->email,
        ]);

        TallerOrdenHistorial::create([
            'orden_id'      => $orden->id,
            'estado_nuevo'  => 'recibida',
            'descripcion'   => 'Orden creada',
            'usuario_id'    => $request->user()->id,
            'created_at'    => now(),
            'created_by'    => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.ordenes.show', $orden)
            ->with('status', "Orden {$numero} creada correctamente.");
    }

    // ── Mostrar ───────────────────────────────────────────────────────────────

    public function show(Request $request, TallerOrden $orden): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $orden->load([
            'taller',
            'equipo.tipoEquipo',
            'cliente',
            'sintomas.sintoma',
            'diagnosticos.tecnico',
            'servicios.servicio',
            'servicios.tecnico',
            'manoObra.tecnico',
            'repuestos',
            'historial',
            'aprobaciones',
            'controlCalidad.tecnico',
            'entrega',
            'facturacion',
        ]);

        $tecnicos = \App\Models\TallerTecnico::where('taller_id', $orden->taller_id)
            ->where('activo', true)
            ->orderBy('nombre_publico')
            ->get();

        $sintomasDisponibles = \App\Models\TallerSintoma::where('taller_id', $orden->taller_id)
            ->orderBy('nombre')
            ->get();

        $serviciosEstandar = \App\Models\TallerServicioEstandar::where('taller_id', $orden->taller_id)
            ->orderBy('nombre')
            ->get();

        return view('admin.taller.ordenes.show', compact(
            'orden', 'tecnicos', 'sintomasDisponibles', 'serviciosEstandar'
        ));
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function edit(Request $request, TallerOrden $orden): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $orden->load('taller');

        return view('admin.taller.ordenes.edit', compact('orden'));
    }

    // ── Actualizar ────────────────────────────────────────────────────────────

    public function update(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'prioridad'             => ['required', 'string', 'in:' . implode(',', array_keys(TallerOrden::PRIORIDADES))],
            'tipo_servicio'         => ['required', 'string', 'in:' . implode(',', array_keys(TallerOrden::TIPOS_SERVICIO))],
            'origen'                => ['required', 'string', 'in:' . implode(',', array_keys(TallerOrden::ORIGENES))],
            'fecha_prometida'       => ['nullable', 'date'],
            'sintomas_reportados'   => ['nullable', 'string'],
            'observacion_recepcion' => ['nullable', 'string'],
            'medidor_valor'         => ['nullable', 'numeric'],
            'medidor_unidad'        => ['nullable', 'string', 'max:50'],
            'garantia_dias'         => ['nullable', 'integer', 'min:0'],
        ]);

        $estadoAnterior = $orden->estado;

        $orden->update([
            ...$data,
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.ordenes.show', $orden)
            ->with('status', 'Orden actualizada.');
    }

    // ── Cambiar estado ────────────────────────────────────────────────────────

    public function cambiarEstado(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'estado'      => ['required', 'string', 'in:' . implode(',', array_keys(TallerOrden::ESTADOS))],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        $estadoAnterior = $orden->estado;

        $orden->update([
            'estado'     => $data['estado'],
            'updated_by' => $request->user()->email,
        ]);

        TallerOrdenHistorial::create([
            'orden_id'       => $orden->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'   => $data['estado'],
            'descripcion'    => $data['descripcion'] ?? null,
            'usuario_id'     => $request->user()->id,
            'created_at'     => now(),
            'created_by'     => $request->user()->email,
        ]);

        return back()->with('status', 'Estado actualizado a: ' . (TallerOrden::ESTADOS[$data['estado']] ?? $data['estado']));
    }

    // ── Síntomas ──────────────────────────────────────────────────────────────

    public function storeSintoma(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'sintoma_id'  => ['nullable', 'integer'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['sintoma_id']) && empty($data['descripcion'])) {
            return back()->withErrors(['sintoma' => 'Debe indicar un síntoma o una descripción.']);
        }

        TallerOrdenSintoma::create([
            'orden_id'    => $orden->id,
            'sintoma_id'  => $data['sintoma_id'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'created_at'  => now(),
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Síntoma agregado.');
    }

    public function destroySintoma(Request $request, TallerOrden $orden, TallerOrdenSintoma $sintoma): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($sintoma->orden_id === $orden->id, 404);

        $sintoma->delete();

        return back()->with('status', 'Síntoma eliminado.');
    }

    // ── Diagnósticos ──────────────────────────────────────────────────────────

    public function storeDiagnostico(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'tecnico_id'          => ['nullable', 'integer'],
            'diagnostico'         => ['required', 'string'],
            'causa'               => ['nullable', 'string'],
            'solucion_propuesta'  => ['nullable', 'string'],
            'requiere_aprobacion' => ['boolean'],
            'costo_estimado'      => ['nullable', 'numeric', 'min:0'],
            'precio_estimado'     => ['nullable', 'numeric', 'min:0'],
        ]);

        TallerOrdenDiagnostico::create([
            ...$data,
            'orden_id'            => $orden->id,
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion', true),
            'estado'              => 'pendiente',
            'created_by'          => $request->user()->email,
        ]);

        return back()->with('status', 'Diagnóstico registrado.');
    }

    public function destroyDiagnostico(Request $request, TallerOrden $orden, TallerOrdenDiagnostico $diagnostico): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($diagnostico->orden_id === $orden->id, 404);

        $diagnostico->delete();

        return back()->with('status', 'Diagnóstico eliminado.');
    }

    // ── Servicios ─────────────────────────────────────────────────────────────

    public function storeServicio(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'servicio_id'    => ['nullable', 'integer'],
            'tecnico_id'     => ['nullable', 'integer'],
            'descripcion'    => ['required', 'string', 'max:500'],
            'cantidad'       => ['required', 'numeric', 'min:0.01'],
            'precio_unitario'=> ['required', 'numeric', 'min:0'],
            'garantia_dias'  => ['nullable', 'integer', 'min:0'],
        ]);

        $total = round($data['cantidad'] * $data['precio_unitario'], 2);

        TallerOrdenServicio::create([
            ...$data,
            'orden_id'   => $orden->id,
            'total'      => $total,
            'estado'     => 'pendiente',
            'created_by' => $request->user()->email,
        ]);

        $this->recalcularTotales($orden);

        return back()->with('status', 'Servicio agregado.');
    }

    public function destroyServicio(Request $request, TallerOrden $orden, TallerOrdenServicio $servicio): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($servicio->orden_id === $orden->id, 404);

        $servicio->delete();
        $this->recalcularTotales($orden);

        return back()->with('status', 'Servicio eliminado.');
    }

    // ── Mano de obra ──────────────────────────────────────────────────────────

    public function storeManoObra(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'tecnico_id'  => ['nullable', 'integer'],
            'descripcion' => ['required', 'string', 'max:500'],
            'horas'       => ['required', 'numeric', 'min:0'],
            'precio_hora' => ['required', 'numeric', 'min:0'],
            'facturable'  => ['boolean'],
            'fecha'       => ['nullable', 'date'],
        ]);

        $precioTotal = round($data['horas'] * $data['precio_hora'], 2);

        TallerOrdenManoObra::create([
            ...$data,
            'orden_id'    => $orden->id,
            'facturable'  => $request->boolean('facturable', true),
            'fecha'       => $data['fecha'] ?? now()->toDateString(),
            'precio_total'=> $precioTotal,
            'created_by'  => $request->user()->email,
        ]);

        $this->recalcularTotales($orden);

        return back()->with('status', 'Mano de obra registrada.');
    }

    public function destroyManoObra(Request $request, TallerOrden $orden, TallerOrdenManoObra $manoObra): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($manoObra->orden_id === $orden->id, 404);

        $manoObra->delete();
        $this->recalcularTotales($orden);

        return back()->with('status', 'Mano de obra eliminada.');
    }

    // ── Repuestos ─────────────────────────────────────────────────────────────

    public function storeRepuesto(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'item_id'             => ['required', 'integer'],
            'descripcion'         => ['nullable', 'string', 'max:500'],
            'cantidad_solicitada' => ['required', 'numeric', 'min:0.01'],
            'precio_unitario'     => ['required', 'numeric', 'min:0'],
        ]);

        $total = round($data['cantidad_solicitada'] * $data['precio_unitario'], 2);

        TallerOrdenRepuesto::create([
            ...$data,
            'orden_id'   => $orden->id,
            'total'      => $total,
            'estado'     => 'solicitado',
            'created_by' => $request->user()->email,
        ]);

        $this->recalcularTotales($orden);

        return back()->with('status', 'Repuesto agregado.');
    }

    public function destroyRepuesto(Request $request, TallerOrden $orden, TallerOrdenRepuesto $repuesto): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($repuesto->orden_id === $orden->id, 404);

        $repuesto->delete();
        $this->recalcularTotales($orden);

        return back()->with('status', 'Repuesto eliminado.');
    }

    // ── Control de calidad ────────────────────────────────────────────────────

    public function storeControlCalidad(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'tecnico_id'  => ['nullable', 'integer'],
            'resultado'   => ['required', 'string', 'in:' . implode(',', array_keys(TallerControlCalidad::RESULTADOS))],
            'observacion' => ['nullable', 'string'],
        ]);

        TallerControlCalidad::create([
            ...$data,
            'orden_id'   => $orden->id,
            'usuario_id' => $request->user()->id,
            'fecha'      => now(),
            'created_at' => now(),
            'created_by' => $request->user()->email,
        ]);

        if ($data['resultado'] === 'aprobado') {
            $estadoAnterior = $orden->estado;
            $orden->update([
                'estado'     => 'lista_entrega',
                'updated_by' => $request->user()->email,
            ]);
            TallerOrdenHistorial::create([
                'orden_id'       => $orden->id,
                'estado_anterior'=> $estadoAnterior,
                'estado_nuevo'   => 'lista_entrega',
                'descripcion'    => 'Control de calidad aprobado',
                'usuario_id'     => $request->user()->id,
                'created_at'     => now(),
                'created_by'     => $request->user()->email,
            ]);
        }

        return back()->with('status', 'Control de calidad registrado.');
    }

    // ── Entrega ───────────────────────────────────────────────────────────────

    public function storeEntrega(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if ($orden->entrega()->exists()) {
            return back()->withErrors(['entrega' => 'Esta orden ya tiene un acta de entrega registrada.']);
        }

        $data = $request->validate([
            'entregado_a_id'     => ['nullable', 'integer'],
            'documento_recibido' => ['nullable', 'string', 'max:100'],
            'observacion'        => ['nullable', 'string'],
            'fecha_entrega'      => ['nullable', 'date'],
        ]);

        TallerEntrega::create([
            ...$data,
            'orden_id'           => $orden->id,
            'usuario_entrega_id' => $request->user()->id,
            'fecha_entrega'      => $data['fecha_entrega'] ?? now(),
            'estado'             => 'entregado',
            'created_at'         => now(),
            'created_by'         => $request->user()->email,
        ]);

        $estadoAnterior = $orden->estado;
        $orden->update([
            'estado'     => 'entregada',
            'updated_by' => $request->user()->email,
        ]);

        TallerOrdenHistorial::create([
            'orden_id'       => $orden->id,
            'estado_anterior'=> $estadoAnterior,
            'estado_nuevo'   => 'entregada',
            'descripcion'    => 'Acta de entrega registrada' . ($data['documento_recibido'] ? ' — Doc: ' . $data['documento_recibido'] : ''),
            'usuario_id'     => $request->user()->id,
            'created_at'     => now(),
            'created_by'     => $request->user()->email,
        ]);

        return back()->with('status', 'Entrega registrada correctamente.');
    }

    // ── Facturación ───────────────────────────────────────────────────────────

    public function storeFacturacion(Request $request, TallerOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if ($orden->facturacion()->exists()) {
            return back()->withErrors(['facturacion' => 'Esta orden ya tiene un registro de facturación.']);
        }

        $data = $request->validate([
            'tipo_facturacion' => ['required', 'string', 'in:' . implode(',', array_keys(TallerFacturacion::TIPOS))],
            'observacion'      => ['nullable', 'string'],
        ]);

        TallerFacturacion::create([
            ...$data,
            'taller_id'   => $orden->taller_id,
            'orden_id'    => $orden->id,
            'compania_id' => $orden->compania_id,
            'cliente_id'  => $orden->cliente_id,
            'fecha'       => now()->toDateString(),
            'subtotal'    => $orden->subtotal,
            'descuento'   => $orden->descuento,
            'impuesto'    => $orden->impuesto,
            'total'       => $orden->total,
            'pagado'      => 0,
            'saldo'       => $orden->total,
            'estado_cxc'  => 'pendiente',
            'estado_fel'  => 'pendiente',
            'created_by'  => $request->user()->email,
        ]);

        $estadoAnterior = $orden->estado;
        $orden->update([
            'estado'     => 'facturada',
            'updated_by' => $request->user()->email,
        ]);

        TallerOrdenHistorial::create([
            'orden_id'       => $orden->id,
            'estado_anterior'=> $estadoAnterior,
            'estado_nuevo'   => 'facturada',
            'descripcion'    => 'Facturación registrada — ' . (TallerFacturacion::TIPOS[$data['tipo_facturacion']] ?? $data['tipo_facturacion']),
            'usuario_id'     => $request->user()->id,
            'created_at'     => now(),
            'created_by'     => $request->user()->email,
        ]);

        return back()->with('status', 'Facturación registrada correctamente.');
    }

    // ── Helper privado ────────────────────────────────────────────────────────

    private function recalcularTotales(TallerOrden $orden): void
    {
        $totalServicios = $orden->servicios()->whereNotIn('estado', ['anulado'])->sum('total');
        $totalManoObra  = $orden->manoObra()->where('facturable', true)->sum('precio_total');
        $totalRepuestos = $orden->repuestos()->whereNotIn('estado', ['anulado', 'devuelto'])->sum('total');

        $subtotal = round($totalServicios + $totalManoObra + $totalRepuestos, 2);

        $orden->update([
            'subtotal' => $subtotal,
            'total'    => $subtotal,
            'saldo'    => $subtotal,
        ]);
    }
}
