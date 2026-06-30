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
 *   - data/<tabla>.ndjson por cada tabla con datos de la compañía
 *
 * CLASIFICACIÓN DIRIGIDA POR EL ESQUEMA REAL (a prueba de drift):
 *   - DIRECTA: la tabla tiene columna compania_id -> WHERE compania_id = ?
 *   - HIJA:    no tiene compania_id, pero pertenece a una fila padre por un FK
 *              "dueño". En este esquema el FK dueño es siempre ON DELETE CASCADE
 *              (composición); los FK a catálogos son NO ACTION. Se sigue la
 *              cadena de FKs CASCADE hasta una tabla DIRECTA.
 *   - El resto (catálogos globales, tablas de sistema) NO se exporta y se reporta
 *     en el manifest (tablas_no_exportadas) para no filtrar nada global ni perder
 *     datos en silencio cuando se agregue un módulo nuevo.
 *
 * La detección es por metadatos del motor (pg_constraint en PostgreSQL,
 * PRAGMA foreign_key_list en SQLite), así que se adapta sola al crecer el esquema.
 */
class RespaldoCompania
{
    /** Filas por lote al exportar; acota memoria en compañías grandes. */
    private const CHUNK = 2000;

    /** Profundidad máxima al seguir cadenas de FK (anti-ciclos defensivo). */
    private const MAX_PROFUNDIDAD = 12;

    /**
     * Tablas con compania_id que NO son datos contables sino registros
     * operativos/transitorios; se excluyen aunque tengan compania_id.
     */
    private function excluidasPorNombre(): array
    {
        return ['cxp_importaciones', 'ventas_importaciones', 'respaldos'];
    }

    /** Tablas de infraestructura/catálogo global esperadas (solo para no ruidear el reporte). */
    private function globalesEsperadas(): array
    {
        return [
            'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches',
            'failed_jobs', 'sessions', 'password_reset_tokens',
            'users', 'companias', 'core_companias',
            'core_planes', 'core_zonas', 'zonas', 'core_monedas',
            'core_tipos_documento', 'cgl_tipos_cuenta', 'contact_tipos',
            'bco_bancos', 'tax_tarifas', 'core_modulos', 'core_adjuntos',
            'core_tasas_cambio', 'item_unidades_medida',
            'core_plantillas_cuentas', 'core_plantillas_cuentas_detalle',
            'report_historial', 'report_definiciones',
            'seg_roles', 'seg_permisos', 'seg_usuarios_roles',
            'seg_usuarios_permisos', 'seg_roles_permisos',
            // spatie deja también las tablas con nombre por defecto en algunos entornos
            'permissions', 'roles', 'model_has_permissions',
            'model_has_roles', 'role_has_permissions',
        ];
    }

    /**
     * Dueño explícito para tablas hija que NO declaran FK en el esquema (no se
     * pueden descubrir por metadatos). Se valida contra el esquema antes de usar;
     * si la columna o la tabla padre no existe, se ignora (a prueba de drift).
     */
    private function overridesDueno(): array
    {
        return [
            'cgl_asientos_recurrentes_detalle' => ['fk' => 'recurrente_id', 'padre' => 'cgl_asientos_recurrentes'],
            'cxp_recurrentes_detalle' => ['fk' => 'recurrente_id', 'padre' => 'cxp_recurrentes'],
            'ph_pagos' => ['fk' => 'cuota_id', 'padre' => 'ph_cuotas'],
            // contact_contactos_tipos SÍ declara el FK en Postgres (esquema maestro),
            // pero la migración local usada solo por los tests SQLite no lo declara
            // (unsignedBigInteger suelto, sin ->foreign()) -> PRAGMA foreign_key_list
            // no lo detecta ahí. Override explícito para que el respaldo no la omita
            // bajo SQLite; en Postgres es redundante con el FK real (mismo resultado).
            'contact_contactos_tipos' => ['fk' => 'contacto_id', 'padre' => 'contact_contactos'],
        ];
    }

    // ----- Detección de FKs y del FK "dueño", por metadatos del motor -----

    /**
     * child => [ ['fk'=>col, 'padre'=>tabla, 'cascade'=>bool, 'notnull'=>bool], ... ]
     * Todos los FK de columna simple (no solo CASCADE).
     */
    private function mapaFks(): array
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'pgsql' ? $this->fksPostgres() : $this->fksSqlite();
    }

    private function fksPostgres(): array
    {
        $rows = DB::select("
            SELECT con.conrelid::regclass::text AS child,
                   a.attname AS fk_col,
                   con.confrelid::regclass::text AS parent,
                   (con.confdeltype = 'c') AS cascade,
                   a.attnotnull AS notnull
            FROM pg_constraint con
            JOIN pg_attribute a ON a.attrelid = con.conrelid AND a.attnum = con.conkey[1]
            WHERE con.contype = 'f' AND array_length(con.conkey, 1) = 1
        ");

        $sinEsquema = fn (string $t) => preg_replace('/^[^.]+\./', '', $t);

        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$sinEsquema($r->child)][] = [
                'fk' => $r->fk_col,
                'padre' => $sinEsquema($r->parent),
                'cascade' => (bool) $r->cascade,
                'notnull' => (bool) $r->notnull,
            ];
        }

        return $mapa;
    }

    private function fksSqlite(): array
    {
        $mapa = [];
        foreach ($this->tablasDelEsquema() as $tabla) {
            $notnull = [];
            foreach (DB::select("PRAGMA table_info({$tabla})") as $col) {
                $notnull[$col->name] = (bool) $col->notnull;
            }
            foreach (DB::select("PRAGMA foreign_key_list({$tabla})") as $fk) {
                $mapa[$tabla][] = [
                    'fk' => $fk->from,
                    'padre' => $fk->table,
                    'cascade' => strtoupper((string) $fk->on_delete) === 'CASCADE',
                    'notnull' => $notnull[$fk->from] ?? false,
                ];
            }
        }

        return $mapa;
    }

    private function tablasDelEsquema(): array
    {
        return collect(Schema::getTableListing())
            ->map(fn ($t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t)
            ->unique()
            ->values()
            ->all();
    }

    // ----- Clasificación + resolución del scope por compañía -----

    /** @var array<string,bool> */
    private array $esDirecta = [];

    /** @var array<string,array<int,array{fk:string,padre:string,cascade:bool,notnull:bool}>> */
    private array $fks = [];

    private function clasificar(): void
    {
        $excluidas = $this->excluidasPorNombre();
        $this->fks = $this->mapaFks();
        $this->esDirecta = [];

        foreach ($this->tablasDelEsquema() as $tabla) {
            if (in_array($tabla, $excluidas, true)) {
                continue;
            }
            $this->esDirecta[$tabla] = in_array('compania_id', Schema::getColumnListing($tabla), true);
        }
    }

    /**
     * Devuelve la condición SQL (booleana) que acota una tabla a la compañía,
     * o null si la tabla no pertenece a ninguna compañía (catálogo/sistema).
     *
     * Resolución del FK "dueño":
     *   1. Override explícito (tablas hija sin FK declarado), validado contra el esquema.
     *   2. Entre los FK cuyo padre resuelve a compañía, se elige el de mayor puntaje
     *      (CASCADE y/o NOT NULL). Preferir NOT NULL evita perder filas; el aislamiento
     *      se mantiene aunque haya empate, porque todos los padres son de la compañía.
     *
     * @param  array<string,bool>  $visitando  guarda anti-ciclos
     */
    private function scopeSql(string $tabla, int $companiaId, array $visitando = [], int $prof = 0): ?string
    {
        if ($prof > self::MAX_PROFUNDIDAD || isset($visitando[$tabla])) {
            return null;
        }
        if (in_array($tabla, $this->excluidasPorNombre(), true)) {
            return null;
        }
        if (! empty($this->esDirecta[$tabla])) {
            return "compania_id = {$companiaId}";
        }

        $visitando[$tabla] = true;

        // 1) Override para tablas hija sin FK declarado.
        $ov = $this->overridesDueno()[$tabla] ?? null;
        if ($ov && Schema::hasColumn($tabla, $ov['fk']) && $ov['padre'] !== $tabla) {
            $sub = $this->scopeSql($ov['padre'], $companiaId, $visitando, $prof + 1);
            if ($sub !== null) {
                return "{$ov['fk']} IN (SELECT id FROM {$ov['padre']} WHERE {$sub})";
            }
        }

        // 2) Mejor FK dueño que resuelve a compañía (CASCADE/NOT NULL primero).
        $candidatos = collect($this->fks[$tabla] ?? [])
            ->reject(fn ($fk) => $fk['padre'] === $tabla)
            ->sortByDesc(fn ($fk) => ($fk['cascade'] ? 2 : 0) + ($fk['notnull'] ? 1 : 0))
            ->values();

        foreach ($candidatos as $fk) {
            $sub = $this->scopeSql($fk['padre'], $companiaId, $visitando, $prof + 1);
            if ($sub !== null) {
                return "{$fk['fk']} IN (SELECT id FROM {$fk['padre']} WHERE {$sub})";
            }
        }

        return null;
    }

    // ----- Generación del respaldo -----

    public function generar(Respaldo $respaldo): void
    {
        $companiaId = (int) $respaldo->compania_id;
        $compania = Compania::find($companiaId);
        if (! $compania) {
            throw new RuntimeException("Compañía {$companiaId} inexistente.");
        }

        $this->clasificar();

        // Resolver el scope de cada tabla; las que dan null no pertenecen a compañía.
        $scopes = [];
        $noExportadas = [];
        foreach ($this->tablasDelEsquema() as $tabla) {
            $scope = $this->scopeSql($tabla, $companiaId);
            if ($scope !== null) {
                $scopes[$tabla] = $scope;
            } elseif (! in_array($tabla, $this->globalesEsperadas(), true)
                   && ! in_array($tabla, $this->excluidasPorNombre(), true)) {
                $noExportadas[] = $tabla;
            }
        }
        ksort($scopes);
        sort($noExportadas);

        $respaldo->update([
            'estado' => Respaldo::ESTADO_PROCESANDO,
            'total_tablas' => count($scopes),
            'tablas_procesadas' => 0,
            'total_filas' => 0,
        ]);

        // Directorio temporal bajo la raíz del disco 'local' (storage/app/private),
        // escribible por el usuario del worker (apache); storage/app/tmp NO lo es.
        $trabajo = storage_path('app/private/respaldos-tmp/respaldo_'.$respaldo->id.'_'.uniqid());
        if (! is_dir($trabajo.'/data') && ! mkdir($trabajo.'/data', 0775, true) && ! is_dir($trabajo.'/data')) {
            throw new RuntimeException("No se pudo crear el directorio temporal {$trabajo}/data");
        }

        $conteos = [];
        $checksums = [];
        $totalFilas = 0;

        try {
            $i = 0;
            foreach ($scopes as $tabla => $scope) {
                $archivo = $trabajo.'/data/'.$tabla.'.ndjson';
                [$filas, $sha] = $this->exportarTabla($tabla, $scope, $archivo);

                $conteos[$tabla] = $filas;
                $checksums[$tabla] = $sha;
                $totalFilas += $filas;

                $respaldo->update([
                    'tablas_procesadas' => ++$i,
                    'total_filas' => $totalFilas,
                ]);
            }

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
                'tablas_no_exportadas' => $noExportadas,
            ];
            file_put_contents(
                $trabajo.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $nombreZip = sprintf(
                'respaldo_%s_%s.zip',
                preg_replace('/[^A-Za-z0-9]+/', '-', (string) $compania->nombre) ?: 'compania',
                now()->format('Ymd_His')
            );
            $rutaZip = $trabajo.'/'.$nombreZip;
            $this->empaquetar($trabajo, $rutaZip);

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

    /** Exporta una tabla a NDJSON por lotes. Devuelve [filas, sha256]. */
    private function exportarTabla(string $tabla, string $scopeSql, string $rutaArchivo): array
    {
        $handle = fopen($rutaArchivo, 'w');
        if ($handle === false) {
            throw new RuntimeException("No se pudo escribir {$rutaArchivo}");
        }

        $tieneId = in_array('id', Schema::getColumnListing($tabla), true);
        $filas = 0;

        try {
            $base = DB::table($tabla)->whereRaw($scopeSql);

            if ($tieneId) {
                $base->orderBy('id')->chunkById(self::CHUNK, function ($rows) use ($handle, &$filas) {
                    foreach ($rows as $row) {
                        fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL);
                        $filas++;
                    }
                }, 'id');
            } else {
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
