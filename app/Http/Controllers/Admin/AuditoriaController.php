<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\AuditActividad;
use App\Models\Compania;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bitácora de actividad de usuarios. Solo super_admin (middleware 'admin'):
 * lista qué hizo cada usuario —crear/editar/eliminar de cualquier módulo más
 * login/logout— con filtros, detalle del cambio (antes/después) y export.
 */
class AuditoriaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'usuario_id' => ['nullable', 'integer'],
            'evento' => ['nullable', 'string', 'max:30'],
            'entidad' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $desde = ! empty($filtros['desde'])
            ? Carbon::parse($filtros['desde'])->startOfDay()
            : now()->startOfMonth();
        $hasta = ! empty($filtros['hasta'])
            ? Carbon::parse($filtros['hasta'])->endOfDay()
            : now()->endOfDay();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        $query = AuditActividad::query()
            ->with('usuario')
            // Acotada a la compañía activa; los eventos sin compañía (login/
            // logout/acceso fallido, que son de plataforma) se incluyen siempre.
            ->where(fn ($q) => $q->where('compania_id', $companiaId)->orWhereNull('compania_id'))
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($filtros['usuario_id'] ?? null, fn ($q, $v) => $q->where('usuario_id', $v))
            ->when($filtros['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->when($filtros['entidad'] ?? null, fn ($q, $v) => $q->where('entidad', $v))
            ->when($filtros['q'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('descripcion', 'like', "%{$v}%")
                    ->orWhere('usuario_nombre', 'like', "%{$v}%");
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // Catálogos para los selectores y el export.
        $usuarios = User::orderBy('name')->get(['id', 'name', 'email']);
        $entidades = AuditActividad::query()
            ->where('compania_id', $companiaId)
            ->whereNotNull('entidad')
            ->distinct()
            ->orderBy('entidad')
            ->pluck('entidad');
        $companiaActiva = Compania::find($companiaId);

        $datos = [
            'desde' => $desde,
            'hasta' => $hasta,
            'filtros' => $filtros,
            'usuarios' => $usuarios,
            'entidades' => $entidades,
            'companiaActiva' => $companiaActiva,
            'etiquetas' => AuditActividad::ETIQUETAS,
            'generado' => now(),
            'usuario' => $request->user()->name ?: $request->user()->email,
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.auditoria',
            $datos + ['registros' => (clone $query)->limit(5000)->get()],
            'auditoria_'.$desde->format('Ymd').'_'.$hasta->format('Ymd'))) {
            return $export;
        }

        return view('admin.auditoria.index', $datos + [
            'registros' => $query->paginate(50)->withQueryString(),
        ]);
    }

    /**
     * Auditoría GLOBAL: actividad de TODAS las compañías, solo super_admin
     * (mismo middleware 'admin'). A diferencia de index() no acota por compañía;
     * agrega filtro y columna de Compañía. Pensada para que el dueño de la
     * plataforma vea qué se hizo en cada cliente.
     */
    public function globalIndex(Request $request): View|Response
    {
        $filtros = $request->validate([
            'usuario_id' => ['nullable', 'array'],
            'usuario_id.*' => ['integer'],
            'usuario_excluir_id' => ['nullable', 'array'],
            'usuario_excluir_id.*' => ['integer'],
            'compania_id' => ['nullable', 'integer'],
            'evento' => ['nullable', 'string', 'max:30'],
            'entidad' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $desde = ! empty($filtros['desde'])
            ? Carbon::parse($filtros['desde'])->startOfDay()
            : now()->startOfMonth();
        $hasta = ! empty($filtros['hasta'])
            ? Carbon::parse($filtros['hasta'])->endOfDay()
            : now()->endOfDay();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        $query = AuditActividad::query()
            ->with('usuario')
            // Sin acotar por compañía: TODAS las compañías.
            ->whereBetween('created_at', [$desde, $hasta])
            ->when($filtros['compania_id'] ?? null, fn ($q, $v) => $q->where('compania_id', $v))
            // Incluir uno o varios usuarios.
            ->when($filtros['usuario_id'] ?? null, fn ($q, $v) => $q->whereIn('usuario_id', array_map('intval', $v)))
            // Excluir uno o varios usuarios: conserva también los eventos sin
            // usuario (logins fallidos), que también son "distintos a X".
            ->when($filtros['usuario_excluir_id'] ?? null, fn ($q, $v) => $q->where(
                fn ($w) => $w->whereNotIn('usuario_id', array_map('intval', $v))->orWhereNull('usuario_id')
            ))
            ->when($filtros['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->when($filtros['entidad'] ?? null, fn ($q, $v) => $q->where('entidad', $v))
            ->when($filtros['q'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('descripcion', 'like', "%{$v}%")
                    ->orWhere('usuario_nombre', 'like', "%{$v}%");
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $companias = Compania::orderBy('nombre')->get(['id', 'nombre']);

        $datos = [
            'desde' => $desde,
            'hasta' => $hasta,
            'filtros' => $filtros,
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'email']),
            'entidades' => AuditActividad::whereNotNull('entidad')->distinct()->orderBy('entidad')->pluck('entidad'),
            'companias' => $companias,
            'companiasMap' => $companias->pluck('nombre', 'id'),
            'etiquetas' => AuditActividad::ETIQUETAS,
            'generado' => now(),
            'usuario' => $request->user()->name ?: $request->user()->email,
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.auditoria-global',
            $datos + ['registros' => (clone $query)->limit(5000)->get()],
            'auditoria_global_'.$desde->format('Ymd').'_'.$hasta->format('Ymd'))) {
            return $export;
        }

        return view('admin.auditoria.global', $datos + [
            'registros' => $query->paginate(50)->withQueryString(),
        ]);
    }

    public function show(Request $request, AuditActividad $actividad): View
    {
        $actividad->load('usuario');
        $compania = $actividad->compania_id ? Compania::find($actividad->compania_id) : null;

        return view('admin.auditoria.show', [
            'registro' => $actividad,
            'compania' => $compania,
        ]);
    }
}
