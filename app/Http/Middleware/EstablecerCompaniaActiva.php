<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EstablecerCompaniaActiva
{
    /**
     * Fija la compañía activa del usuario en sesión y como "team" de
     * spatie/permission, para que roles y permisos se evalúen por compañía.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $companias = $user->companiasAccesibles();

            $activaId = session('compania_activa_id');

            if (! $activaId || ! $companias->contains('id', $activaId)) {
                // Preferencia persistida del usuario; si ya no tiene acceso, primera accesible.
                $ultima = $user->ultima_compania_id;

                $activaId = ($ultima && $companias->contains('id', $ultima))
                    ? $ultima
                    : $companias->first()?->id;

                session(['compania_activa_id' => $activaId]);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($activaId);

            View::share('companiasDisponibles', $companias);
            View::share('companiaActiva', $companias->firstWhere('id', $activaId));
        }

        return $next($request);
    }
}
