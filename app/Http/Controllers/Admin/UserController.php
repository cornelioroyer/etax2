<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $ordenables = ['name', 'email', 'is_admin', 'is_active', 'created_at'];
        $sort = in_array($request->query('sort'), $ordenables, true) ? $request->query('sort') : 'created_at';
        $dir = in_array($request->query('dir'), ['asc', 'desc'], true) ? $request->query('dir') : 'desc';

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'sort', 'dir'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = (new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]))->forceFill([
            // is_admin/is_active están guardados (no mass-assignable): se fijan
            // explícito. El flag super_admin solo lo puede otorgar otro super_admin.
            'is_admin' => $request->user()->is_admin ? $request->boolean('is_admin') : false,
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->save();

        // Por defecto, todo usuario nuevo queda en la compañía 1 con rol "usuario".
        $user->asegurarAccesoDefault(1);

        return redirect()->route('admin.users.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Roles del usuario por compañía (más sus roles globales). El flag de
     * plataforma (is_admin) no es el rol real; el rol verdadero es por compañía
     * en seg_usuarios_roles. Pantalla de administración para super_admin
     * (toda la sección users/* va bajo middleware 'admin').
     */
    public function roles(User $user): View
    {
        // Un usuario puede tener VARIOS roles en la misma compañía: se agrupan
        // por compañía y cada grupo lleva su lista de roles.
        $rolesPorCompania = DB::table('seg_usuarios_roles')
            ->join('seg_roles', 'seg_roles.id', '=', 'seg_usuarios_roles.rol_id')
            ->join('core_companias', 'core_companias.id', '=', 'seg_usuarios_roles.compania_id')
            ->where('seg_usuarios_roles.model_type', User::class)
            ->where('seg_usuarios_roles.model_id', $user->id)
            ->orderBy('core_companias.nombre')
            ->orderBy('seg_roles.name')
            ->get(['core_companias.id as compania_id', 'core_companias.nombre as compania', 'seg_roles.name as rol'])
            ->groupBy('compania_id')
            ->map(fn ($grupo) => (object) [
                'compania_id' => $grupo->first()->compania_id,
                'compania'    => $grupo->first()->compania,
                'roles'       => $grupo->pluck('rol')->all(),
            ])
            ->values();

        $rolesGlobales = DB::table('seg_usuarios_roles_globales')
            ->join('seg_roles', 'seg_roles.id', '=', 'seg_usuarios_roles_globales.rol_id')
            ->where('seg_usuarios_roles_globales.user_id', $user->id)
            ->orderBy('seg_roles.name')
            ->pluck('seg_roles.name');

        // Compañías a las que el usuario AÚN no tiene rol asignado (para el alta).
        $idsAsignadas = $rolesPorCompania->pluck('compania_id')->all();
        $companiasDisponibles = Compania::query()
            ->when($idsAsignadas, fn ($q) => $q->whereNotIn('id', $idsAsignadas))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.users.roles', [
            'user'                 => $user,
            'rolesPorCompania'     => $rolesPorCompania,
            'rolesGlobales'        => $rolesGlobales,
            'companiasDisponibles' => $companiasDisponibles,
            'rolesAsignables'      => $this->rolesAsignables(),
        ]);
    }

    /**
     * Agrega un rol a un usuario en UNA compañía. ADITIVO: un usuario puede
     * acumular varios roles en la misma compañía (sus permisos se UNEN). Como
     * aquí el super_admin opera sobre cualquier compañía (no solo la activa), se
     * inserta directo en seg_usuarios_roles —mismo patrón que
     * User::asegurarAccesoDefault— en vez de syncRoles, que actúa sobre el
     * "team" (compañía) activo de Spatie. insertOrIgnore respeta la PK compuesta
     * (compania_id, rol_id, model_id, model_type): re-asignar el mismo rol no
     * duplica ni falla.
     */
    public function asignarRol(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'compania_id' => ['required', 'integer', 'exists:core_companias,id'],
            'rol'         => ['required', Rule::in($this->rolesAsignables()->pluck('name')->all())],
        ]);

        $rolId = DB::table('seg_roles')->whereNull('compania_id')->where('name', $data['rol'])->value('id');

        DB::table('seg_usuarios_roles')->insertOrIgnore([
            'rol_id'      => $rolId,
            'model_type'  => User::class,
            'model_id'    => $user->id,
            'compania_id' => $data['compania_id'],
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.users.roles', $user)->with('status', 'Rol asignado.');
    }

    /**
     * Quita TODOS los roles de un usuario en una compañía (revoca el acceso).
     */
    public function quitarRol(Request $request, User $user, int $compania): RedirectResponse
    {
        DB::table('seg_usuarios_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->where('compania_id', $compania)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $reinstaurado = $user->garantizarRolMinimo();

        return redirect()->route('admin.users.roles', $user)->with('status', $reinstaurado
            ? 'Acceso a la compañía quitado. El usuario quedaba sin roles, así que se le reasignó el rol base «Usuario» en la compañía por defecto.'
            : 'Acceso a la compañía quitado.');
    }

    /**
     * Quita UN rol puntual de un usuario en una compañía, conservando los demás
     * roles que tenga en esa misma compañía.
     */
    public function quitarUnRol(Request $request, User $user, int $compania, string $rol): RedirectResponse
    {
        $rolId = DB::table('seg_roles')->whereNull('compania_id')->where('name', $rol)->value('id');

        if ($rolId) {
            DB::table('seg_usuarios_roles')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->where('compania_id', $compania)
                ->where('rol_id', $rolId)
                ->delete();

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $reinstaurado = $user->garantizarRolMinimo();

        return redirect()->route('admin.users.roles', $user)->with('status', $reinstaurado
            ? 'Rol quitado. El usuario quedaba sin roles, así que se le reasignó el rol base «Usuario» en la compañía por defecto.'
            : 'Rol quitado.');
    }

    /**
     * Pantalla dedicada a las COMPAÑÍAS de un usuario (alta/baja de acceso sin
     * el detalle de roles). "Tener acceso a una compañía" = tener al menos un
     * rol en seg_usuarios_roles con ese compania_id. El detalle fino de roles
     * se administra en la pantalla de Roles.
     */
    public function companias(User $user): View
    {
        $companiasConAcceso = DB::table('seg_usuarios_roles')
            ->join('core_companias', 'core_companias.id', '=', 'seg_usuarios_roles.compania_id')
            ->where('seg_usuarios_roles.model_type', User::class)
            ->where('seg_usuarios_roles.model_id', $user->id)
            ->whereNotNull('seg_usuarios_roles.compania_id')
            ->distinct()
            ->orderBy('core_companias.nombre')
            ->get(['core_companias.id as compania_id', 'core_companias.nombre as compania']);

        // Compañías a las que el usuario AÚN no tiene acceso (para el alta).
        $idsAsignadas = $companiasConAcceso->pluck('compania_id')->all();
        $companiasDisponibles = Compania::query()
            ->when($idsAsignadas, fn ($q) => $q->whereNotIn('id', $idsAsignadas))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.users.companias', [
            'user'                  => $user,
            'companiasConAcceso'    => $companiasConAcceso,
            'companiasDisponibles'  => $companiasDisponibles,
            'tieneAsignacionGlobal' => $user->tieneAsignacionGlobal(),
        ]);
    }

    /**
     * Da acceso a UNA compañía otorgando el rol base "usuario". Mismo patrón
     * que asignarRol/asegurarAccesoDefault: insertOrIgnore respeta la PK
     * compuesta, así que re-agregar no duplica ni falla. Para más permisos, se
     * ajustan los roles en la pantalla de Roles.
     */
    public function agregarCompania(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'compania_id' => ['required', 'integer', 'exists:core_companias,id'],
        ]);

        $rolId = DB::table('seg_roles')->whereNull('compania_id')->where('name', 'usuario')->value('id');

        DB::table('seg_usuarios_roles')->insertOrIgnore([
            'rol_id'      => $rolId,
            'model_type'  => User::class,
            'model_id'    => $user->id,
            'compania_id' => $data['compania_id'],
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.users.companias', $user)
            ->with('status', 'Compañía agregada con el rol «Usuario». Ajusta los roles si necesita más permisos.');
    }

    /**
     * Quita el acceso de un usuario a una compañía: borra TODOS sus roles en
     * ella (mismo efecto que "Quitar acceso" en la pantalla de Roles).
     */
    public function quitarCompania(Request $request, User $user, int $compania): RedirectResponse
    {
        DB::table('seg_usuarios_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->where('compania_id', $compania)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $reinstaurado = $user->garantizarRolMinimo();

        return redirect()->route('admin.users.companias', $user)
            ->with('status', $reinstaurado
                ? 'Compañía quitada del usuario. Quedaba sin roles, así que se le reasignó el rol base «Usuario» en la compañía por defecto.'
                : 'Compañía quitada del usuario.');
    }

    /**
     * Catálogo de roles asignables (definiciones globales del módulo de Roles).
     * Mismo criterio que UsuarioCompaniaController.
     */
    private function rolesAsignables()
    {
        return Role::query()
            ->whereNull('compania_id')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'descripcion']);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);
        $user->forceFill([
            // is_admin/is_active están guardados (no mass-assignable): se fijan
            // explícito. Solo un super_admin puede cambiar el flag super_admin;
            // si no, se conserva el valor actual.
            'is_admin' => $request->user()->is_admin ? $request->boolean('is_admin') : $user->is_admin,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if ($user->is(auth()->user()) && (! $user->is_admin || ! $user->is_active)) {
            return back()->withErrors(['is_admin' => 'No puedes quitarte tu propio acceso administrador.'])->withInput();
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'Usuario actualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'No puedes eliminar tu propio usuario.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Usuario eliminado.');
    }
}
