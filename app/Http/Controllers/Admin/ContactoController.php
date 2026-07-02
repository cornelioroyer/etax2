<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ContactosImport;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\CxcDocumentoDetalle;
use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Models\OtroCostoGasto;
use App\Models\TipoContacto;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $companiaId = $this->companiaActivaId($request);
        $tipoPreseleccionado = strtoupper(trim((string) $request->query('tipo', '')));

        return view('admin.contactos.create', [
            'tipos' => TipoContacto::orderBy('id')->get(),
            'tipoPreseleccionado' => $tipoPreseleccionado,
            'cuentas' => $this->cuentasGasto($companiaId),
            'otrosCostosGastos' => OtroCostoGasto::where('activo', true)->orderBy('descripcion')->get(),
            // Solo precarga la cuenta de gasto cuando se crea un proveedor.
            'cuentaGastoDefault' => $tipoPreseleccionado === 'PROVEEDOR'
                ? CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT')
                : null,
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
            'cuentas' => $this->cuentasGasto($contacto->compania_id),
            'otrosCostosGastos' => OtroCostoGasto::where('activo', true)->orderBy('descripcion')->get(),
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

    public function importar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.crear'), 403);

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt']]);

        $companiaId = $this->companiaActivaId($request);
        $tipos = TipoContacto::all()->keyBy('codigo');

        $import = new ContactosImport();
        Excel::import($import, $request->file('archivo'));

        $creados = 0;
        $duplicados = 0;
        $saldosRegistrados = 0;
        $saldosSinCuenta = 0;

        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');

        foreach ($import->filas as $fila) {
            if ($fila['identificacion'] !== '') {
                $existe = Contacto::where('compania_id', $companiaId)
                    ->where('identificacion', $fila['identificacion'])
                    ->exists();

                if ($existe) {
                    $duplicados++;
                    continue;
                }
            }

            $saldo = $fila['saldo'];
            $fechaSaldo = $fila['fecha_saldo'] ?? now()->toDateString();

            DB::transaction(function () use (
                $companiaId, $fila, $tipos, $saldo, $fechaSaldo,
                $cuentaCxcId, $cuentaVentasId, $request,
                &$creados, &$saldosRegistrados, &$saldosSinCuenta
            ) {
                $contacto = Contacto::create([
                    'compania_id'    => $companiaId,
                    'nombre'         => $fila['nombre'],
                    'razon_social'   => $fila['razon_social'] ?: null,
                    'tipo_persona'   => $fila['tipo_persona'],
                    'identificacion' => $fila['identificacion'] ?: null,
                    'dv'             => $fila['dv'] ?: null,
                    'email'          => $fila['email'] ?: null,
                    'telefono'       => $fila['telefono'] ?: null,
                    'direccion'      => $fila['direccion'] ?: null,
                    'activo'         => true,
                    'created_by'     => $request->user()->email,
                ]);

                $codigos = array_filter(array_map('trim', explode(',', $fila['tipos_raw'])));
                if (empty($codigos)) {
                    $codigos = ['CLIENTE'];
                }

                $tipoIds = collect($codigos)
                    ->map(fn ($c) => $tipos->get($c)?->id)
                    ->filter()
                    ->values()
                    ->all();

                if (empty($tipoIds) && $tipos->has('CLIENTE')) {
                    $tipoIds = [$tipos->get('CLIENTE')->id];
                }

                $contacto->tipos()->sync($tipoIds);
                $creados++;

                if ($saldo > 0) {
                    if (! $cuentaCxcId || ! $cuentaVentasId) {
                        $saldosSinCuenta++;
                        return;
                    }

                    $doc = CxcDocumento::create([
                        'compania_id'      => $companiaId,
                        'cliente_id'       => $contacto->id,
                        'tipo_documento'   => CxcDocumento::TIPO_FACTURA,
                        'numero'           => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_FACTURA),
                        'fecha'            => $fechaSaldo,
                        'fecha_vencimiento' => $fechaSaldo,
                        'subtotal'         => $saldo,
                        'descuento'        => 0,
                        'impuesto'         => 0,
                        'total'            => $saldo,
                        'saldo'            => $saldo,
                        'estado'           => CxcDocumento::ESTADO_PENDIENTE,
                        'created_by'       => $request->user()->email,
                    ]);

                    CxcDocumentoDetalle::create([
                        'documento_id'    => $doc->id,
                        'linea'           => 1,
                        'descripcion'     => 'Saldo inicial',
                        'cantidad'        => 1,
                        'precio_unitario' => $saldo,
                        'descuento'       => 0,
                        'impuesto_monto'  => 0,
                        'total_linea'     => $saldo,
                        'cuenta_id'       => $cuentaVentasId,
                        'created_by'      => $request->user()->email,
                    ]);

                    $asiento = app(AsientoAutomatico::class)->postear(
                        $companiaId,
                        $fechaSaldo,
                        "Saldo inicial {$contacto->nombre}",
                        $doc->numero,
                        [
                            [
                                'cuenta_id'   => $cuentaCxcId,
                                'contacto_id' => $contacto->id,
                                'descripcion' => "Saldo inicial {$doc->numero}",
                                'debito'      => $saldo,
                                'credito'     => 0,
                            ],
                            [
                                'cuenta_id'   => $cuentaVentasId,
                                'descripcion' => "Saldo inicial {$contacto->nombre}",
                                'debito'      => 0,
                                'credito'     => $saldo,
                            ],
                        ],
                        'CXC',
                        'cxc_documentos',
                        $doc->id,
                        $request->user(),
                    );

                    $doc->update(['asiento_id' => $asiento->id]);
                    $saldosRegistrados++;
                }
            });
        }

        $partes = ["Importación completada: {$creados} contacto(s) creado(s)"];
        if ($saldosRegistrados > 0) {
            $partes[] = "{$saldosRegistrados} saldo(s) inicial(es) registrado(s) en CxC";
        }
        if ($saldosSinCuenta > 0) {
            $partes[] = "{$saldosSinCuenta} saldo(s) omitido(s) por falta de cuenta CXC/VENTAS configurada";
        }
        if ($duplicados > 0) {
            $partes[] = "{$duplicados} omitido(s) por identificación duplicada";
        }

        return redirect()->route('admin.contactos.index')->with('status', implode('; ', $partes));
    }

    public function plantillaImport(): StreamedResponse
    {
        $encabezados = ['nombre', 'razon_social', 'tipo_persona', 'identificacion', 'dv', 'email', 'telefono', 'direccion', 'tipos', 'saldo', 'fecha_saldo'];
        $ejemplo     = ['Juan Pérez', 'Juan Pérez S.A.', 'NATURAL', '8-123-456', '5', 'juan@email.com', '6000-0000', 'Ciudad de Panamá', 'CLIENTE', '500.00', '01/01/2026'];

        return response()->streamDownload(function () use ($encabezados, $ejemplo) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel
            fputcsv($f, $encabezados);
            fputcsv($f, $ejemplo);
            fclose($f);
        }, 'plantilla_contactos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importarProveedores(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contactos.crear'), 403);

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt']]);

        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $tipoProveedor = TipoContacto::where('codigo', 'PROVEEDOR')->first();

        $cuentaCxpId    = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaGastoId  = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');

        $import = new ContactosImport();
        Excel::import($import, $request->file('archivo'));

        $creados = 0;
        $actualizados = 0;
        $saldosRegistrados = 0;
        $saldosSinCuenta = 0;

        foreach ($import->filas as $idx => $fila) {
            $saldo      = $fila['saldo'];
            $fechaSaldo = $fila['fecha_saldo'] ?? now()->toDateString();

            DB::transaction(function () use (
                $companiaId, $fila, $idx, $tipoProveedor, $saldo, $fechaSaldo,
                $cuentaCxpId, $cuentaGastoId, $usuario,
                &$creados, &$actualizados, &$saldosRegistrados, &$saldosSinCuenta
            ) {
                // Buscar proveedor existente por identificacion
                $contacto = null;
                if ($fila['identificacion'] !== '') {
                    $contacto = Contacto::where('compania_id', $companiaId)
                        ->where('identificacion', $fila['identificacion'])
                        ->first();
                }

                if ($contacto) {
                    $contacto->update([
                        'nombre'       => $fila['nombre'],
                        'razon_social' => $fila['razon_social'] ?: null,
                        'tipo_persona' => $fila['tipo_persona'],
                        'dv'           => $fila['dv'] ?: null,
                        'email'        => $fila['email'] ?: null,
                        'telefono'     => $fila['telefono'] ?: null,
                        'direccion'    => $fila['direccion'] ?: null,
                        'updated_by'   => $usuario->email,
                    ]);
                    $actualizados++;
                } else {
                    $contacto = Contacto::create([
                        'compania_id'    => $companiaId,
                        'nombre'         => $fila['nombre'],
                        'razon_social'   => $fila['razon_social'] ?: null,
                        'tipo_persona'   => $fila['tipo_persona'],
                        'identificacion' => $fila['identificacion'] ?: null,
                        'dv'             => $fila['dv'] ?: null,
                        'email'          => $fila['email'] ?: null,
                        'telefono'       => $fila['telefono'] ?: null,
                        'direccion'      => $fila['direccion'] ?: null,
                        'activo'         => true,
                        'created_by'     => $usuario->email,
                    ]);
                    $creados++;
                }

                // Asegurar tipo PROVEEDOR
                if ($tipoProveedor) {
                    $contacto->tipos()->syncWithoutDetaching([$tipoProveedor->id]);
                }

                // Registrar saldo en CxP si viene
                if ($saldo > 0) {
                    if (! $cuentaCxpId || ! $cuentaGastoId) {
                        $saldosSinCuenta++;
                        return;
                    }

                    $numero = 'SI-' . ($fila['identificacion'] ?: str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT));

                    // Evitar duplicado si ya existe saldo inicial para este proveedor
                    $yaExiste = CxpDocumento::where('compania_id', $companiaId)
                        ->where('proveedor_id', $contacto->id)
                        ->where('numero', $numero)
                        ->exists();

                    if ($yaExiste) {
                        return;
                    }

                    $doc = CxpDocumento::create([
                        'compania_id'      => $companiaId,
                        'proveedor_id'     => $contacto->id,
                        'tipo_documento'   => CxpDocumento::TIPO_FACTURA,
                        'numero'           => $numero,
                        'fecha'            => $fechaSaldo,
                        'fecha_vencimiento' => $fechaSaldo,
                        'subtotal'         => $saldo,
                        'descuento'        => 0,
                        'impuesto'         => 0,
                        'total'            => $saldo,
                        'saldo'            => $saldo,
                        'estado'           => CxpDocumento::ESTADO_PENDIENTE,
                        'created_by'       => $usuario->email,
                    ]);

                    CxpDocumentoDetalle::create([
                        'documento_id'    => $doc->id,
                        'linea'           => 1,
                        'descripcion'     => 'Saldo inicial',
                        'cantidad'        => 1,
                        'precio_unitario' => $saldo,
                        'descuento'       => 0,
                        'impuesto_monto'  => 0,
                        'total_linea'     => $saldo,
                        'cuenta_id'       => $cuentaGastoId,
                        'created_by'      => $usuario->email,
                    ]);

                    $asiento = app(AsientoAutomatico::class)->postear(
                        $companiaId,
                        $fechaSaldo,
                        "Saldo inicial {$contacto->nombre}",
                        $doc->numero,
                        [
                            [
                                'cuenta_id'   => $cuentaGastoId,
                                'contacto_id' => $contacto->id,
                                'descripcion' => "Saldo inicial {$doc->numero}",
                                'debito'      => $saldo,
                                'credito'     => 0,
                            ],
                            [
                                'cuenta_id'   => $cuentaCxpId,
                                'contacto_id' => $contacto->id,
                                'descripcion' => "Saldo inicial {$contacto->nombre}",
                                'debito'      => 0,
                                'credito'     => $saldo,
                            ],
                        ],
                        'CXP',
                        'cxp_documentos',
                        $doc->id,
                        $usuario,
                    );

                    $doc->update(['asiento_id' => $asiento->id]);
                    $saldosRegistrados++;
                }
            });
        }

        $partes = ["{$creados} proveedor(es) creado(s)", "{$actualizados} actualizado(s)"];
        if ($saldosRegistrados > 0) {
            $partes[] = "{$saldosRegistrados} saldo(s) inicial(es) en CxP";
        }
        if ($saldosSinCuenta > 0) {
            $partes[] = "{$saldosSinCuenta} saldo(s) omitido(s): configura cuentas CXP y GASTO_DEFAULT";
        }

        return redirect()->route('admin.contactos.index', ['tipo' => 'PROVEEDOR'])
            ->with('status', 'Importación: ' . implode(', ', $partes) . '.');
    }

    public function plantillaProveedores(): StreamedResponse
    {
        $encabezados = ['nombre', 'razon_social', 'tipo_persona', 'identificacion', 'dv', 'email', 'telefono', 'direccion', 'saldo', 'fecha_saldo'];
        $ejemplos = [
            ['Ferretería ABC S.A.', 'Ferretería ABC S.A.', 'JURIDICA', '888-888-111111', '99', 'compras@abc.com', '6000-0000', 'Ciudad de Panamá', '1500.00', '31/12/2025'],
            ['Juan Pérez', '', 'NATURAL', '8-123-456', '5', 'juan@email.com', '6111-2222', 'Chorrera', '', ''],
        ];

        return response()->streamDownload(function () use ($encabezados, $ejemplos) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($f, $encabezados);
            foreach ($ejemplos as $fila) {
                fputcsv($f, $fila);
            }
            fclose($f);
        }, 'plantilla_proveedores.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function plantillaProveedoresXlsx(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Proveedores');

        $encabezados = ['nombre', 'razon_social', 'tipo_persona', 'identificacion', 'dv', 'email', 'telefono', 'direccion', 'saldo', 'fecha_saldo'];
        $sheet->fromArray([$encabezados], null, 'A1');

        // Estilo encabezados: fondo azul oscuro, texto blanco, negrita
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0369A1']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        // Filas de ejemplo
        $ejemplos = [
            ['Ferretería ABC S.A.', 'Ferretería ABC S.A.', 'JURIDICA', '888-888-111111', '99', 'compras@abc.com', '6000-0000', 'Ciudad de Panamá', 1500.00, '31/12/2025'],
            ['Juan Pérez', '', 'NATURAL', '8-123-456', '5', 'juan@email.com', '6111-2222', 'Chorrera', '', ''],
        ];
        $sheet->fromArray($ejemplos, null, 'A2');

        // Fondo gris claro en fila 3 para alternar
        $sheet->getStyle('A3:J3')->applyFromArray([
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
        ]);

        // Columna saldo como número
        $sheet->getStyle('I2:I3')->getNumberFormat()->setFormatCode('#,##0.00');

        // Ancho automático
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze fila 1
        $sheet->freezePane('A2');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'plantilla_proveedores.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
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
        // Los catálogos DGI de compra (concepto/tipo_compra/otros_costos_gastos_id) y la
        // cuenta de gasto por defecto son propios del PROVEEDOR; solo se exigen si ese tipo está marcado.
        $tipoProveedorId = TipoContacto::where('codigo', 'PROVEEDOR')->value('id');
        $esProveedor = $tipoProveedorId && in_array(
            $tipoProveedorId,
            array_map('intval', (array) $request->input('tipos', [])),
            true
        );

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
            'forma_pago' => ['nullable', Rule::in([Contacto::FORMA_PAGO_CONTADO, Contacto::FORMA_PAGO_CREDITO])],
            'dias_credito' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'cuenta_gasto_id' => [
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'concepto' => ['nullable', Rule::requiredIf($esProveedor), Rule::in(array_keys(Contacto::CONCEPTOS))],
            'otros_costos_gastos_id' => ['nullable', Rule::requiredIf($esProveedor), 'integer', Rule::exists('core_otros_costos_gastos', 'id')->where('activo', true)],
            'tipo_compra' => ['nullable', Rule::requiredIf($esProveedor), Rule::in(array_keys(Contacto::TIPOS_COMPRA))],
            'activo' => ['required', 'boolean'],
            'tipos' => ['required', 'array', 'min:1'],
            'tipos.*' => ['integer', 'exists:contact_tipos,id'],
        ]);

        // Los días de crédito solo aplican a contactos a crédito.
        if (($data['forma_pago'] ?? null) !== Contacto::FORMA_PAGO_CREDITO) {
            $data['dias_credito'] = null;
        }

        // Idem para los campos propios del proveedor: si el contacto no tiene ese tipo,
        // se descarta lo que haya llegado del select oculto (que siempre trae un valor).
        if (! $esProveedor) {
            $data['cuenta_gasto_id'] = null;
            $data['concepto'] = null;
            $data['otros_costos_gastos_id'] = null;
            $data['tipo_compra'] = null;
        }

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

    /** Cuentas de movimiento para el selector de cuenta de gasto por defecto del proveedor. */
    private function cuentasGasto(int $companiaId)
    {
        return CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }
}
