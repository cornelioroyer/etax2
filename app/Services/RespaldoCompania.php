<?php

namespace App\Services;

use App\Models\Compania;
use App\Models\Respaldo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Genera un respaldo LÓGICO de los datos de UNA compañía.
 *
 * eTax2 es multiempresa sobre una sola base de datos PostgreSQL; el aislamiento
 * es por columna compania_id. Por eso el respaldo NO puede ser un volcado del
 * motor (filtraría otras compañías): se construye tabla por tabla filtrando por
 * la compañía. El resultado es un .zip con:
 *   - manifest.json  (metadatos, versión de esquema, conteos, checksums)
 *   - una/<tabla>.ndjson por cada tabla con datos de la compañía
 *
 * Clasificación de tablas (manifiesto curado, revisado contra el esquema):
 *   - DIRECTAS:  tienen compania_id  -> WHERE compania_id = ?
 *   - HIJAS:     no tienen compania_id, pertenecen a la compañía por un *_id
 *                que encadena hasta una tabla directa -> WHERE fk IN (subquery)
 *   - GLOBALES:  catálogos/sistema compartidos por todas las compañías -> se omiten
 *
 * Cualquier tabla del esquema que no esté en ninguna lista se OMITE y se reporta
 * en el manifest (tablas_no_clasificadas) para no filtrar un catálogo global ni
 * perder datos en silencio cuando se agregue un módulo nuevo.
 */
class RespaldoCompania
{
    /** Filas por lote al exportar; acota memoria en compañías grandes. */
    private const CHUNK = 2000;

    /** Tablas con columna compania_id: se filtran directamente. */
    private function tablasDirectas(): array
    {
        return [
            'afi_activos', 'afi_categorias', 'afi_ubicaciones',
            'audit_actividad',
            'banco_cuentas', 'bco_cuentas', 'bco_depositos', 'bco_movimientos',
            'budget_escenarios', 'budget_presupuestos', 'budget_versiones',
            'caj_cajas', 'caj_movimientos',
            'cgl_asientos', 'cgl_asientos_recurrentes', 'cgl_cierres',
            'cgl_cuentas', 'cgl_diarios', 'cgl_periodos', 'cgl_saldos',
            'compras_ordenes', 'compras_recepciones',
            'contact_contactos',
            'core_cuentas_default',
            'cxp_recurrentes',
            'fel_configuracion', 'fel_documentos',
            'inv_almacenes', 'inv_existencias', 'inv_kardex', 'inv_movimientos',
            'item_productos_servicios',
            'ph_cuotas', 'ph_edificios', 'ph_propietarios', 'ph_tipos_cuota',
            'tax_impuestos',
            'taller_ordenes', 'taller_talleres',
            'ventas_cotizaciones', 'ventas_facturas', 'ventas_recibos',
        ];
    }

    /**
     * Tablas hija: [tabla => [fk, padre]]. El padre puede ser DIRECTA o a su vez
     * otra HIJA (cadena de dos niveles); el scope se resuelve recursivamente.
     */
    private function tablasHijas(): array
    {
        return [
            'afi_bajas' => ['fk' => 'activo_id', 'padre' => 'afi_activos'],
            'afi_depreciaciones' => ['fk' => 'activo_id', 'padre' => 'afi_activos'],
            'afi_movimientos' => ['fk' => 'activo_id', 'padre' => 'afi_activos'],
            'afi_revaluaciones' => ['fk' => 'activo_id', 'padre' => 'afi_activos'],
            'audit_reaperturas' => ['fk' => 'periodo_id', 'padre' => 'cgl_periodos'],
            'budget_presupuestos_detalle' => ['fk' => 'presupuesto_id', 'padre' => 'budget_presupuestos'],
            'caj_arqueos' => ['fk' => 'caja_id', 'padre' => 'caj_cajas'],
            'caj_arqueos_detalle' => ['fk' => 'arqueo_id', 'padre' => 'caj_arqueos'],
            'caj_reembolsos' => ['fk' => 'caja_id', 'padre' => 'caj_cajas'],
            'caj_vales' => ['fk' => 'caja_id', 'padre' => 'caj_cajas'],
            'cgl_asientos_detalle' => ['fk' => 'asiento_id', 'padre' => 'cgl_asientos'],
            'cgl_asientos_recurrentes_detalle' => ['fk' => 'recurrente_id', 'padre' => 'cgl_asientos_recurrentes'],
            'compras_ordenes_detalle' => ['fk' => 'orden_id', 'padre' => 'compras_ordenes'],
            'compras_recepciones_detalle' => ['fk' => 'recepcion_id', 'padre' => 'compras_recepciones'],
            'contact_contactos_tipos' => ['fk' => 'contacto_id', 'padre' => 'contact_contactos'],
            'cxp_recurrentes_detalle' => ['fk' => 'recurrente_id', 'padre' => 'cxp_recurrentes'],
            'fel_documentos_detalle' => ['fk' => 'fel_documento_id', 'padre' => 'fel_documentos'],
            'fel_eventos' => ['fk' => 'fel_documento_id', 'padre' => 'fel_documentos'],
            'inv_movimientos_detalle' => ['fk' => 'movimiento_id', 'padre' => 'inv_movimientos'],
            'ph_pagos' => ['fk' => 'cuota_id', 'padre' => 'ph_cuotas'],
            'ph_unidades' => ['fk' => 'edificio_id', 'padre' => 'ph_edificios'],
            'ventas_cotizaciones_detalle' => ['fk' => 'cotizacion_id', 'padre' => 'ventas_cotizaciones'],
            'ventas_facturas_detalle' => ['fk' => 'factura_id', 'padre' => 'ventas_facturas'],
            'ventas_recibos_detalle' => ['fk' => 'recibo_id', 'padre' => 'ventas_recibos'],
            // Taller: casi todo cuelga de taller_talleres (compania_id) por taller_id.
            'taller_areas' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_checklist' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_checklist_detalle' => ['fk' => 'checklist_id', 'padre' => 'taller_checklist'],
            'taller_configuracion' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_equipos' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_especialidades' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_marcas' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_modelos' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_servicios_estandar' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_sintomas' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_sucursales' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_tecnicos' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
            'taller_tecnico_especialidades' => ['fk' => 'tecnico_id', 'padre' => 'taller_tecnicos'],
            'taller_tipos_equipo' => ['fk' => 'taller_id', 'padre' => 'taller_talleres'],
        ];
    }

    /**
     * Tablas globales/sistema que NO son datos de la compañía y se omiten:
     * catálogos compartidos, infraestructura de Laravel y los propios registros
     * de seguimiento de importaciones/respaldos.
     */
    private function tablasGlobalesExcluidas(): array
    {
        return [
            // Infraestructura Laravel
            'migrations', 'cache', 'cache_locks', 'failed_jobs', 'job_batches',
            'jobs', 'password_reset_tokens', 'sessions',
            // Identidad / seguridad (global). spatie usa tablas seg_* (config/permission.php).
            'users', 'seg_roles', 'seg_permisos', 'seg_usuarios_roles',
            'seg_usuarios_permisos', 'seg_roles_permisos',
            // Catálogos globales compartidos por todas las compañías
            'companias', 'core_companias', 'core_planes', 'core_zonas', 'zonas',
            'core_tipos_documento', 'cgl_tipos_cuenta', 'contact_tipos',
            'bco_bancos',
            // Registros de seguimiento (logs operativos, no datos contables)
            'cxp_importaciones', 'ventas_importaciones', 'respaldos',
        ];
    }

    /**
     * SQL escalar `(SELECT id FROM <tabla> WHERE <scope>)` para usar en un IN.
     * Resuelve la cadena hasta una tabla con compania_id.
     */
    private function subconsultaIds(string $tabla, int $companiaId): string
    {
        if (in_array($tabla, $this->tablasDirectas(), true)) {
            return "(SELECT id FROM {$tabla} WHERE compania_id = {$companiaId})";
        }

        $hijas = $this->tablasHijas();
        if (isset($hijas[$tabla])) {
            $fk = $hijas[$tabla]['fk'];
            $padre = $hijas[$tabla]['padre'];

            return "(SELECT id FROM {$tabla} WHERE {$fk} IN {$this->subconsultaIds($padre, $companiaId)})";
        }

        throw new RuntimeException("Tabla no clasificada para respaldo: {$tabla}");
    }

    /** Query base de una tabla ya acotada a la compañía. */
    private function queryCompania(string $tabla, int $companiaId)
    {
        if (in_array($tabla, $this->tablasDirectas(), true)) {
            return DB::table($tabla)->where('compania_id', $companiaId);
        }

        $hijas = $this->tablasHijas();
        $fk = $hijas[$tabla]['fk'];
        $padre = $hijas[$tabla]['padre'];

        return DB::table($tabla)->whereRaw("{$fk} IN {$this->subconsultaIds($padre, $companiaId)}");
    }

    /**
     * Ejecuta el respaldo: escribe los NDJSON + manifest, los empaqueta en un
     * ZIP, lo deja en el disco privado y actualiza el registro de seguimiento.
     */
    public function generar(Respaldo $respaldo): void
    {
        $companiaId = (int) $respaldo->compania_id;
        $compania = Compania::find($companiaId);
        if (! $compania) {
            throw new RuntimeException("Compañía {$companiaId} inexistente.");
        }

        $tablas = array_merge($this->tablasDirectas(), array_keys($this->tablasHijas()));
        sort($tablas);

        $respaldo->update([
            'estado' => Respaldo::ESTADO_PROCESANDO,
            'total_tablas' => count($tablas),
            'tablas_procesadas' => 0,
            'total_filas' => 0,
        ]);

        // Directorio temporal de trabajo.
        $trabajo = storage_path('app/tmp/respaldo_'.$respaldo->id.'_'.uniqid());
        @mkdir($trabajo.'/data', 0775, true);

        $conteos = [];
        $checksums = [];
        $totalFilas = 0;

        try {
            foreach ($tablas as $i => $tabla) {
                if (! Schema::hasTable($tabla)) {
                    $conteos[$tabla] = 0;
                    $respaldo->update(['tablas_procesadas' => $i + 1]);
                    continue;
                }

                $archivo = $trabajo.'/data/'.$tabla.'.ndjson';
                [$filas, $sha] = $this->exportarTabla($tabla, $companiaId, $archivo);

                $conteos[$tabla] = $filas;
                $checksums[$tabla] = $sha;
                $totalFilas += $filas;

                $respaldo->update([
                    'tablas_procesadas' => $i + 1,
                    'total_filas' => $totalFilas,
                ]);
            }

            // Guardrail: tablas del esquema no contempladas en ninguna lista.
            $noClasificadas = $this->tablasNoClasificadas();

            $manifest = [
                'aplicacion' => 'eTax2',
                'tipo' => 'respaldo_compania',
                'version_formato' => 1,
                'generado_en' => now()->toIso8601String(),
                'generado_por' => $respaldo->usuario,
                'version_esquema' => DB::table('migrations')->max('migration'),
                'compania' => [
                    'id' => $compania->id,
                    'nombre' => $compania->nombre,
                    'ruc' => $compania->ruc,
                    'dv' => $compania->dv,
                ],
                'total_filas' => $totalFilas,
                'tablas' => $conteos,
                'checksums_sha256' => $checksums,
                'tablas_no_clasificadas_omitidas' => $noClasificadas,
            ];
            file_put_contents(
                $trabajo.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // Empaquetar ZIP.
            $nombreZip = sprintf(
                'respaldo_%s_%s.zip',
                preg_replace('/[^A-Za-z0-9]+/', '-', (string) $compania->nombre) ?: 'compania',
                now()->format('Ymd_His')
            );
            $rutaZip = $trabajo.'/'.$nombreZip;
            $this->empaquetar($trabajo, $rutaZip);

            // Mover al disco privado, namespaced por compañía.
            $disco = config('filesystems.default', 'local');
            $rutaDisco = "respaldos/{$companiaId}/{$nombreZip}";
            Storage::disk($disco)->put($rutaDisco, fopen($rutaZip, 'r'));

            $respaldo->update([
                'estado' => Respaldo::ESTADO_COMPLETADO,
                'archivo' => $nombreZip,
                'ruta' => $rutaDisco,
                'disco' => $disco,
                'bytes' => filesize($rutaZip),
                'checksum' => hash_file('sha256', $rutaZip),
                'total_filas' => $totalFilas,
                'terminado_at' => now(),
            ]);
        } finally {
            $this->limpiarDirectorio($trabajo);
        }
    }

    /**
     * Exporta una tabla a NDJSON por lotes. Devuelve [filas, sha256].
     */
    private function exportarTabla(string $tabla, int $companiaId, string $rutaArchivo): array
    {
        $handle = fopen($rutaArchivo, 'w');
        if ($handle === false) {
            throw new RuntimeException("No se pudo escribir {$rutaArchivo}");
        }

        $columnas = Schema::getColumnListing($tabla);
        $tieneId = in_array('id', $columnas, true);
        $filas = 0;

        try {
            $base = $this->queryCompania($tabla, $companiaId);

            if ($tieneId) {
                // chunkById: estable y acotado en memoria.
                $base->orderBy('id')->chunkById(self::CHUNK, function ($rows) use ($handle, &$filas) {
                    foreach ($rows as $row) {
                        fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL);
                        $filas++;
                    }
                }, 'id');
            } else {
                // Tablas sin id (pivotes): cursor en streaming.
                foreach ($base->cursor() as $row) {
                    fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL);
                    $filas++;
                }
            }
        } finally {
            fclose($handle);
        }

        return [$filas, hash_file('sha256', $rutaArchivo)];
    }

    /** Lista las tablas del esquema que no están en ninguna lista del manifiesto. */
    private function tablasNoClasificadas(): array
    {
        $todas = collect(Schema::getTableListing())
            ->map(fn ($t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t);

        $conocidas = array_merge(
            $this->tablasDirectas(),
            array_keys($this->tablasHijas()),
            $this->tablasGlobalesExcluidas(),
        );

        return $todas->reject(fn ($t) => in_array($t, $conocidas, true))->values()->all();
    }

    /** Empaqueta manifest.json + data/*.ndjson en un ZIP. */
    private function empaquetar(string $dirTrabajo, string $rutaZip): void
    {
        $zip = new ZipArchive();
        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear el ZIP {$rutaZip}");
        }

        $zip->addFile($dirTrabajo.'/manifest.json', 'manifest.json');

        foreach (glob($dirTrabajo.'/data/*.ndjson') as $archivo) {
            $zip->addFile($archivo, 'data/'.basename($archivo));
        }

        $zip->close();
    }

    private function limpiarDirectorio(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.'/data/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($dir.'/*') ?: [] as $f) {
            is_file($f) && @unlink($f);
        }
        @rmdir($dir.'/data');
        @rmdir($dir);
    }
}
