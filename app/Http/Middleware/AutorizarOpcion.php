<?php

namespace App\Http\Middleware;

use App\Support\OpcionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforcement del modelo de permisos "por opción × acción".
 *
 * Para cada ruta del panel admin resuelve la opción y la acción (OpcionResolver)
 * y exige el permiso "{opcion}.{accion}". El Gate::before ya aplica el bypass de
 * super_admin, el modo solo-lectura de la compañía 1 y los permisos denegados,
 * así que aquí solo se consulta $user->can().
 *
 * Rutas sin opción asociada (helpers, adjuntos) NO se restringen aquí: las
 * resuelve su propio controlador. Es seguro montarlo encima del enforcement
 * viejo durante la migración (no reduce acceso a roles ya migrados).
 */
class AutorizarOpcion
{
    public function handle(Request $request, Closure $next): Response
    {
        $ruta = $request->route()?->getName();
        $mapa = OpcionResolver::resolver($ruta, $request->method());

        if ($mapa === null) {
            return $next($request);
        }

        // Sin usuario: deja que el middleware 'auth' redirija al login (no es
        // un 403, es un no-autenticado).
        if (! $request->user()) {
            return $next($request);
        }

        $permiso = $mapa['clave'].'.'.$mapa['accion'];

        if (! $request->user()->can($permiso)) {
            abort(403, 'No tiene el permiso requerido: '.$permiso);
        }

        return $next($request);
    }
}
