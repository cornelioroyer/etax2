<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Zona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompaniaController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $companias = Compania::query()
            ->with('zona')
            ->when(! $request->user()->is_admin, function ($query) use ($request) {
                $query->whereIn('id', $request->user()->companiasAccesibles()->pluck('id'));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nombre', 'ilike', "%{$search}%")
                        ->orWhere('ruc', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.companias.index', compact('companias', 'search'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('companias.crear'), 403);

        $zonas = Zona::orderBy('description')->get();

        return view('admin.companias.create', compact('zonas'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('companias.crear'), 403);

        $data = $this->validated($request);
        $data['fecha_de_expiracion'] = \Illuminate\Support\Carbon::parse($data['fecha_de_apertura'])->addDays(30);
        $data['created_by'] = $request->user()->email;

        $compania = Compania::create($data);

        // Si quien la crea no es super-admin (is_admin), se le da acceso como
        // admin_compania a la nueva compañía; si no, no podría verla luego.
        if (! $request->user()->is_admin) {
            $rolAdminId = DB::table('seg_roles')->where('name', 'admin_compania')->value('id');

            if ($rolAdminId) {
                DB::table('seg_usuarios_roles')->insert([
                    'rol_id'      => $rolAdminId,
                    'model_type'  => \App\Models\User::class,
                    'model_id'    => $request->user()->id,
                    'compania_id' => $compania->id,
                ]);
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }

        $files = [];

        foreach (['logo' => 'logo_url', 'sello' => 'sello_url'] as $campo => $columna) {
            if ($request->hasFile($campo)) {
                $files[$columna] = $request->file($campo)->store($compania->id.'/'.$campo, 'public');
            }
        }

        if ($files !== []) {
            $compania->update($files);
        }

        // Catálogo contable inicial: plan de cuentas Formulario 2 DGI (PA_ISR),
        // incluida la cuenta Salarios por Pagar y demás cuentas por defecto.
        app(\App\Services\PlantillaCuentas::class)->aplicar(
            $compania->id,
            \App\Services\PlantillaCuentas::POR_DEFECTO,
            $request->user()->email
        );

        // Configuración FEL por defecto (tokens demo HKA) editable por compañía.
        app(\App\Services\FelConfiguracionDefault::class)->aplicar(
            $compania->id,
            $request->user()->email
        );

        return redirect()->route('admin.companias.index')->with('status', 'Compañía creada con catálogo contable DGI.');
    }

    public function edit(Compania $compania): View
    {
        abort_unless(auth()->user()->can('companias.editar'), 403);
        $this->verificarAcceso($compania);

        $zonas = Zona::orderBy('description')->get();

        return view('admin.companias.edit', compact('compania', 'zonas'));
    }

    public function update(Request $request, Compania $compania): RedirectResponse
    {
        abort_unless($request->user()->can('companias.editar'), 403);
        $this->verificarAcceso($compania);

        $data = $this->validated($request, $compania);
        $data['updated_by'] = $request->user()->email;

        foreach (['logo' => 'logo_url', 'sello' => 'sello_url'] as $campo => $columna) {
            if ($request->hasFile($campo)) {
                if ($compania->{$columna}) {
                    Storage::disk('public')->delete($compania->{$columna});
                }
                $data[$columna] = $request->file($campo)->store($compania->id.'/'.$campo, 'public');
            }
        }

        $compania->update($data);

        return redirect()->route('admin.companias.index')->with('status', 'Compañía actualizada.');
    }

    public function destroy(Compania $compania): RedirectResponse
    {
        abort_unless(auth()->user()->can('companias.eliminar'), 403);
        $this->verificarAcceso($compania);

        $tieneDatos = DB::table('cgl_cuentas')->where('compania_id', $compania->id)->exists()
            || DB::table('contact_contactos')->where('compania_id', $compania->id)->exists()
            || DB::table('seg_usuarios_roles')->where('compania_id', $compania->id)->exists();

        if ($tieneDatos) {
            return back()->withErrors([
                'compania' => 'No se puede eliminar: la compañía tiene datos (plan de cuentas, contactos o usuarios asignados). Desactívala en su lugar.',
            ]);
        }

        $compania->delete();

        return redirect()->route('admin.companias.index')->with('status', 'Compañía eliminada.');
    }

    /**
     * El usuario solo puede operar sobre compañías a las que tiene acceso.
     */
    private function verificarAcceso(Compania $compania): void
    {
        $user = auth()->user();

        abort_unless(
            $user->is_admin || $user->companiasAccesibles()->contains('id', $compania->id),
            403
        );
    }

    private function validated(Request $request, ?Compania $compania = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ruc' => [
                'required', 'string', 'max:255',
                Rule::unique('core_companias', 'ruc')->ignore($compania?->id),
            ],
            'dv' => ['required', 'string', 'max:2'],
            'firma_cartas' => ['nullable', 'string', 'max:255'],
            'direccion' => ['required', 'string'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'telefono2' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'mensaje' => ['nullable', 'string'],
            'correlativo_ss' => ['required', 'integer', 'min:0'],
            'fecha_de_apertura' => ['required', 'date'],
            'activa' => ['required', 'boolean'],
            'no_patronal' => ['nullable', 'string', 'max:100'],
            'act_economica' => ['nullable', 'string', 'max:100'],
            'cedula' => ['nullable', 'string', 'max:50'],
            'licencia' => ['nullable', 'string', 'max:50'],
            'repre_legal' => ['nullable', 'string', 'max:200'],
            'zonas_id' => ['required', 'integer', 'exists:core_zonas,id'],
            'tipo_de_entidad' => ['nullable', 'string', 'max:100'],
            'constitucion' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'token' => ['nullable', 'string', 'max:50'],
            'nit' => ['nullable', 'string', 'max:50'],
            'cedula_repre_legal' => ['nullable', 'string', 'max:100'],
            'sello' => ['nullable', 'image', 'max:2048'],
            'municipio' => ['nullable', 'string', 'max:200'],
            'clave_municipio' => ['nullable', 'string', 'max:200'],
        ], [
            'ruc.unique' => 'El RUC ya está registrado en otra compañía.',
        ]);

        $data['nombre'] = mb_strtoupper($data['nombre'], 'UTF-8');

        unset($data['logo'], $data['sello']);

        return $data;
    }
}
