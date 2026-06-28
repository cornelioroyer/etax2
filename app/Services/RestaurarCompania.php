<?php

namespace App\Services;

use App\Models\Compania;
use App\Models\Restauracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * Restaura un respaldo LÓGICO (el .zip que genera RespaldoCompania) en una
 * compañía NUEVA de la MISMA instancia eTax2.
 *
 * Por qué "compañía nueva" y "misma instancia":
 *   - eTax2 es multiempresa sobre UNA sola base de datos; las claves primarias
 *     son globales. Restaurar las filas con su id original chocaría con las de
 *     otras compañías, así que TODO id se reasigna y los FK internos del respaldo
 *     se reescriben al nuevo id (remapeo topológicamente irrelevante porque se
 *     hace con restricciones desactivadas; ver abajo).
 *   - Los catálogos GLOBALES (monedas, tipos de documento, bancos, usuarios…) no
 *     viajan en el respaldo; como restauramos en la misma BD, los FK a globales
 *     siguen siendo válidos y NO se tocan.
 *   - Se crea una compañía destino nueva (no se restaura sobre una existente)
 *     para no chocar con índices únicos ni mezclar datos.
 *
 * Por qué se desactivan los disparadores durante la inserción:
 *   Las tablas contables tienen disparadores que imponen el FLUJO DE CREACIÓN
 *   (período abierto, cuadre leyendo el detalle, recálculo de cgl_saldos…). Una
 *   restauración inserta filas ya consistentes y debe ser FIEL al origen, así que
 *   se usa `session_replication_role = replica` (equivalente a
 *   `pg_restore --disable-triggers`): desactiva disparadores de usuario y la
 *   verificación de llaves foráneas durante la transacción. Requiere rol con
 *   privilegio; en eTax2 el rol de la app lo tiene. Todo ocurre dentro de UNA
 *   transacción: o se restaura entero o no se restaura nada.
 */
class RestaurarCompania
{
    /** Filas por INSERT al volcar; acota memoria y tamaño de sentencia. */
    private const CHUNK_INSERT = 500;

    /** @var array<string,bool> tabla => tiene columna compania_id */
    private array $esDirecta = [];

    /** @var array<string,array<int,array{fk:string,padre:string}>> FKs de 1 columna por tabla */
    private array $fks = [];

    /** @var array<string,array<int,string>> tabla => columnas reales en destino */
    private array $columnas = [];

    private ?string $connProgreso = null;

    public function restaurar(Restauracion $rest): void
    {
        $zip = (string) $rest->archivo_tmp;
        if (! is_file($zip)) {
            throw new RuntimeException("El archivo del respaldo no está disponible: {$zip}");
        }

        $trabajo = storage_path('app/private/restauraciones-tmp/rest_'.$rest->id.'_'.uniqid());
        if (! is_dir($trabajo) && ! mkdir($trabajo, 0775, true) && ! is_dir($trabajo)) {
            throw new RuntimeException("No se pudo crear el directorio temporal {$trabajo}");
        }

        try {
            [$manifest, $dataDir] = $this->abrirYValidar($zip, $trabajo);

            $this->progreso($rest, [
                'estado' => Restauracion::ESTADO_PROCESANDO,
                'compania_origen_id' => $manifest['compania']['id'] ?? null,
                'compania_origen_nombre' => $manifest['compania']['nombre'] ?? null,
            ]);

            // Tablas a restaurar = las del zip que existen en el esquema destino.
            $this->introspeccionar();
            $omitidasPorEsquema = [];
            $tablas = [];
            foreach (array_keys($manifest['tablas'] ?? []) as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    $omitidasPorEsquema[] = $tabla;

                    continue;
                }
                $tablas[] = $tabla;
            }
            sort($tablas);

            $this->progreso($rest, ['total_tablas' => count($tablas), 'tablas_procesadas' => 0, 'total_filas' => 0]);

            $reporte = [
                'tablas_omitidas_no_existen' => $omitidasPorEsquema,
                'columnas_omitidas' => [],
                'conteos' => [],
            ];

            // Conjunto de tablas que se restauran (para decidir qué FK remapear).
            $enRespaldo = array_flip($tablas);

            // Orden de inserción: padres antes que hijos. En PostgreSQL las FK van
            // desactivadas (replica) y el orden es indiferente, pero el orden
            // topológico mantiene la integridad referencial en cualquier motor
            // (p. ej. SQLite en pruebas, que sí valida FK dentro de la transacción).
            $ordenInsercion = $this->ordenarTopologico($tablas, $enRespaldo);

            DB::beginTransaction();
            try {
                if ($this->esPgsql()) {
                    DB::statement('SET session_replication_role = replica');
                }

                // 1) Compañía destino nueva (vacía: no se siembra catálogo).
                // En la misma instancia el RUC es único: si el del respaldo ya
                // existe (la compañía original sigue viva), se deja en blanco —
                // es una copia y el usuario fijará el RUC. Si está libre (p. ej.
                // se restaura una compañía eliminada), se reutiliza.
                $ruc = $manifest['compania']['ruc'] ?? null;
                $dv = $manifest['compania']['dv'] ?? null;
                if ($ruc !== null && $ruc !== '') {
                    $existe = DB::table('core_companias')
                        ->where('ruc', $ruc)
                        ->when($dv !== null && $dv !== '', fn ($q) => $q->where('dv', $dv))
                        ->exists();
                    if ($existe) {
                        $ruc = null;
                        $dv = null;
                    }
                } else {
                    $ruc = null;
                    $dv = null;
                }

                $compania = Compania::create([
                    'nombre' => $rest->compania_destino_nombre
                        ?: (($manifest['compania']['nombre'] ?? 'Compañía').' (restaurado)'),
                    'ruc' => $ruc,
                    'dv' => $dv,
                    'activa' => true,
                    'created_by' => null,
                ]);
                $companiaId = (int) $compania->id;

                // 2) Pre-asignar ids nuevos por tabla (mapa old_id -> new_id).
                $mapaIds = [];
                $filasPorTabla = [];
                foreach ($tablas as $tabla) {
                    $rows = $this->leerNdjson($dataDir.'/'.$tabla.'.ndjson');
                    $filasPorTabla[$tabla] = $rows;
                    if ($this->tieneId($tabla) && $rows) {
                        $mapaIds[$tabla] = $this->reservarIds($tabla, $rows);
                    }
                }

                // 3) Volcar remapeando id, compania_id y FK internos.
                $i = 0;
                $totalFilas = 0;
                foreach ($ordenInsercion as $tabla) {
                    [$n, $colsOmitidas] = $this->volcarTabla(
                        $tabla, $filasPorTabla[$tabla], $companiaId, $mapaIds, $enRespaldo
                    );
                    $reporte['conteos'][$tabla] = $n;
                    if ($colsOmitidas) {
                        $reporte['columnas_omitidas'][$tabla] = array_values($colsOmitidas);
                    }
                    $totalFilas += $n;
                    $this->progreso($rest, [
                        'tablas_procesadas' => ++$i,
                        'total_filas' => $totalFilas,
                    ]);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            } finally {
                // En commit el SET persiste en la sesión; en rollback ya revirtió.
                // Lo reponemos siempre para no dejar la conexión en modo replica.
                if ($this->esPgsql()) {
                    try {
                        DB::statement('SET session_replication_role = DEFAULT');
                    } catch (\Throwable $e) {
                        // ignorar
                    }
                }
            }

            $this->progreso($rest, [
                'estado' => Restauracion::ESTADO_COMPLETADO,
                'compania_destino_id' => $companiaId,
                'reporte' => json_encode($reporte, JSON_UNESCAPED_UNICODE),
                'terminado_at' => now(),
            ]);
        } finally {
            $this->limpiar($trabajo);
            // El zip temporal subido se elimina tras procesarlo.
            if ($rest->archivo_tmp && str_contains((string) $rest->archivo_tmp, 'restauraciones-tmp')) {
                @unlink($rest->archivo_tmp);
            }
        }
    }

    // ---------------------------------------------------------------- Validación

    /** Extrae el zip, valida el manifest y los checksums. Devuelve [manifest, dataDir]. */
    private function abrirYValidar(string $zipPath, string $trabajo): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('El archivo no es un ZIP válido.');
        }
        if (! $zip->extractTo($trabajo)) {
            $zip->close();
            throw new RuntimeException('No se pudo extraer el respaldo.');
        }
        $zip->close();

        $manifestPath = $trabajo.'/manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException('El respaldo no contiene manifest.json (¿no es un respaldo de eTax2?).');
        }
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ($manifest['tipo'] ?? null) !== 'respaldo_compania') {
            throw new RuntimeException('El manifest no corresponde a un respaldo de compañía de eTax2.');
        }
        if ((int) ($manifest['version_formato'] ?? 0) !== 1) {
            throw new RuntimeException('Versión de formato de respaldo no soportada.');
        }

        $dataDir = $trabajo.'/data';
        foreach (($manifest['checksums_sha256'] ?? []) as $tabla => $sha) {
            $archivo = $dataDir.'/'.$tabla.'.ndjson';
            if (! is_file($archivo)) {
                throw new RuntimeException("Falta el archivo de datos de {$tabla} en el respaldo.");
            }
            if (hash_file('sha256', $archivo) !== $sha) {
                throw new RuntimeException("El respaldo está corrupto: checksum no coincide en {$tabla}.");
            }
        }

        return [$manifest, $dataDir];
    }

    // ------------------------------------------------------------- Introspección

    private function introspeccionar(): void
    {
        $this->fks = $this->mapaFks();
        $this->esDirecta = [];
        $this->columnas = [];
    }

    private function esPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * Ordena las tablas de modo que cada padre (FK dentro del conjunto a
     * restaurar) quede antes que sus hijas. Los ciclos se rompen de forma
     * arbitraria; en PostgreSQL las FK van desactivadas (replica), así que el
     * orden solo importa para motores que validan FK (SQLite en pruebas).
     *
     * @param  array<string,int>  $enRespaldo  conjunto de tablas a restaurar
     * @return array<int,string>
     */
    private function ordenarTopologico(array $tablas, array $enRespaldo): array
    {
        $dep = [];
        foreach ($tablas as $t) {
            $dep[$t] = [];
            foreach (($this->fks[$t] ?? []) as $fk) {
                $p = $fk['padre'];
                if ($p !== $t && isset($enRespaldo[$p])) {
                    $dep[$t][$p] = true;
                }
            }
        }

        $orden = [];
        $estado = []; // 0=sin visitar, 1=en proceso, 2=listo
        $visitar = function (string $t) use (&$visitar, &$dep, &$estado, &$orden): void {
            $e = $estado[$t] ?? 0;
            if ($e !== 0) {
                return; // listo, o en proceso (ciclo) -> cortar
            }
            $estado[$t] = 1;
            foreach (array_keys($dep[$t] ?? []) as $p) {
                $visitar($p);
            }
            $estado[$t] = 2;
            $orden[] = $t;
        };
        foreach ($tablas as $t) {
            $visitar($t);
        }

        return $orden;
    }

    private function columnasDe(string $tabla): array
    {
        return $this->columnas[$tabla] ??= Schema::getColumnListing($tabla);
    }

    private function esDirecta(string $tabla): bool
    {
        return $this->esDirecta[$tabla] ??= in_array('compania_id', $this->columnasDe($tabla), true);
    }

    private function tieneId(string $tabla): bool
    {
        return in_array('id', $this->columnasDe($tabla), true);
    }

    /** child => [ ['fk'=>col, 'padre'=>tabla], ... ] (solo FK de 1 columna). */
    private function mapaFks(): array
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? $this->fksPostgres()
            : $this->fksSqlite();
    }

    private function fksPostgres(): array
    {
        $rows = DB::select("
            SELECT con.conrelid::regclass::text AS child,
                   a.attname AS fk_col,
                   con.confrelid::regclass::text AS parent
            FROM pg_constraint con
            JOIN pg_attribute a ON a.attrelid = con.conrelid AND a.attnum = con.conkey[1]
            WHERE con.contype = 'f' AND array_length(con.conkey, 1) = 1
        ");
        $sinEsquema = fn (string $t) => preg_replace('/^[^.]+\./', '', $t);
        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$sinEsquema($r->child)][] = ['fk' => $r->fk_col, 'padre' => $sinEsquema($r->parent)];
        }

        return $mapa;
    }

    private function fksSqlite(): array
    {
        $mapa = [];
        $tablas = collect(Schema::getTableListing())
            ->map(fn ($t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t)
            ->unique();
        foreach ($tablas as $tabla) {
            foreach (DB::select("PRAGMA foreign_key_list({$tabla})") as $fk) {
                $mapa[$tabla][] = ['fk' => $fk->from, 'padre' => $fk->table];
            }
        }

        return $mapa;
    }

    // ----------------------------------------------------------------- Volcado

    /** Lee un NDJSON a un arreglo de filas asociativas. */
    private function leerNdjson(string $archivo): array
    {
        if (! is_file($archivo)) {
            return [];
        }
        $rows = [];
        $h = fopen($archivo, 'r');
        if ($h === false) {
            throw new RuntimeException("No se pudo leer {$archivo}");
        }
        try {
            while (($linea = fgets($h)) !== false) {
                $linea = trim($linea);
                if ($linea === '') {
                    continue;
                }
                $rows[] = json_decode($linea, true);
            }
        } finally {
            fclose($h);
        }

        return $rows;
    }

    /**
     * Reserva ids nuevos para cada fila (mapa old_id -> new_id).
     * En PostgreSQL avanza la secuencia con nextval (seguro ante concurrencia);
     * en SQLite usa max(id)+offset (entornos de prueba, monohilo).
     */
    private function reservarIds(string $tabla, array $rows): array
    {
        $n = count($rows);
        $nuevos = [];

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$tabla, 'id'])->s ?? null;
            if ($seq) {
                $vals = DB::select('SELECT nextval(?) AS v FROM generate_series(1, ?)', [$seq, $n]);
                $nuevos = array_map(fn ($r) => (int) $r->v, $vals);
            }
        }

        if (! $nuevos) {
            $max = (int) (DB::table($tabla)->max('id') ?? 0);
            $nuevos = range($max + 1, $max + $n);
        }

        $mapa = [];
        foreach ($rows as $idx => $row) {
            $old = $row['id'] ?? null;
            if ($old !== null) {
                $mapa[(string) $old] = $nuevos[$idx];
            }
        }

        return $mapa;
    }

    /**
     * Inserta las filas de una tabla remapeando id, compania_id y FK internos.
     * Devuelve [filas_insertadas, columnas_omitidas].
     *
     * @param  array<string,array<string,int>>  $mapaIds  tabla => (old_id => new_id)
     * @param  array<string,int>  $enRespaldo  conjunto de tablas que se restauran
     */
    private function volcarTabla(string $tabla, array $rows, int $companiaId, array $mapaIds, array $enRespaldo): array
    {
        if (! $rows) {
            return [0, []];
        }

        $colsDest = array_flip($this->columnasDe($tabla));
        $esDirecta = $this->esDirecta($tabla);
        $tieneId = $this->tieneId($tabla);

        // FK de esta tabla cuyo padre también se restaura (excepto compania_id,
        // que se fija explícitamente al destino).
        $fkRemap = [];
        foreach (($this->fks[$tabla] ?? []) as $fk) {
            if ($fk['fk'] === 'compania_id') {
                continue;
            }
            if (isset($enRespaldo[$fk['padre']]) && isset($colsDest[$fk['fk']])) {
                $fkRemap[$fk['fk']] = $fk['padre'];
            }
        }

        $colsOmitidas = [];
        $buffer = [];
        $insertadas = 0;

        foreach ($rows as $row) {
            $fila = [];
            foreach ($row as $col => $val) {
                if (! isset($colsDest[$col])) {
                    $colsOmitidas[$col] = $col;

                    continue;
                }
                $fila[$col] = $val;
            }

            if ($tieneId && isset($mapaIds[$tabla][(string) ($row['id'] ?? '')])) {
                $fila['id'] = $mapaIds[$tabla][(string) $row['id']];
            }
            if ($esDirecta) {
                $fila['compania_id'] = $companiaId;
            }
            foreach ($fkRemap as $col => $padre) {
                $v = $fila[$col] ?? null;
                if ($v !== null && isset($mapaIds[$padre][(string) $v])) {
                    $fila[$col] = $mapaIds[$padre][(string) $v];
                }
            }

            $buffer[] = $fila;
            if (count($buffer) >= self::CHUNK_INSERT) {
                DB::table($tabla)->insert($buffer);
                $insertadas += count($buffer);
                $buffer = [];
            }
        }
        if ($buffer) {
            DB::table($tabla)->insert($buffer);
            $insertadas += count($buffer);
        }

        return [$insertadas, $colsOmitidas];
    }

    // ----------------------------------------------------------------- Progreso

    /**
     * Escribe progreso/estado en una conexión INDEPENDIENTE de la transacción de
     * restauración, para que la barra de la UI lo vea antes del commit. En SQLite
     * (pruebas) usa la misma conexión (no se puede clonar :memory:).
     */
    private function progreso(Restauracion $rest, array $attrs): void
    {
        DB::connection($this->progresoConn())
            ->table('restauraciones')
            ->where('id', $rest->id)
            ->update($attrs + ['updated_at' => now()]);

        foreach ($attrs as $k => $v) {
            $rest->setAttribute($k, $v);
        }
        $rest->syncOriginal();
    }

    private function progresoConn(): string
    {
        if ($this->connProgreso !== null) {
            return $this->connProgreso;
        }
        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver");

        if ($driver === 'pgsql') {
            $nombre = $default.'_progreso';
            if (! config("database.connections.{$nombre}")) {
                config(["database.connections.{$nombre}" => config("database.connections.{$default}")]);
            }
            $this->connProgreso = $nombre;
        } else {
            $this->connProgreso = $default;
        }

        return $this->connProgreso;
    }

    private function limpiar(string $dir): void
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
