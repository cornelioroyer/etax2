<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class UsuarioCompaniaController extends Controller
{
    /**
     * Usuarios con acceso a la compañía activa.
     */
    public function index(Request $request): View
    {
        $compania = $this->companiaActivaConsulta($request);

        $filas = DB::table('seg_usuarios_roles')
            ->join('users', 'users.id', '=', 'seg_usuarios_roles.model_id')
            ->join('seg_roles', 'seg_roles.id', '=', 'seg_usuarios_roles.rol_id')
            ->where('seg_usuarios_roles.model_type', User::class)
            ->where('seg_usuarios_roles.compania_id', $compania->id)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email', 'users.is_active', 'seg_roles.name as rol']);

        // Accesos GLOBALES (a todas las compañías): solo los administra y ve el
        // super_admin, porque dan acceso a compañías que el admin de compañía no
        // necesariamente controla.
        $usuariosGlobales = $request->user()->is_admin
            ? DB::table('seg_usuarios_roles_globales')
                ->join('users', 'users.id', '=', 'seg_usuarios_roles_globales.user_id')
                ->join('seg_roles', 'seg_roles.id', '=', 'seg_usuarios_roles_globales.rol_id')
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email', 'seg_roles.name as rol'])
            : collect();

        return view('admin.usuarios-compania.index', [
            'usuarios' => $filas,
            'compania' => $compania,
            'roles' => $this->rolesAsignables(),
            'usuariosGlobales' => $usuariosGlobales,
        ]);
    }

    /**
     * Otorga un rol GLOBAL (compania_id NULL): aplica en todas las compañías,
     * presentes y futuras. Solo super_admin (da acceso transversal).
     */
    public function storeGlobal(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'rol' => ['required', 'in:'.$this->rolesAsignables()->pluck('name')->implode(',')],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return back()->withErrors([
                'email_global' => 'El usuario no existe. Créalo primero dándole acceso a una compañía.',
            ])->withInput();
        }

        $rolId = DB::table('seg_roles')->whereNull('compania_id')->where('name', $data['rol'])->value('id');

        // Un solo rol global por usuario: se reemplaza el anterior.
        DB::table('seg_usuarios_roles_globales')->where('user_id', $user->id)->delete();
        DB::table('seg_usuarios_roles_globales')->insert([
            'user_id' => $user->id,
            'rol_id' => $rolId,
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->olvidarPermisosGlobales();

        return redirect()->route('admin.usuarios-compania.index')
            ->with('status', "Acceso GLOBAL (todas las compañías) otorgado a {$user->email}.");
    }

    /**
     * Quita el acceso global de un usuario.
     */
    public function destroyGlobal(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        DB::table('seg_usuarios_roles_globales')->where('user_id', $user->id)->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->olvidarPermisosGlobales();

        $reinstaurado = $user->garantizarRolMinimo();

        return redirect()->route('admin.usuarios-compania.index')
            ->with('status', $reinstaurado
                ? "Acceso global de {$user->email} eliminado. Quedaba sin roles, así que se le reasignó el rol base «Usuario» en la compañía por defecto."
                : "Acceso global de {$user->email} eliminado.");
    }

    /**
     * Catálogo de roles globales que se pueden asignar a un usuario en una compañía.
     * Se administra en el módulo de Roles (RoleController).
     */
    private function rolesAsignables()
    {
        return Role::query()
            ->whereNull('compania_id')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'descripcion']);
    }

    /**
     * Da acceso a un usuario (existente por email, o lo crea) a la compañía activa.
     */
    public function store(Request $request): RedirectResponse
    {
        $compania = $this->companiaActiva($request);

        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'rol' => ['required', 'in:'.$this->rolesAsignables()->pluck('name')->implode(',')],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            if (empty($data['name']) || empty($data['password'])) {
                return back()->withErrors([
                    'email' => 'El usuario no existe. Para crearlo indica nombre y contraseña.',
                ])->withInput();
            }

            $user = (new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]))->forceFill([
                'is_admin' => false,
                'is_active' => true,
            ]);
            $user->save();
        }

        // El team activo ya es la compañía actual (middleware), el rol se asigna en ella.
        $user->syncRoles([$data['rol']]);

        return redirect()->route('admin.usuarios-compania.index')
            ->with('status', "Acceso otorgado a {$user->email} en {$compania->nombre}.");
    }

    /**
     * Cambia el rol de un usuario en la compañía activa.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $compania = $this->companiaActiva($request);
        $this->asegurarUsuarioEnCompania($user, $compania);

        $data = $request->validate([
            'rol' => ['required', 'in:'.$this->rolesAsignables()->pluck('name')->implode(',')],
        ]);

        if ($user->is($request->user())) {
            return back()->withErrors(['rol' => 'No puedes cambiar tu propio rol.']);
        }

        $user->syncRoles([$data['rol']]);

        return redirect()->route('admin.usuarios-compania.index')->with('status', 'Rol actualizado.');
    }

    /**
     * Quita el acceso de un usuario a la compañía activa.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $compania = $this->companiaActiva($request);
        $this->asegurarUsuarioEnCompania($user, $compania);

        if ($user->is($request->user())) {
            return back()->withErrors(['usuario' => 'No puedes quitarte tu propio acceso.']);
        }

        DB::table('seg_usuarios_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->where('compania_id', $compania->id)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $reinstaurado = $user->garantizarRolMinimo();

        return redirect()->route('admin.usuarios-compania.index')
            ->with('status', $reinstaurado
                ? "Acceso de {$user->email} a {$compania->nombre} eliminado. Quedaba sin roles, así que se le reasignó el rol base «Usuario» en la compañía por defecto."
                : "Acceso de {$user->email} a {$compania->nombre} eliminado.");
    }

    /**
     * Muestra la pantalla para editar los permisos extra de un usuario en la compañía.
     */
    public function editarPermisos(Request $request, User $user): View
    {
        $compania = $this->companiaActiva($request);
        $this->asegurarUsuarioEnCompania($user, $compania);

        // Permisos que tiene el usuario por su rol en esta compañía
        $rolNombre = DB::table('seg_usuarios_roles')
            ->join('seg_roles', 'seg_roles.id', '=', 'seg_usuarios_roles.rol_id')
            ->where('seg_usuarios_roles.model_type', User::class)
            ->where('seg_usuarios_roles.model_id', $user->id)
            ->where('seg_usuarios_roles.compania_id', $compania->id)
            ->value('seg_roles.name');

        $permisosDelRol = $rolNombre
            ? DB::table('seg_roles_permisos')
                ->join('seg_roles', 'seg_roles.id', '=', 'seg_roles_permisos.rol_id')
                ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_roles_permisos.permiso_id')
                ->where('seg_roles.name', $rolNombre)
                ->pluck('seg_permisos.name')
                ->all()
            : [];

        $permisosDirectos = DB::table('seg_usuarios_permisos')
            ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_usuarios_permisos.permiso_id')
            ->where('seg_usuarios_permisos.model_type', User::class)
            ->where('seg_usuarios_permisos.model_id', $user->id)
            ->where('seg_usuarios_permisos.compania_id', $compania->id)
            ->pluck('seg_permisos.name')
            ->all();

        // Permisos del rol DENEGADOS puntualmente a este usuario (override negativo).
        $permisosDenegados = DB::table('seg_usuarios_permisos_denegados')
            ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_usuarios_permisos_denegados.permiso_id')
            ->where('seg_usuarios_permisos_denegados.model_type', User::class)
            ->where('seg_usuarios_permisos_denegados.model_id', $user->id)
            ->where('seg_usuarios_permisos_denegados.compania_id', $compania->id)
            ->pluck('seg_permisos.name')
            ->all();

        // Matriz por opción × acción (excluye solo_admin y reservados de
        // plataforma: un admin de compañía no debe otorgarlos ni denegarlos).
        return view('admin.usuarios-compania.permisos', [
            'usuario'          => $user,
            'compania'         => $compania,
            'rolNombre'        => $rolNombre,
            'permisosDelRol'   => $permisosDelRol,
            'permisosDirectos' => $permisosDirectos,
            'permisosDenegados'=> $permisosDenegados,
            'matriz'           => \App\Support\MatrizPermisos::grupos(),
        ]);
    }

    /**
     * Sincroniza los permisos directos del usuario en la compañía (sin tocar los del rol).
     */
    public function actualizarPermisos(Request $request, User $user): RedirectResponse
    {
        $compania = $this->companiaActiva($request);
        $this->asegurarUsuarioEnCompania($user, $compania);

        if ($user->is($request->user())) {
            return back()->withErrors(['permisos' => 'No puedes editar tus propios permisos.']);
        }

        $data = $request->validate([
            'permisos'    => ['nullable', 'array'],
            'permisos.*'  => ['integer', 'exists:seg_permisos,id'],
            'denegados'   => ['nullable', 'array'],
            'denegados.*' => ['integer', 'exists:seg_permisos,id'],
        ]);

        // Defensa de servidor: descarta cualquier permiso reservado de plataforma
        // aunque venga manipulado en el POST (no confiar solo en ocultarlo en la
        // vista). Un admin de compañía nunca puede otorgar/denegar estos permisos.
        $idsReservados = DB::table('seg_permisos')
            ->whereIn('name', array_merge(Role::PERMISOS_RESERVADOS, \App\Support\MatrizPermisos::RESERVADOS))
            ->pluck('id')
            ->all();

        $denegadosSeleccionados = array_values(array_diff(
            array_unique($data['denegados'] ?? []),
            $idsReservados
        ));
        // Un permiso no puede ser a la vez extra y denegado: la denegación manda.
        $permisosSeleccionados = array_values(array_diff(
            array_unique($data['permisos'] ?? []),
            $denegadosSeleccionados,
            $idsReservados
        ));

        // Fix de seguridad (auditoría 2026-06-29 #2): nadie puede CONCEDER un
        // permiso que él mismo no posee (escalada intra-tenant). Acota los
        // permisos extra a la intersección con los permisos efectivos del
        // otorgante en esta compañía. El super_admin queda exento (su can()
        // resuelve todo vía el bypass del Gate). Las denegaciones NO se acotan:
        // solo reducen privilegio, no escalan.
        $otorgante = $request->user();
        if (! $otorgante->is_admin && $permisosSeleccionados) {
            $nombrePorId = DB::table('seg_permisos')
                ->whereIn('id', $permisosSeleccionados)
                ->pluck('name', 'id');

            $permisosSeleccionados = array_values(array_filter(
                $permisosSeleccionados,
                fn ($id) => isset($nombrePorId[$id]) && $otorgante->can($nombrePorId[$id])
            ));
        }

        DB::transaction(function () use ($user, $compania, $permisosSeleccionados, $denegadosSeleccionados) {
            // Permisos directos extra (más allá del rol).
            DB::table('seg_usuarios_permisos')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->where('compania_id', $compania->id)
                ->delete();

            foreach ($permisosSeleccionados as $permisoId) {
                DB::table('seg_usuarios_permisos')->insert([
                    'permiso_id'  => $permisoId,
                    'model_type'  => User::class,
                    'model_id'    => $user->id,
                    'compania_id' => $compania->id,
                ]);
            }

            // Permisos del rol denegados puntualmente (override negativo).
            DB::table('seg_usuarios_permisos_denegados')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->where('compania_id', $compania->id)
                ->delete();

            foreach ($denegadosSeleccionados as $permisoId) {
                DB::table('seg_usuarios_permisos_denegados')->insert([
                    'permiso_id'  => $permisoId,
                    'model_type'  => User::class,
                    'model_id'    => $user->id,
                    'compania_id' => $compania->id,
                ]);
            }
        });

        // Refresca caches de permisos (Spatie y memoización de denegados).
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->olvidarPermisosDenegados();

        return redirect()->route('admin.usuarios-compania.index')
            ->with('status', "Permisos de {$user->email} actualizados.");
    }

    /**
     * Blindaje multiempresa: el usuario objetivo de una operación de gestión
     * (cambiar rol, editar permisos, quitar acceso) DEBE pertenecer ya a la
     * compañía activa, es decir, tener al menos un rol en ella. Sin esto, un
     * admin de compañía podría manipular —usando el id que viaja en la URL—
     * a CUALQUIER usuario del sistema sobre su propia compañía (otorgarle
     * acceso, cambiarle el rol o quitárselo). El alta (store) queda fuera
     * porque ahí incorporar a un usuario nuevo es justamente la intención.
     */
    private function asegurarUsuarioEnCompania(User $user, $compania): void
    {
        $perteneceACompania = DB::table('seg_usuarios_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->where('compania_id', $compania->id)
            ->exists();

        abort_unless($perteneceACompania, 403, 'El usuario no pertenece a esta compañía.');
    }

    /**
     * Compañía activa, validando que el usuario tenga acceso (solo consulta).
     */
    private function companiaActivaConsulta(Request $request)
    {
        $companiaId = session('compania_activa_id');

        abort_if(! $companiaId, 404, 'No hay compañía activa.');

        $compania = $request->user()->companiasAccesibles()->firstWhere('id', $companiaId);

        abort_if(! $compania, 403);

        return $compania;
    }

    /**
     * Compañía activa, validando que el usuario la administre.
     */
    private function companiaActiva(Request $request)
    {
        $companiaId = session('compania_activa_id');

        abort_if(! $companiaId, 404, 'No hay compañía activa.');

        $compania = $request->user()->companiasAdministradas()->firstWhere('id', $companiaId);

        abort_if(! $compania, 403, 'No administras esta compañía.');

        return $compania;
    }
}
