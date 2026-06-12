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

        abort_unless(
            $request->user()->companiasAccesibles()->contains('id', (int) $data['compania_id']),
            403
        );

        session(['compania_activa_id' => (int) $data['compania_id']]);

        return redirect()->route('dashboard');
    }
}
