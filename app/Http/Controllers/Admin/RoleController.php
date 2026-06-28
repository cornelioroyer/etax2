<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo de roles GLOBALES (seg_roles con compania_id = null), administrable solo
 * por super_admin (middleware 'admin'). Los roles se asignan a los usuarios por
 * compañía desde "Accesos por compañía" (UsuarioCompaniaController).
 *
 * No expone los permisos reservados de plataforma (Role::PERMISOS_RESERVADOS) ni
 * permite borrar/renombrar los roles base (Role::PROTEGIDOS).
 */
class RoleController extends Controller
{
    /**
     * Listado de roles globales con su conteo de usuarios y permisos.
     */
    public function index(): View
    {
        $roles = Role::query()
            ->whereNull('compania_id')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->withCount('permissions')
            ->get();

        // Cuántos usuarios tienen cada rol (en cualquier compañía).
        $usuariosPorRol = DB::table('seg_usuarios_roles')
            ->where('model_type', User::class)
            ->select('rol_id', DB::raw('count(*) as total'))
            ->groupBy('rol_id')
            ->pluck('total', 'rol_id');

        return view('admin.roles.index', [
            'roles'          => $roles,
            'usuariosPorRol' => $usuariosPorRol,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'grupos'             => $this->catalogoPermisos(),
            'permisosDelRol'     => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        $nombre = $this->nombreTecnico($data['name']);

        if ($this->nombreEnUso($nombre)) {
            return back()->withErrors(['name' => 'Ya existe un rol con ese nombre.'])->withInput();
        }

        $permisos = $this->permisosAdministrables($data['permisos'] ?? []);

        $this->enContextoGlobal(function () use ($nombre, $data, $permisos) {
            $rol = Role::create([
                'name'        => $nombre,
                'descripcion' => $data['descripcion'] ?? null,
                'guard_name'  => 'web',
            ]);
            $rol->syncPermissions($permisos);
        });

        return redirect()->route('admin.roles.index')
            ->with('status', "Rol «{$nombre}» creado.");
    }

    public function edit(Role $role): View
    {
        abort_unless($this->esGlobal($role), 404);

        return view('admin.roles.edit', [
            'role'           => $role,
            'grupos'         => $this->catalogoPermisos(),
            'permisosDelRol' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($this->esGlobal($role), 404);

        $data = $this->validar($request, $role);
        $permisos = $this->permisosAdministrables($data['permisos'] ?? []);

        // Nunca tocar los permisos reservados que el rol ya pudiera tener: se
        // preservan aunque no aparezcan en la matriz.
        $reservadosVigentes = array_intersect(
            $role->permissions->pluck('name')->all(),
            Role::PERMISOS_RESERVADOS
        );
        $permisosFinales = array_values(array_unique(array_merge($permisos, $reservadosVigentes)));

        // El nombre de los roles protegidos es inmutable (referenciado en código).
        $nombre = $role->name;
        if (! $role->esProtegido()) {
            $nombre = $this->nombreTecnico($data['name']);
            if ($nombre !== $role->name && $this->nombreEnUso($nombre)) {
                return back()->withErrors(['name' => 'Ya existe un rol con ese nombre.'])->withInput();
            }
        }

        $this->enContextoGlobal(function () use ($role, $nombre, $data, $permisosFinales) {
            $role->update([
                'name'        => $nombre,
                'descripcion' => $data['descripcion'] ?? null,
            ]);
            $role->syncPermissions($permisosFinales);
        });

        return redirect()->route('admin.roles.index')->with('status', 'Rol actualizado.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless($this->esGlobal($role), 404);

        if ($role->esProtegido()) {
            return back()->withErrors(['rol' => 'Los roles base del sistema no se pueden eliminar.']);
        }

        $enUso = DB::table('seg_usuarios_roles')->where('rol_id', $role->id)->exists();
        if ($enUso) {
            return back()->withErrors(['rol' => 'No se puede eliminar: el rol está asignado a uno o más usuarios. Reasígnalos primero.']);
        }

        $this->enContextoGlobal(fn () => $role->delete());

        return redirect()->route('admin.roles.index')->with('status', 'Rol eliminado.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validar(Request $request, ?Role $role = null): array
    {
        $reglas = [
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos'    => ['nullable', 'array'],
            'permisos.*'  => ['string', Rule::exists('seg_permisos', 'name')],
        ];

        // El nombre solo se valida/edita en roles no protegidos.
        if (! $role || ! $role->esProtegido()) {
            $reglas['name'] = ['required', 'string', 'max:100'];
        }

        return $request->validate($reglas);
    }

    /** Normaliza el nombre visible a la clave técnica (minúsculas con guion bajo). */
    private function nombreTecnico(string $nombre): string
    {
        return Str::slug($nombre, '_');
    }

    private function nombreEnUso(string $nombre): bool
    {
        return Role::query()
            ->whereNull('compania_id')
            ->where('guard_name', 'web')
            ->where('name', $nombre)
            ->exists();
    }

    /** Deja solo permisos del catálogo administrable (excluye los reservados). */
    private function permisosAdministrables(array $nombres): array
    {
        return array_values(array_diff(array_unique($nombres), Role::PERMISOS_RESERVADOS));
    }

    /**
     * Prefijos de permiso que se muestran reunidos bajo el encabezado lógico
     * "Seguridad" en la matriz de roles (agrupación solo visual; no son permisos
     * nuevos ni cambian el modelo de seguridad).
     */
    private const GRUPO_SEGURIDAD = ['usuarios_compania', 'respaldos'];

    /** Permisos agrupados por módulo, sin los reservados de plataforma. */
    private function catalogoPermisos(): array
    {
        $permisos = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        $grupos = [];
        foreach ($permisos as $p) {
            if (in_array($p->name, Role::PERMISOS_RESERVADOS, true)) {
                continue;
            }
            $prefijo = explode('.', $p->name)[0];
            $modulo = in_array($prefijo, self::GRUPO_SEGURIDAD, true) ? 'seguridad' : $prefijo;
            $grupos[$modulo][] = $p;
        }
        ksort($grupos);

        return $grupos;
    }

    private function esGlobal(Role $role): bool
    {
        return $role->compania_id === null && $role->guard_name === 'web';
    }

    /**
     * Ejecuta una operación en el contexto de equipo NULL para que los roles se
     * creen/consulten como globales y no atados a la compañía activa.
     */
    private function enContextoGlobal(callable $fn): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previo = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        try {
            return $fn();
        } finally {
            $registrar->setPermissionsTeamId($previo);
            $registrar->forgetCachedPermissions();
        }
    }
}
