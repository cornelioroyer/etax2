<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompaniaActivaController extends Controller
{
    /**
     * Cambia la compañía activa del usuario (selector de la barra).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'compania_id' => ['required', 'integer'],
        ]);

        $companiaId = (int) $data['compania_id'];
        $user = $request->user();

        abort_unless(
            $user->companiasAccesibles()->contains('id', $companiaId),
            403
        );

        session(['compania_activa_id' => $companiaId]);

        // Recordar la última compañía elegida para próximos inicios de sesión.
        $user->forceFill(['ultima_compania_id' => $companiaId])->save();

        return redirect()->route('dashboard');
    }
}
