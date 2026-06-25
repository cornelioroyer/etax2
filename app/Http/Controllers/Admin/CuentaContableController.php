<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\CuentasImport;
use App\Models\CuentaContable;
use App\Models\TipoCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CuentaContableController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cuentas = CuentaContable::query()
            ->with('tipo')
            ->where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        $plantillas = $cuentas->isEmpty()
            ? DB::table('core_plantillas_cuentas')->where('activa', true)->orderBy('codigo')->get()
            : collect();

        return view('admin.cuentas.index', compact('cuentas', 'plantillas'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        return view('admin.cuentas.create', $this->datosFormulario($request));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);
        $data = $this->validated($request, $companiaId);
        $data['compania_id'] = $companiaId;
        $data['created_by'] = $request->user()->email;

        CuentaContable::create($data);

        return redirect()->route('admin.cuentas.index')->with('status', 'Cuenta creada.');
    }

    public function edit(Request $request, CuentaContable $cuenta): View
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $cuenta);

        return view('admin.cuentas.edit', ['cuenta' => $cuenta] + $this->datosFormulario($request, $cuenta));
    }

    public function update(Request $request, CuentaContable $cuenta): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $cuenta);

        $data = $this->validated($request, $cuenta->compania_id, $cuenta);
        $data['updated_by'] = $request->user()->email;

        $cuenta->update($data);

        return redirect()->route('admin.cuentas.index')->with('status', 'Cuenta actualizada.');
    }

    public function destroy(Request $request, CuentaContable $cuenta): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.eliminar'), 403);
        $this->verificarCompania($request, $cuenta);

        if ($cuenta->hijos()->exists()) {
            return back()->withErrors(['cuenta' => 'No se puede eliminar: la cuenta tiene subcuentas.']);
        }

        if (DB::table('cgl_asientos_detalle')->where('cuenta_id', $cuenta->id)->exists()) {
            return back()->withErrors(['cuenta' => 'No se puede eliminar: la cuenta tiene movimientos. Desactívala en su lugar.']);
        }

        $cuenta->delete();

        return redirect()->route('admin.cuentas.index')->with('status', 'Cuenta eliminada.');
    }

    /**
     * Copia la plantilla PA_BASICO a la compañía activa y configura
     * las cuentas por defecto (core_cuentas_default).
     */
    public function aplicarPlantilla(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);

        if (CuentaContable::where('compania_id', $companiaId)->exists()) {
            return back()->withErrors(['plantilla' => 'La compañía ya tiene plan de cuentas; la plantilla solo aplica sobre un plan vacío.']);
        }

        $codigo = $request->validate([
            'plantilla' => ['required', 'string', Rule::exists('core_plantillas_cuentas', 'codigo')],
        ])['plantilla'];

        $creadas = app(\App\Services\PlantillaCuentas::class)
            ->aplicar($companiaId, $codigo, $request->user()->email);

        abort_if($creadas === 0, 404, 'Plantilla no encontrada.');

        return redirect()->route('admin.cuentas.index')
            ->with('status', "Plantilla aplicada: {$creadas} cuentas creadas.");
    }

    /**
     * Importa un catálogo de cuentas desde Excel/CSV de forma aditiva.
     *
     * Solo CREA cuentas nuevas; nunca modifica ni elimina cuentas existentes
     * (no se tocan cuentas con movimientos). Los códigos ya existentes se omiten.
     * La jerarquía (cuenta_padre_id/nivel) se resuelve por código_padre explícito
     * o, en su defecto, por el prefijo de código más largo entre las cuentas
     * conocidas. La naturaleza se deriva del tipo si no viene especificada.
     */
    public function importar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt']]);

        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user()->email;

        $tipos = TipoCuenta::all()->keyBy('codigo'); // codigo => modelo (id, naturaleza)

        $import = new CuentasImport();
        Excel::import($import, $request->file('archivo'));

        // Cuentas ya existentes en la compañía: codigo => ['id'=>, 'nivel'=>]
        $existentes = CuentaContable::where('compania_id', $companiaId)
            ->get(['id', 'codigo', 'nivel'])
            ->keyBy('codigo')
            ->map(fn ($c) => ['id' => $c->id, 'nivel' => $c->nivel])
            ->all();

        // Ordenar por código para procesar padres antes que hijos.
        $filas = collect($import->filas)->sortBy('codigo', SORT_NATURAL)->values();

        // Conjunto de TODOS los códigos conocidos (existentes + a importar) para
        // derivar padres por prefijo.
        $todosCodigos = array_unique(array_merge(
            array_keys($existentes),
            $filas->pluck('codigo')->all()
        ));

        // Padre resuelto por código (explícito si viene; si no, por prefijo más
        // largo). Sirve tanto para enlazar como para detectar qué cuentas serán
        // de título (las que son padre de alguna otra).
        $padreResuelto = [];
        $tieneHijos = [];
        foreach ($filas as $fila) {
            $p = $fila['codigo_padre'] !== ''
                ? $fila['codigo_padre']
                : $this->padrePorPrefijo($fila['codigo'], $todosCodigos);
            $padreResuelto[$fila['codigo']] = $p;
            if ($p !== null) {
                $tieneHijos[$p] = true;
            }
        }

        $creadas = 0;
        $duplicadas = 0;
        $errores = [];

        DB::transaction(function () use (
            $filas, $tipos, $companiaId, $usuario, $tieneHijos, $padreResuelto,
            &$existentes, &$creadas, &$duplicadas, &$errores
        ) {
            foreach ($filas as $fila) {
                $codigo = $fila['codigo'];

                if (isset($existentes[$codigo])) {
                    $duplicadas++;
                    continue;
                }

                if ($fila['tipo'] === '' || ! $tipos->has($fila['tipo'])) {
                    $errores[] = "{$codigo}: tipo de cuenta inválido o ausente";
                    continue;
                }

                $tipo = $tipos->get($fila['tipo']);

                // Naturaleza: la del archivo o, si está vacía, la del tipo.
                $naturaleza = $fila['naturaleza'] !== '' ? $fila['naturaleza'] : strtoupper($tipo->naturaleza);

                // Padre ya resuelto (explícito o por prefijo); debe existir ya creado.
                $codigoPadre = $padreResuelto[$codigo] ?? null;
                $padre = $codigoPadre !== null && isset($existentes[$codigoPadre])
                    ? $existentes[$codigoPadre]
                    : null;

                // Si tiene hijos en el catálogo, por defecto es título (no movimiento),
                // salvo que el archivo indique explícitamente lo contrario.
                $permiteMovimiento = $fila['permite_movimiento']
                    ?? ! ($tieneHijos[$codigo] ?? false);

                $cuenta = CuentaContable::create([
                    'compania_id'        => $companiaId,
                    'codigo'             => $codigo,
                    'nombre'             => $fila['nombre'],
                    'cuenta_padre_id'    => $padre['id'] ?? null,
                    'nivel'              => isset($padre) ? $padre['nivel'] + 1 : 1,
                    'tipo_cuenta_id'     => $tipo->id,
                    'naturaleza'         => $naturaleza,
                    'permite_movimiento' => $permiteMovimiento,
                    'conciliable'        => $fila['conciliable'],
                    'activa'             => true,
                    'renglon_isr'        => $fila['renglon_isr'],
                    'created_by'         => $usuario,
                ]);

                $existentes[$codigo] = ['id' => $cuenta->id, 'nivel' => $cuenta->nivel];
                $creadas++;
            }
        });

        $partes = ["Importación completada: {$creadas} cuenta(s) creada(s)"];
        if ($duplicadas > 0) {
            $partes[] = "{$duplicadas} omitida(s) por código ya existente";
        }
        if (! empty($errores)) {
            $muestra = array_slice($errores, 0, 10);
            $partes[] = count($errores).' con error ('.implode('; ', $muestra).(count($errores) > 10 ? '…' : '').')';
        }

        return redirect()->route('admin.cuentas.index')->with('status', implode('; ', $partes));
    }

    public function plantillaImport(): StreamedResponse
    {
        $encabezados = ['codigo', 'nombre', 'tipo', 'naturaleza', 'codigo_padre', 'permite_movimiento', 'conciliable', 'renglon_isr'];
        $ejemplos = [
            ['1000', 'ACTIVO', 'ACTIVO', 'DEBITO', '', 'NO', 'NO', ''],
            ['1100', 'CAJA GENERAL', 'ACTIVO', 'DEBITO', '1000', 'SI', 'NO', ''],
            ['1200', 'BANCO', 'ACTIVO', 'DEBITO', '1000', 'SI', 'SI', ''],
            ['1800', 'DEPRECIACION ACUMULADA', 'ACTIVO', 'CREDITO', '1000', 'SI', 'NO', ''],
            ['4000', 'INGRESOS', 'INGRESO', 'CREDITO', '', 'NO', 'NO', ''],
            ['4100', 'VENTAS', 'INGRESO', 'CREDITO', '4000', 'SI', 'NO', '1'],
        ];

        return response()->streamDownload(function () use ($encabezados, $ejemplos) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel
            fputcsv($f, $encabezados);
            foreach ($ejemplos as $fila) {
                fputcsv($f, $fila);
            }
            fclose($f);
        }, 'plantilla_plan_de_cuentas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Plantilla de ejemplo en Excel (.xlsx) con encabezados estilizados,
     * filas de ejemplo, listas desplegables (tipo/naturaleza/SI-NO) y una
     * hoja de instrucciones.
     */
    public function plantillaImportXlsx(): StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plan de cuentas');

        $encabezados = ['codigo', 'nombre', 'tipo', 'naturaleza', 'codigo_padre', 'permite_movimiento', 'conciliable', 'renglon_isr'];
        $sheet->fromArray([$encabezados], null, 'A1');

        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0D2D5E']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $ejemplos = [
            ['1000', 'ACTIVO', 'ACTIVO', 'DEBITO', '', 'NO', 'NO', ''],
            ['1100', 'CAJA GENERAL', 'ACTIVO', 'DEBITO', '1000', 'SI', 'NO', ''],
            ['1200', 'BANCO GENERAL CTA CTE', 'ACTIVO', 'DEBITO', '1000', 'SI', 'SI', ''],
            ['1300', 'CUENTAS POR COBRAR', 'ACTIVO', 'DEBITO', '1000', 'SI', 'NO', ''],
            ['1700', 'PROPIEDAD, PLANTA Y EQUIPO', 'ACTIVO', 'DEBITO', '1000', 'NO', 'NO', ''],
            ['1710', 'MOBILIARIO Y EQUIPO', 'ACTIVO', 'DEBITO', '1700', 'SI', 'NO', ''],
            ['1800', 'DEPRECIACION ACUMULADA', 'ACTIVO', 'CREDITO', '1700', 'SI', 'NO', ''],
            ['2000', 'PASIVO', 'PASIVO', 'CREDITO', '', 'NO', 'NO', ''],
            ['2100', 'CUENTAS POR PAGAR PROVEEDORES', 'PASIVO', 'CREDITO', '2000', 'SI', 'NO', ''],
            ['3000', 'PATRIMONIO', 'PATRIMONIO', 'CREDITO', '', 'NO', 'NO', ''],
            ['3100', 'CAPITAL', 'PATRIMONIO', 'CREDITO', '3000', 'SI', 'NO', ''],
            ['4000', 'INGRESOS', 'INGRESO', 'CREDITO', '', 'NO', 'NO', ''],
            ['4100', 'VENTAS / SERVICIOS', 'INGRESO', 'CREDITO', '4000', 'SI', 'NO', '1'],
            ['5000', 'COSTOS', 'COSTO', 'DEBITO', '', 'NO', 'NO', ''],
            ['6000', 'GASTOS GENERALES', 'GASTO', 'DEBITO', '', 'NO', 'NO', ''],
            ['6100', 'SALARIOS', 'GASTO', 'DEBITO', '6000', 'SI', 'NO', ''],
        ];
        $sheet->fromArray($ejemplos, null, 'A2');

        // El código debe quedar como TEXTO para conservar ceros a la izquierda.
        $ultima = count($ejemplos) + 1; // fila final con datos
        $sheet->getStyle("A2:A{$ultima}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("E2:E{$ultima}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("H2:H{$ultima}")->getNumberFormat()->setFormatCode('@');

        // Listas desplegables hasta una fila amplia para que el usuario siga llenando.
        $hasta = 500;
        $this->validacionLista($sheet, 'C', $hasta, '"ACTIVO,PASIVO,PATRIMONIO,INGRESO,COSTO,GASTO"');
        $this->validacionLista($sheet, 'D', $hasta, '"DEBITO,CREDITO"');
        $this->validacionLista($sheet, 'F', $hasta, '"SI,NO"');
        $this->validacionLista($sheet, 'G', $hasta, '"SI,NO"');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        // Hoja de instrucciones
        $guia = $spreadsheet->createSheet();
        $guia->setTitle('Instrucciones');
        $guia->fromArray([
            ['Columna', 'Obligatorio', 'Descripción'],
            ['codigo', 'Sí', 'Código de la cuenta. Texto; conserva ceros a la izquierda. Ej: 1100'],
            ['nombre', 'Sí', 'Nombre de la cuenta'],
            ['tipo', 'Sí', 'ACTIVO / PASIVO / PATRIMONIO / INGRESO / COSTO / GASTO'],
            ['naturaleza', 'No', 'DEBITO / CREDITO. Si se omite, se deriva del tipo. Úsala para contra-cuentas (ej: Depreciación acumulada = ACTIVO + CREDITO)'],
            ['codigo_padre', 'No', 'Código de la cuenta padre. Si se omite, se deduce por el código (padre de 1100 es 1000)'],
            ['permite_movimiento', 'No', 'SI = recibe asientos; NO = cuenta de título. Por defecto NO si tiene subcuentas'],
            ['conciliable', 'No', 'SI / NO. Marca cuentas de banco para conciliación. Default NO'],
            ['renglon_isr', 'No', 'Renglón del Formulario 2 (ISR) al que tributa'],
            ['', '', ''],
            ['Nota', '', 'Solo se crean cuentas nuevas. Si el código ya existe en la compañía, la fila se omite; no se modifican cuentas existentes ni con movimientos.'],
        ], null, 'A1');
        $guia->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0D2D5E']],
        ]);
        $guia->getColumnDimension('A')->setWidth(20);
        $guia->getColumnDimension('B')->setWidth(12);
        $guia->getColumnDimension('C')->setWidth(95);
        $guia->getStyle('C2:C11')->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            'plantilla_plan_de_cuentas.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /** Agrega una lista desplegable (data validation) al rango {col}2:{col}{hasta}. */
    private function validacionLista(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $col, int $hasta, string $formula1): void
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

    /**
     * Para un código, devuelve el código existente más largo que sea su prefijo
     * estricto (su padre jerárquico), o null si ninguno aplica.
     *
     * @param  array<int, string>  $candidatos
     */
    private function padrePorPrefijo(string $codigo, array $candidatos): ?string
    {
        $mejor = null;

        foreach ($candidatos as $cand) {
            if ($cand !== $codigo && str_starts_with($codigo, $cand)) {
                if ($mejor === null || strlen($cand) > strlen($mejor)) {
                    $mejor = $cand;
                }
            }
        }

        return $mejor;
    }

    private function datosFormulario(Request $request, ?CuentaContable $excluir = null): array
    {
        $companiaId = $this->companiaActivaId($request);

        $padres = CuentaContable::where('compania_id', $companiaId)
            ->when($excluir, fn ($q) => $q->where('id', '!=', $excluir->id))
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'nivel']);

        return [
            'tipos' => TipoCuenta::orderBy('id')->get(),
            'padres' => $padres,
        ];
    }

    private function validated(Request $request, int $companiaId, ?CuentaContable $cuenta = null): array
    {
        $data = $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('cgl_cuentas')->where('compania_id', $companiaId)->ignore($cuenta?->id),
            ],
            'nombre' => ['required', 'string', 'max:200'],
            'cuenta_padre_id' => [
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'tipo_cuenta_id' => ['required', 'integer', 'exists:cgl_tipos_cuenta,id'],
            'naturaleza' => ['required', Rule::in(['DEBITO', 'CREDITO'])],
            'permite_movimiento' => ['required', 'boolean'],
            'conciliable' => ['required', 'boolean'],
            'activa' => ['required', 'boolean'],
        ]);

        $padre = $data['cuenta_padre_id'] ? CuentaContable::find($data['cuenta_padre_id']) : null;
        $data['nivel'] = $padre ? $padre->nivel + 1 : 1;

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

    private function verificarCompania(Request $request, CuentaContable $cuenta): void
    {
        abort_unless($cuenta->compania_id === $this->companiaActivaId($request), 404);
    }
}
