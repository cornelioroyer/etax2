<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\TipoContacto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactoController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $search = trim((string) $request->query('search', ''));
        $tipo = strtoupper(trim((string) $request->query('tipo', '')));

        $contactos = Contacto::query()
            ->with('tipos')
            ->where('compania_id', $companiaId)
            ->when($tipo !== '', function ($query) use ($tipo) {
                $query->whereHas('tipos', fn ($q) => $q->where('codigo', $tipo));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nombre', 'ilike', "%{$search}%")
                        ->orWhere('razon_social', 'ilike', "%{$search}%")
                        ->orWhere('identificacion', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        $tipos = TipoContacto::orderBy('id')->get();

        return view('admin.contactos.index', compact('contactos', 'search', 'tipo', 'tipos'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('contactos.crear'), 403);

        return view('admin.contactos.create', [
            'tipos' => TipoContacto::orderBy('id')->get(),
            'tipoPreseleccionado' => strtoupper(trim((string) $request->query('tipo', ''))),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.crear'), 403);

        $companiaId = $this->companiaActivaId($request);
        $data = $this->validated($request, $companiaId);
        $tipoIds = $data['tipos'];
        unset($data['tipos']);

        $data['compania_id'] = $companiaId;
        $data['created_by'] = $request->user()->email;

        $contacto = Contacto::create($data);
        $contacto->tipos()->sync($tipoIds);

        return redirect()->route('admin.contactos.index')->with('status', 'Contacto creado.');
    }

    public function edit(Request $request, Contacto $contacto): View
    {
        abort_unless($request->user()->can('contactos.editar'), 403);
        $this->verificarCompania($request, $contacto);

        return view('admin.contactos.edit', [
            'contacto' => $contacto->load('tipos'),
            'tipos' => TipoContacto::orderBy('id')->get(),
            'tipoPreseleccionado' => '',
        ]);
    }

    public function update(Request $request, Contacto $contacto): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.editar'), 403);
        $this->verificarCompania($request, $contacto);

        $data = $this->validated($request, $contacto->compania_id, $contacto);
        $tipoIds = $data['tipos'];
        unset($data['tipos']);

        $data['updated_by'] = $request->user()->email;

        $contacto->update($data);
        $contacto->tipos()->sync($tipoIds);

        return redirect()->route('admin.contactos.index')->with('status', 'Contacto actualizado.');
    }

    public function destroy(Request $request, Contacto $contacto): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.eliminar'), 403);
        $this->verificarCompania($request, $contacto);

        if (DB::table('cgl_asientos_detalle')->where('contacto_id', $contacto->id)->exists()) {
            return back()->withErrors(['contacto' => 'No se puede eliminar: el contacto tiene movimientos contables. Desactívalo en su lugar.']);
        }

        $contacto->tipos()->detach();
        $contacto->delete();

        return redirect()->route('admin.contactos.index')->with('status', 'Contacto eliminado.');
    }

    private function validated(Request $request, int $companiaId, ?Contacto $contacto = null): array
    {
        $data = $request->validate([
            'codigo' => [
                'nullable', 'string', 'max:50',
                Rule::unique('contact_contactos')->where('compania_id', $companiaId)->ignore($contacto?->id),
            ],
            'nombre' => ['required', 'string', 'max:200'],
            'razon_social' => ['nullable', 'string', 'max:250'],
            'tipo_persona' => ['required', Rule::in(['NATURAL', 'JURIDICA', 'EXTRANJERO'])],
            'identificacion' => ['nullable', 'string', 'max:50'],
            'dv' => ['nullable', 'string', 'max:5'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
            'tipos' => ['required', 'array', 'min:1'],
            'tipos.*' => ['integer', 'exists:contact_tipos,id'],
        ]);

        // identificacion + dv unicos por compania (cuando se indican)
        if (! empty($data['identificacion'])) {
            $existe = Contacto::where('compania_id', $companiaId)
                ->where('identificacion', $data['identificacion'])
                ->where('dv', $data['dv'] ?? null)
                ->when($contacto, fn ($q) => $q->where('id', '!=', $contacto->id))
                ->exists();

            if ($existe) {
                back()->withErrors(['identificacion' => 'Ya existe un contacto con esta identificación.'])->throwResponse();
            }
        }

        return $data;
    }

    private function companiaActivaId(Request $request): int
    {
        $companiaId = session('compania_activa_id');

        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', $companiaId),
            403
        );

        return (int) $companiaId;
    }

    private function verificarCompania(Request $request, Contacto $contacto): void
    {
        abort_unless($contacto->compania_id === $this->companiaActivaId($request), 404);
    }
}
