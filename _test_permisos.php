<?php
// Prueba de permisos por usuario-compañía (ejecutar con: php artisan tinker _test_permisos.php)
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$ctrl = app(\App\Http\Controllers\Admin\UsuarioCompaniaController::class);

// Admin de la compañía 1 y otro usuario con acceso
$rolAdminId = DB::table('seg_roles')->where('name', 'admin_compania')->value('id');
$adminId = DB::table('seg_usuarios_roles')->where('compania_id', 1)->where('rol_id', $rolAdminId)->value('model_id');
$admin = $adminId ? User::find($adminId) : User::where('is_admin', true)->first();
$otroId = DB::table('seg_usuarios_roles')->where('compania_id', 1)->where('model_id', '!=', $admin->id)->value('model_id');
$otro = $otroId ? User::find($otroId) : null;

if (! $otro) {
    echo "SKIP: no hay segundo usuario con acceso a compañía 1\n";
    return;
}

Auth::login($admin);
session(['compania_activa_id' => 1]);
view()->share('errors', new \Illuminate\Support\ViewErrorBag); // en HTTP lo inyecta el middleware

$mkReq = function (string $method, array $data = []) use ($admin) {
    $req = Request::create('/test', $method, $data);
    $req->setUserResolver(fn () => $admin);
    $req->setLaravelSession(app('session.store'));
    return $req;
};

// 1. GET editarPermisos + render de la vista
$view = $ctrl->editarPermisos($mkReq('GET'), $otro);
$html = $view->render();
echo "1. editarPermisos render OK (" . strlen($html) . " bytes), grupos: " . count($view->getData()['grupos']) . "\n";

// Respaldo de permisos directos actuales
$backup = DB::table('seg_usuarios_permisos')
    ->where('model_type', User::class)->where('model_id', $otro->id)->where('compania_id', 1)
    ->pluck('permiso_id')->all();

// 2. PUT con un permiso extra
$permisoId = DB::table('seg_permisos')->where('name', 'bancos.ver')->value('id')
    ?? DB::table('seg_permisos')->value('id');
$ctrl->actualizarPermisos($mkReq('PUT', ['permisos' => [$permisoId]]), $otro);
$tiene = DB::table('seg_usuarios_permisos')
    ->where('model_type', User::class)->where('model_id', $otro->id)
    ->where('compania_id', 1)->where('permiso_id', $permisoId)->exists();
echo "2. actualizarPermisos asigna permiso {$permisoId}: " . ($tiene ? 'OK' : 'FALLO') . "\n";

// 3. PUT vacío = quita todos los directos
$ctrl->actualizarPermisos($mkReq('PUT', []), $otro);
$quedan = DB::table('seg_usuarios_permisos')
    ->where('model_type', User::class)->where('model_id', $otro->id)->where('compania_id', 1)->count();
echo "3. actualizarPermisos vacío deja {$quedan} directos: " . ($quedan === 0 ? 'OK' : 'FALLO') . "\n";

// 4. No puede editarse a sí mismo
$resp = $ctrl->actualizarPermisos($mkReq('PUT', ['permisos' => []]), $admin);
$bloqueado = $resp->getSession()->get('errors')?->has('permisos') ?? false;
echo "4. bloqueo de auto-edición: " . ($bloqueado ? 'OK' : 'FALLO') . "\n";

// Restaurar respaldo
foreach ($backup as $pid) {
    DB::table('seg_usuarios_permisos')->insert([
        'permiso_id' => $pid, 'model_type' => User::class, 'model_id' => $otro->id, 'compania_id' => 1,
    ]);
}
echo "Restaurados " . count($backup) . " permisos directos originales de {$otro->email}\n";
