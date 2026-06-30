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
        return view('admin.roles.create');
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
            'role' => $role,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($this->esGlobal($role), 404);

        $data = $this->validar($request, $role);

        // El nombre de los roles protegidos es inmutable (referenciado en código).
        $nombre = $role->name;
        if (! $role->esProtegido()) {
            $nombre = $this->nombreTecnico($data['name']);
            if ($nombre !== $role->name && $this->nombreEnUso($nombre)) {
                return back()->withErrors(['name' => 'Ya existe un rol con ese nombre.'])->withInput();
            }
        }

        // La pantalla de edición ya NO incluye la matriz de permisos: solo cambia
        // nombre y descripción. Los permisos solo se sincronizan si la petición los
        // trae explícitamente (vía programática/API); en su ausencia se dejan intactos.
        $this->enContextoGlobal(function () use ($request, $role, $nombre, $data) {
            $role->update([
                'name'        => $nombre,
                'descripcion' => $data['descripcion'] ?? null,
            ]);

            if ($request->has('permisos')) {
                $permisos = $this->permisosAdministrables($data['permisos'] ?? []);

                // Preservar los permisos reservados de plataforma que el rol ya tuviera.
                $reservadosVigentes = array_intersect(
                    $role->permissions->pluck('name')->all(),
                    Role::PERMISOS_RESERVADOS
                );
                $role->syncPermissions(array_values(array_unique(array_merge($permisos, $reservadosVigentes))));
            }
        });

        return redirect()->route('admin.roles.index')->with('status', 'Rol actualizado.');
    }

    /**
     * Matriz de permisos del rol (opción × acción), reutilizando MatrizPermisos.
     */
    public function permisos(Role $role): View
    {
        abort_unless($this->esGlobal($role), 404);

        $matriz = \App\Support\MatrizPermisos::grupos();
        $permisosDelRol = $this->enContextoGlobal(
            fn () => $role->permissions->pluck('name')->all()
        );

        // Permisos que el rol tiene pero que esta matriz NO administra (legacy de
        // módulo, reservados, etc.). Se muestran como informativos y se preservan
        // intactos al guardar: la matriz solo togglea las celdas que muestra.
        $otrosPermisos = array_values(array_diff(
            $permisosDelRol,
            $this->nombresGestionables($matriz)
        ));
        sort($otrosPermisos);

        return view('admin.roles.permisos', [
            'role'           => $role,
            'matriz'         => $matriz,
            'permisosDelRol' => $permisosDelRol,
            'otrosPermisos'  => $otrosPermisos,
        ]);
    }

    /**
     * Sincroniza SOLO los permisos administrados por la matriz (celdas opción.acción
     * visibles). Todo permiso que el rol tenga fuera de la matriz —legacy de módulo,
     * reservados de plataforma, etc.— se preserva intacto: nunca se borra desde aquí.
     */
    public function actualizarPermisos(Request $request, Role $role): RedirectResponse
    {
        abort_unless($this->esGlobal($role), 404);

        $data = $request->validate([
            'permisos'   => ['nullable', 'array'],
            'permisos.*' => ['string', Rule::exists('seg_permisos', 'name')],
        ]);

        $gestionables = $this->nombresGestionables(\App\Support\MatrizPermisos::grupos());

        // De la selección, aceptar solo lo que es realmente una celda de la matriz.
        $seleccionMatriz = array_values(array_intersect(
            $this->permisosAdministrables($data['permisos'] ?? []),
            $gestionables
        ));

        $this->enContextoGlobal(function () use ($role, $seleccionMatriz, $gestionables) {
            $actuales = $role->permissions->pluck('name')->all();
            // Preservar TODO lo que la matriz no administra (legacy, reservados, …).
            $preservados = array_values(array_diff($actuales, $gestionables));
            $role->syncPermissions(array_values(array_unique(array_merge($seleccionMatriz, $preservados))));
        });

        return redirect()->route('admin.roles.permisos.edit', $role)
            ->with('status', 'Permisos del rol actualizados.');
    }

    /** Nombres de permiso que la matriz puede otorgar/quitar (celdas con id, no reservadas). */
    private function nombresGestionables(array $matriz): array
    {
        $names = [];
        foreach ($matriz as $grupo) {
            foreach ($grupo['opciones'] as $op) {
                foreach ($op['acciones'] as $accion) {
                    if (! $accion['reservado'] && $accion['id']) {
                        $names[] = $accion['name'];
                    }
                }
            }
        }

        return $names;
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
        $reservados = array_merge(Role::PERMISOS_RESERVADOS, \App\Support\MatrizPermisos::RESERVADOS);

        return array_values(array_diff(array_unique($nombres), $reservados));
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
