<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\ContactCuentaBancaria;
use App\Models\ContactDireccion;
use App\Models\ContactPersonaContacto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactoExtController extends Controller
{
    use ConCompaniaActiva;

    public function show(Request $request, Contacto $contacto): View
    {
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);
        $contacto->load('tipos');

        $cuentasBancarias = ContactCuentaBancaria::where('contacto_id', $contacto->id)->orderByDesc('principal')->get();
        $direcciones      = ContactDireccion::where('contacto_id', $contacto->id)->orderByDesc('principal')->get();
        $personas         = ContactPersonaContacto::where('contacto_id', $contacto->id)->orderByDesc('principal')->get();

        return view('admin.contactos.show', compact('contacto', 'cuentasBancarias', 'direcciones', 'personas'));
    }

    // ── Cuentas bancarias ────────────────────────────────────────────────────

    public function storeCuentaBancaria(Request $request, Contacto $contacto): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.editar') || $request->user()->can('contactos.crear'), 403);
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'banco'         => ['required', 'string', 'max:100'],
            'numero_cuenta' => ['required', 'string', 'max:50'],
            'tipo_cuenta'   => ['nullable', Rule::in(['CORRIENTE', 'AHORROS', 'OTRO'])],
            'principal'     => ['boolean'],
        ]);

        if (! empty($data['principal'])) {
            ContactCuentaBancaria::where('contacto_id', $contacto->id)->update(['principal' => false]);
        }

        ContactCuentaBancaria::create([
            'contacto_id'   => $contacto->id,
            'banco'         => $data['banco'],
            'numero_cuenta' => $data['numero_cuenta'],
            'tipo_cuenta'   => $data['tipo_cuenta'] ?? null,
            'principal'     => $data['principal'] ?? false,
            'created_by'    => $request->user()->email,
        ]);

        return back()->with('status', 'Cuenta bancaria agregada.');
    }

    public function destroyCuentaBancaria(Request $request, Contacto $contacto, ContactCuentaBancaria $cuenta): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.editar'), 403);
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($cuenta->contacto_id === $contacto->id, 404);

        $cuenta->delete();

        return back()->with('status', 'Cuenta bancaria eliminada.');
    }

    // ── Direcciones ──────────────────────────────────────────────────────────

    public function storeDireccion(Request $request, Contacto $contacto): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.editar') || $request->user()->can('contactos.crear'), 403);
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'tipo'      => ['required', Rule::in(['PRINCIPAL', 'FACTURACION', 'ENTREGA', 'OTRO'])],
            'direccion' => ['required', 'string'],
            'pais'      => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'distrito'  => ['nullable', 'string', 'max:100'],
            'principal' => ['boolean'],
        ]);

        if (! empty($data['principal'])) {
            ContactDireccion::where('contacto_id', $contacto->id)->update(['principal' => false]);
        }

        ContactDireccion::create(['contacto_id' => $contacto->id, ...$data, 'created_by' => $request->user()->email]);

        return back()->with('status', 'Dirección agregada.');
    }

    public function destroyDireccion(Request $request, Contacto $contacto, ContactDireccion $direccion): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.editar'), 403);
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($direccion->contacto_id === $contacto->id, 404);

        $direccion->delete();

        return back()->with('status', 'Dirección eliminada.');
    }

    // ── Personas de contacto ─────────────────────────────────────────────────

    public function storePersona(Request $request, Contacto $contacto): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.editar') || $request->user()->can('contactos.crear'), 403);
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre'    => ['required', 'string', 'max:200'],
            'cargo'     => ['nullable', 'string', 'max:100'],
            'email'     => ['nullable', 'string', 'email', 'max:150'],
            'telefono'  => ['nullable', 'string', 'max:50'],
            'principal' => ['boolean'],
        ]);

        if (! empty($data['principal'])) {
            ContactPersonaContacto::where('contacto_id', $contacto->id)->update(['principal' => false]);
        }

        ContactPersonaContacto::create(['contacto_id' => $contacto->id, ...$data, 'created_by' => $request->user()->email]);

        return back()->with('status', 'Persona de contacto agregada.');
    }

    public function destroyPersona(Request $request, Contacto $contacto, ContactPersonaContacto $persona): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.editar'), 403);
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($persona->contacto_id === $contacto->id, 404);

        $persona->delete();

        return back()->with('status', 'Persona de contacto eliminada.');
    }
}
