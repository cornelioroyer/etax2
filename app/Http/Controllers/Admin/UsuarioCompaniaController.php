<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioCompaniaController extends Controller
{
    private const ROLES_ASIGNABLES = ['admin_compania', 'usuario'];

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

        return view('admin.usuarios-compania.index', [
            'usuarios' => $filas,
            'compania' => $compania,
            'roles' => self::ROLES_ASIGNABLES,
        ]);
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
            'rol' => ['required', 'in:'.implode(',', self::ROLES_ASIGNABLES)],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            if (empty($data['name']) || empty($data['password'])) {
                return back()->withErrors([
                    'email' => 'El usuario no existe. Para crearlo indica nombre y contraseña.',
                ])->withInput();
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_admin' => false,
                'is_active' => true,
            ]);
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
        $this->companiaActiva($request);

        $data = $request->validate([
            'rol' => ['required', 'in:'.implode(',', self::ROLES_ASIGNABLES)],
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

        if ($user->is($request->user())) {
            return back()->withErrors(['usuario' => 'No puedes quitarte tu propio acceso.']);
        }

        DB::table('seg_usuarios_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->where('compania_id', $compania->id)
            ->delete();

        return redirect()->route('admin.usuarios-compania.index')
            ->with('status', "Acceso de {$user->email} a {$compania->nombre} eliminado.");
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
