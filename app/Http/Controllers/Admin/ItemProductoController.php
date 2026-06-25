<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Imports\ItemsImport;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\ItemCategoria;
use App\Models\ItemProducto;
use App\Models\ItemUnidadMedida;
use App\Models\TaxImpuesto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemProductoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'tipo'         => ['nullable', Rule::in(['PRODUCTO', 'SERVICIO'])],
            'categoria_id' => ['nullable', 'integer'],
            'activo'       => ['nullable', Rule::in(['1', '0'])],
            'q'            => ['nullable', 'string', 'max:100'],
        ]);

        $items = ItemProducto::with(['categoria', 'unidadMedida', 'impuesto'])
            ->where('compania_id', $companiaId)
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $v) => $q->where('categoria_id', $v))
            ->when(isset($filtros['activo']) && $filtros['activo'] !== null, fn ($q) => $q->where('activo', (bool) $filtros['activo']))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $b = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw('LOWER(codigo) LIKE ?', [$b])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', [$b])
                );
            })
            ->orderBy('codigo')
            ->paginate(30)
            ->withQueryString();

        return view('admin.items.index', [
            'items'      => $items,
            'filtros'    => $filtros,
            'categorias' => ItemCategoria::where('compania_id', $companiaId)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.items.create', $this->formData($companiaId));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $this->validar($request, $companiaId);

        // Código opcional: si el usuario lo deja vacío se genera un correlativo
        // (PROD-001 / SERV-001). Se hace dentro de la transacción para que el
        // advisory lock de numeración sea válido y serialice concurrentes.
        $item = DB::transaction(function () use ($data, $companiaId, $request) {
            if (trim((string) ($data['codigo'] ?? '')) === '') {
                $data['codigo'] = ItemProducto::siguienteCodigo($companiaId, $data['tipo']);
            }

            return ItemProducto::create($data + [
                'compania_id' => $companiaId,
                'activo'      => true,
                'created_by'  => $request->user()->email,
            ]);
        });

        return redirect()->route('admin.items.index')
            ->with('status', "Producto/servicio {$item->codigo} — {$item->nombre} creado.");
    }

    public function edit(Request $request, ItemProducto $item): View
    {
        abort_unless($item->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.items.edit', ['item' => $item] + $this->formData($item->compania_id));
    }

    public function update(Request $request, ItemProducto $item): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        abort_unless($item->compania_id === $companiaId, 404);

        $data = $this->validar($request, $companiaId, $item->id);

        // Al editar, si el código queda vacío se conserva el actual (no se
        // borra ni se regenera para no romper referencias existentes).
        if (trim((string) ($data['codigo'] ?? '')) === '') {
            $data['codigo'] = $item->codigo;
        }

        $item->update($data + ['updated_by' => $request->user()->email]);

        return redirect()->route('admin.items.index')
            ->with('status', "Producto/servicio {$item->codigo} — {$item->nombre} actualizado.");
    }

    public function toggle(Request $request, ItemProducto $item): RedirectResponse
    {
        abort_unless($item->compania_id === $this->companiaActivaId($request), 404);

        $item->update(['activo' => ! $item->activo, 'updated_by' => $request->user()->email]);

        return back()->with('status', "{$item->nombre} ".($item->activo ? 'activado' : 'desactivado').'.');
    }

    /**
     * Importa productos y servicios desde Excel/CSV de forma ADITIVA.
     *
     * Solo CREA ítems nuevos para la compañía activa; si el código ya existe se
     * omite la fila (no modifica ni elimina). Las categorías, unidades, cuentas
     * contables e ITBMS se resuelven SIEMPRE dentro de la compañía activa para
     * mantener el aislamiento. Si el código viene vacío se autogenera
     * (PROD-001 / SERV-001) dentro de la transacción.
     */
    public function importar(Request $request): RedirectResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt']]);

        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user()->email;

        $import = new ItemsImport();
        Excel::import($import, $request->file('archivo'));

        // Catálogos auxiliares de la compañía, indexados para lookup tolerante.
        $codigosExistentes = ItemProducto::where('compania_id', $companiaId)
            ->pluck('codigo')
            ->map(fn ($c) => mb_strtolower(trim($c)))
            ->flip()
            ->all();

        $categorias = ItemCategoria::where('compania_id', $companiaId)
            ->get(['id', 'nombre'])
            ->keyBy(fn ($c) => mb_strtolower(trim($c->nombre)));

        $unidades = ItemUnidadMedida::get(['id', 'codigo', 'nombre']);
        $unidadPorCodigo = $unidades->keyBy(fn ($u) => mb_strtolower(trim($u->codigo)));
        $unidadPorNombre = $unidades->keyBy(fn ($u) => mb_strtolower(trim($u->nombre)));

        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->get(['id', 'codigo'])
            ->keyBy(fn ($c) => mb_strtolower(trim($c->codigo)));

        // ITBMS: porcentaje (entero) => id.
        $itbmsPorPct = TaxImpuesto::itbmsGlobales()
            ->keyBy(fn ($t) => (int) $t->porcentaje);

        $creados = 0;
        $omitidos = 0;
        $errores = [];

        DB::transaction(function () use (
            $import, $companiaId, $usuario, $categorias, $unidadPorCodigo, $unidadPorNombre,
            $cuentas, $itbmsPorPct, &$codigosExistentes, &$creados, &$omitidos, &$errores
        ) {
            foreach ($import->filas as $i => $fila) {
                $linea = $i + 2; // fila real en el archivo (encabezado = 1)

                if ($fila['nombre'] === '') {
                    $errores[] = "Fila {$linea}: nombre vacío";
                    continue;
                }

                $codigo = trim((string) $fila['codigo']);

                // Código provisto y ya existente (o duplicado dentro del archivo): se omite.
                if ($codigo !== '' && isset($codigosExistentes[mb_strtolower($codigo)])) {
                    $omitidos++;
                    continue;
                }

                if ($codigo === '') {
                    $codigo = ItemProducto::siguienteCodigo($companiaId, $fila['tipo']);
                }

                // Lookups tolerantes; lo no encontrado queda en null (se reporta).
                $categoriaId = $fila['categoria'] !== ''
                    ? ($categorias->get(mb_strtolower($fila['categoria']))?->id) : null;
                if ($fila['categoria'] !== '' && $categoriaId === null) {
                    $errores[] = "Fila {$linea}: categoría \"{$fila['categoria']}\" no existe (se dejó sin categoría)";
                }

                $unidadId = null;
                if ($fila['unidad'] !== '') {
                    $u = $fila['unidad'];
                    $unidadId = ($unidadPorCodigo->get(mb_strtolower($u)) ?? $unidadPorNombre->get(mb_strtolower($u)))?->id;
                    if ($unidadId === null) {
                        $errores[] = "Fila {$linea}: unidad \"{$u}\" no existe (se dejó sin unidad)";
                    }
                }

                $cuentaIngresoId = $fila['cuenta_ingreso'] !== ''
                    ? ($cuentas->get(mb_strtolower($fila['cuenta_ingreso']))?->id) : null;
                if ($fila['cuenta_ingreso'] !== '' && $cuentaIngresoId === null) {
                    $errores[] = "Fila {$linea}: cuenta de ingreso \"{$fila['cuenta_ingreso']}\" no existe o no permite movimiento";
                }

                $cuentaGastoId = $fila['cuenta_gasto'] !== ''
                    ? ($cuentas->get(mb_strtolower($fila['cuenta_gasto']))?->id) : null;
                if ($fila['cuenta_gasto'] !== '' && $cuentaGastoId === null) {
                    $errores[] = "Fila {$linea}: cuenta de gasto \"{$fila['cuenta_gasto']}\" no existe o no permite movimiento";
                }

                // ITBMS: vacío => 7% (tasa estándar). Si el porcentaje no existe, exento (null).
                $pct = $fila['itbms'] ?? 7;
                $impuestoId = $itbmsPorPct->get((int) $pct)?->id;

                $item = ItemProducto::create([
                    'compania_id'       => $companiaId,
                    'codigo'            => $codigo,
                    'nombre'            => $fila['nombre'],
                    'descripcion'       => $fila['descripcion'] ?: null,
                    'tipo'              => $fila['tipo'],
                    'categoria_id'      => $categoriaId,
                    'unidad_medida_id'  => $unidadId,
                    'precio_venta'      => $fila['precio_venta'],
                    'costo'             => $fila['costo'],
                    'cuenta_ingreso_id' => $cuentaIngresoId,
                    'cuenta_gasto_id'   => $cuentaGastoId,
                    'impuesto_id'       => $impuestoId,
                    'activo'            => true,
                    'created_by'        => $usuario,
                ]);

                $codigosExistentes[mb_strtolower($item->codigo)] = true;
                $creados++;
            }
        });

        $partes = ["Importación completada: {$creados} ítem(s) creado(s)"];
        if ($omitidos > 0) {
            $partes[] = "{$omitidos} omitido(s) por código ya existente";
        }
        if (! empty($errores)) {
            $muestra = array_slice($errores, 0, 10);
            $partes[] = count($errores).' aviso(s) ('.implode('; ', $muestra).(count($errores) > 10 ? '…' : '').')';
        }

        return redirect()->route('admin.items.index')->with('status', implode('; ', $partes));
    }

    /** Plantilla CSV simple (encabezados + ejemplos). */
    public function plantillaImportCsv(): StreamedResponse
    {
        $encabezados = ['codigo', 'nombre', 'tipo', 'descripcion', 'categoria', 'unidad', 'precio_venta', 'costo', 'cuenta_ingreso', 'cuenta_gasto', 'itbms'];
        $ejemplos = [
            ['', 'Laptop HP 15"', 'PRODUCTO', '', '', 'UND', '650.00', '500.00', '', '', '7'],
            ['', 'Servicio de soporte técnico', 'SERVICIO', 'Soporte por hora', '', '', '35.00', '0', '', '', '7'],
            ['', 'Servicio profesional exento', 'SERVICIO', '', '', '', '120.00', '0', '', '', '0'],
        ];

        return response()->streamDownload(function () use ($encabezados, $ejemplos) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel
            fputcsv($f, $encabezados);
            foreach ($ejemplos as $fila) {
                fputcsv($f, $fila);
            }
            fclose($f);
        }, 'plantilla_productos_servicios.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Plantilla Excel (.xlsx) con encabezados estilizados, ejemplos, listas
     * desplegables (tipo / ITBMS) y una hoja de instrucciones.
     */
    public function plantillaImportXlsx(): StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos y servicios');

        $encabezados = ['codigo', 'nombre', 'tipo', 'descripcion', 'categoria', 'unidad', 'precio_venta', 'costo', 'cuenta_ingreso', 'cuenta_gasto', 'itbms'];
        $sheet->fromArray([$encabezados], null, 'A1');

        $sheet->getStyle('A1:K1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0D2D5E']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $ejemplos = [
            ['', 'Laptop HP 15"', 'PRODUCTO', '', '', 'UND', '650.00', '500.00', '', '', '7'],
            ['', 'Mouse inalámbrico', 'PRODUCTO', '', '', 'UND', '15.00', '9.00', '', '', '7'],
            ['', 'Servicio de soporte técnico', 'SERVICIO', 'Soporte por hora', '', '', '35.00', '0', '', '', '7'],
            ['', 'Servicio profesional exento', 'SERVICIO', '', '', '', '120.00', '0', '', '', '0'],
        ];
        $sheet->fromArray($ejemplos, null, 'A2');

        // codigo, cuentas e itbms como TEXTO para conservar ceros a la izquierda.
        $ultima = count($ejemplos) + 1;
        foreach (['A', 'I', 'J', 'K'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$ultima}")->getNumberFormat()->setFormatCode('@');
        }

        $hasta = 500;
        $this->validacionListaItems($sheet, 'C', $hasta, '"PRODUCTO,SERVICIO"');
        $this->validacionListaItems($sheet, 'K', $hasta, '"0,7,10,15"');

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        // Hoja de instrucciones
        $guia = $spreadsheet->createSheet();
        $guia->setTitle('Instrucciones');
        $guia->fromArray([
            ['Columna', 'Obligatorio', 'Descripción'],
            ['codigo', 'No', 'Si se deja vacío se genera automáticamente (PROD-001 / SERV-001). Si lo escribes y ya existe, la fila se omite.'],
            ['nombre', 'Sí', 'Nombre del producto o servicio'],
            ['tipo', 'No', 'PRODUCTO o SERVICIO. Por defecto PRODUCTO'],
            ['descripcion', 'No', 'Descripción larga (opcional)'],
            ['categoria', 'No', 'Nombre de una categoría existente de la compañía. Si no existe, el ítem se crea sin categoría'],
            ['unidad', 'No', 'Código (ej. UND) o nombre de la unidad de medida'],
            ['precio_venta', 'No', 'Precio de venta. Default 0'],
            ['costo', 'No', 'Costo. Default 0'],
            ['cuenta_ingreso', 'No', 'Código de la cuenta contable de ingreso (debe permitir movimiento)'],
            ['cuenta_gasto', 'No', 'Código de la cuenta contable de gasto/costo (debe permitir movimiento)'],
            ['itbms', 'No', 'Tasa ITBMS: 0, 7, 10 o 15. Si se omite se usa 7%. Usa 0 para exento'],
            ['', '', ''],
            ['Nota', '', 'Solo se crean ítems nuevos. Las cuentas, categorías y unidades se buscan dentro de la compañía activa; lo no encontrado se reporta como aviso y el ítem se crea sin ese dato.'],
        ], null, 'A1');
        $guia->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0D2D5E']],
        ]);
        $guia->getColumnDimension('A')->setWidth(18);
        $guia->getColumnDimension('B')->setWidth(12);
        $guia->getColumnDimension('C')->setWidth(95);
        $guia->getStyle('C2:C14')->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'plantilla_productos_servicios.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /** Agrega una lista desplegable (data validation) al rango {col}2:{col}{hasta}. */
    private function validacionListaItems(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $col, int $hasta, string $formula1): void
    {
        $dv = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
        $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $dv->setAllowBlank(true);
        $dv->setShowDropDown(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorTitle('Valor no permitido');
        $dv->setError('Selecciona un valor de la lista.');
        $dv->setFormula1($formula1);

        $sheet->setDataValidation("{$col}2:{$col}{$hasta}", $dv);
    }

    private function validar(Request $request, int $companiaId, ?int $excludeId = null): array
    {
        $data = $request->validate([
            'codigo'             => ['nullable', 'string', 'max:50',
                Rule::unique('item_productos_servicios')->where('compania_id', $companiaId)->ignore($excludeId)],
            'nombre'             => ['required', 'string', 'max:200'],
            'descripcion'        => ['nullable', 'string', 'max:2000'],
            'tipo'               => ['required', Rule::in(['PRODUCTO', 'SERVICIO'])],
            'categoria_id'       => ['nullable', 'integer', Rule::exists('item_categorias', 'id')->where('compania_id', $companiaId)],
            'unidad_medida_id'   => ['nullable', 'integer', Rule::exists('item_unidades_medida', 'id')],
            'precio_venta'       => ['nullable', 'numeric', 'min:0'],
            'costo'              => ['nullable', 'numeric', 'min:0'],
            'cuenta_ingreso_id'  => ['nullable', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'cuenta_gasto_id'    => ['nullable', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'impuesto_id'        => ['nullable', 'integer'],
        ]);

        // precio_venta y costo son NOT NULL DEFAULT 0 en la BD; si el usuario
        // los deja vacíos los normalizamos a 0 para no pasar null explícito.
        $data['precio_venta'] = $data['precio_venta'] ?? 0;
        $data['costo']        = $data['costo'] ?? 0;

        return $data;
    }

    private function formData(int $companiaId): array
    {
        $impuestos = TaxImpuesto::itbmsGlobales();

        return [
            'categorias'   => ItemCategoria::where('compania_id', $companiaId)->orderBy('nombre')->get(['id', 'nombre']),
            'unidades'     => ItemUnidadMedida::orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'impuestos'    => $impuestos,
            'cuentas'      => CuentaContable::where('compania_id', $companiaId)->where('activa', true)->where('permite_movimiento', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),

            // Valores por defecto para un ítem NUEVO (el blade solo los aplica
            // cuando no hay $item). ITBMS 7% (tasa estándar) y las cuentas de
            // ingreso/gasto configuradas para la compañía; si no existen, queda
            // "Exento" / "Sin cuenta". La cuenta de gasto/costo del ítem es la
            // que se debita al venderlo/consumirlo, así que el default es Costo
            // de Ventas (COSTO_VENTAS); si no está configurada, cae a Otros
            // Gastos (GASTO_DEFAULT).
            'impuestoDefaultId'      => $impuestos->first(fn ($i) => (float) $i->porcentaje === 7.0)?->id,
            'cuentaIngresoDefaultId' => CuentaDefault::idPara($companiaId, 'VENTAS'),
            'cuentaGastoDefaultId'   => CuentaDefault::idPara($companiaId, 'COSTO_VENTAS')
                ?? CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT'),
        ];
    }
}
