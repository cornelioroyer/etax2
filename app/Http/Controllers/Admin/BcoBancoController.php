<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BcoBanco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BcoBancoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'max:30', 'unique:bco_bancos,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        BcoBanco::create([
            'codigo'     => $data['codigo'] ?? null,
            'nombre'     => $data['nombre'],
            'activo'     => true,
            'created_by' => $request->user()->email,
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', "Banco {$data['nombre']} registrado.");
    }

    public function update(Request $request, BcoBanco $banco): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'max:30', "unique:bco_bancos,codigo,{$banco->id}"],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        $banco->update([
            'codigo'     => $data['codigo'] ?? null,
            'nombre'     => $data['nombre'],
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', "Banco {$banco->nombre} actualizado.");
    }

    public function toggle(Request $request, BcoBanco $banco): RedirectResponse
    {
        $banco->update(['activo' => ! $banco->activo, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Banco {$banco->nombre} " . ($banco->activo ? 'activado' : 'desactivado') . '.');
    }
}
