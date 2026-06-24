<?php

namespace App\Models;

use App\Observers\AsientoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[ObservedBy(AsientoObserver::class)]
class Asiento extends Model
{
    protected $table = 'cgl_asientos';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_POSTEADO = 'POSTEADO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'compania_id',
        'periodo_id',
        'diario_id',
        'numero',
        'fecha',
        'descripcion',
        'referencia',
        'estado',
        'origen_modulo',
        'origen_tabla',
        'origen_id',
        'total_debito',
        'total_credito',
        'usuario_id',
        'posteado_por',
        'fecha_posteo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_posteo' => 'datetime',
            'total_debito' => 'decimal:2',
            'total_credito' => 'decimal:2',
        ];
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(AsientoDetalle::class, 'asiento_id')->orderBy('linea');
    }

    public function diario(): BelongsTo
    {
        return $this->belongsTo(Diario::class, 'diario_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }

    public function posteadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posteado_por');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function esPosteado(): bool
    {
        return $this->estado === self::ESTADO_POSTEADO;
    }

    /**
     * Asiento manual (capturado en Contabilidad), sin libro auxiliar detrás.
     * Solo estos pueden re-emitirse; los de módulo (CXC, CXP, VEN…) se
     * corrigen anulando su documento de origen.
     */
    public function esManual(): bool
    {
        return $this->origen_modulo === 'CGL';
    }

    /** Nombre legible del módulo de origen de un asiento de módulo. */
    public const MODULOS_ORIGEN = [
        'CXC' => 'Cuentas por Cobrar',
        'CXP' => 'Cuentas por Pagar',
        'VENTAS' => 'Ventas',
        'COMPRAS' => 'Compras',
        'BANCOS' => 'Bancos',
        'INVENTARIO' => 'Inventario',
        'CAJA' => 'Caja',
        'AFI' => 'Activos Fijos',
    ];

    /**
     * Etiqueta del módulo de origen, solo para módulos operativos conocidos.
     * Devuelve null para manuales y para orígenes sin módulo navegable (p.ej.
     * MIGRACION_PEACHTREE), que no tienen "documento de origen" que editar.
     */
    public function nombreModuloOrigen(): ?string
    {
        return self::MODULOS_ORIGEN[$this->origen_modulo] ?? null;
    }

    /** Etiquetas de origen disponibles como filtro (espejo de etiquetaOrigen()). */
    public const ETIQUETAS_ORIGEN = [
        'Diario', 'Ventas', 'Compras', 'Cuentas por Cobrar', 'Cuentas por Pagar',
        'Bancos', 'Inventario', 'Caja', 'Activos Fijos', 'Migración', 'Cierre anual',
    ];

    /**
     * Filtra por la etiqueta de origen mostrada en el listado. Refleja la misma
     * lógica que etiquetaOrigen() (que usa origen_tabla cuando es más específica
     * que el módulo: las tablas ventas_ y compras_ mandan sobre CXC/CXP).
     */
    public function scopeOrigenEtiqueta($query, string $etiqueta)
    {
        return match ($etiqueta) {
            'Diario' => $query->where(fn ($q) => $q->whereIn('origen_modulo', ['CGL', 'DIARIO'])->orWhereNull('origen_modulo')),
            'Ventas' => $query->where('origen_tabla', 'like', 'ventas_%'),
            'Compras' => $query->where('origen_tabla', 'like', 'compras_%'),
            'Cuentas por Cobrar' => $query->where('origen_modulo', 'CXC')
                ->where(fn ($q) => $q->where('origen_tabla', 'not like', 'ventas_%')->orWhereNull('origen_tabla')),
            'Cuentas por Pagar' => $query->where('origen_modulo', 'CXP')
                ->where(fn ($q) => $q->where('origen_tabla', 'not like', 'compras_%')->orWhereNull('origen_tabla')),
            'Bancos' => $query->where('origen_modulo', 'BANCOS'),
            'Inventario' => $query->where('origen_modulo', 'INVENTARIO'),
            'Caja' => $query->where('origen_modulo', 'CAJA'),
            'Activos Fijos' => $query->where('origen_modulo', 'AFI'),
            'Migración' => $query->whereIn('origen_modulo', ['MIGRACION_PT', 'MIGRACION_PEACHTREE']),
            'Cierre anual' => $query->where('origen_modulo', 'CIERRE_ANUAL'),
            default => $query,
        };
    }

    public function etiquetaOrigen(): string
    {
        if ($this->esManual() || ! $this->origen_modulo || $this->origen_modulo === 'DIARIO') {
            return 'Diario';
        }

        // La tabla de origen es más específica que el módulo contable: una
        // factura de venta postea al submayor CXC pero su módulo funcional es
        // Ventas; una factura de compra al CXP pero viene de Compras.
        $porTabla = match (true) {
            str_starts_with((string) $this->origen_tabla, 'ventas_') => 'Ventas',
            str_starts_with((string) $this->origen_tabla, 'compras_') => 'Compras',
            default => null,
        };

        return $porTabla
            ?? self::MODULOS_ORIGEN[$this->origen_modulo]
            ?? match ($this->origen_modulo) {
                'MIGRACION_PEACHTREE', 'MIGRACION_PT' => 'Migración',
                'CIERRE_ANUAL' => 'Cierre anual',
                default => ucfirst(strtolower($this->origen_modulo)),
            };
    }

    /**
     * URL del documento que originó un asiento de módulo, para "editar en la
     * fuente". El discriminador es el par origen_modulo|origen_tabla (el tabla
     * solo no basta: p.ej. ventas factura y NC comparten 'ventas_facturas').
     * Devuelve null si el origen aún no está mapeado: el llamador hace fallback
     * a un mensaje, nunca a un enlace roto.
     */
    public function urlOrigen(): ?string
    {
        if ($this->esManual() || ! $this->origen_id) {
            return null;
        }

        return match ($this->origen_modulo.'|'.$this->origen_tabla) {
            'CXC|cxc_documentos' => $this->rutaDocumentoSubmayor('cxc_documentos', [
                'admin.cxc.facturas.show' => [CxcDocumento::TIPO_FACTURA, CxcDocumento::TIPO_REEMBOLSO],
                'admin.cxc.cobros.show' => [CxcDocumento::TIPO_PAGO],
                'admin.cxc.notas.show' => [CxcDocumento::TIPO_NOTA_CREDITO, CxcDocumento::TIPO_NOTA_DEBITO],
            ]),
            'CXP|cxp_documentos' => $this->rutaDocumentoSubmayor('cxp_documentos', [
                'admin.cxp.facturas.show' => [CxpDocumento::TIPO_FACTURA, CxpDocumento::TIPO_REEMBOLSO, CxpDocumento::TIPO_IMPORTACION],
                'admin.cxp.pagos.show' => [CxpDocumento::TIPO_PAGO, CxpDocumento::TIPO_RETENCION],
                'admin.cxp.anticipos.show' => [CxpDocumento::TIPO_ANTICIPO],
                'admin.cxp.notas.show' => [CxpDocumento::TIPO_NOTA_CREDITO, CxpDocumento::TIPO_NOTA_DEBITO],
            ]),
            'CXC|ventas_facturas' => route('admin.ventas.facturas.show', $this->origen_id),
            'VENTAS|ventas_facturas' => route('admin.ventas.notas-credito.show', $this->origen_id),
            'VENTAS|ventas_recibos' => route('admin.ventas.recibos.show', $this->origen_id),
            'INVENTARIO|inv_movimientos' => route('admin.inventario.movimientos.show', $this->origen_id),
            'BANCOS|bco_movimientos' => route('admin.bco.movimientos.show', $this->origen_id),
            // Caja: el movimiento/reembolso/arqueo no tiene show propio; se abre
            // la caja a la que pertenece (su pantalla lista los movimientos).
            'CAJA|caj_movimientos' => $this->rutaPorPadre('caj_movimientos', 'caja_id', 'admin.caja.show'),
            'CAJA|caj_reembolsos' => $this->rutaPorPadre('caj_reembolsos', 'caja_id', 'admin.caja.show'),
            'CAJA|caj_arqueos' => $this->rutaPorPadre('caj_arqueos', 'caja_id', 'admin.caja.show'),
            // Activos fijos: el alta usa el id del activo; depreciación y baja
            // cuelgan de un activo, así que se abre la ficha del activo.
            'AFI|afi_activos' => route('admin.activos.show', $this->origen_id),
            'AFI|afi_depreciaciones' => $this->rutaPorPadre('afi_depreciaciones', 'activo_id', 'admin.activos.show'),
            'AFI|afi_bajas' => $this->rutaPorPadre('afi_bajas', 'activo_id', 'admin.activos.show'),
            default => null,
        };
    }

    /**
     * Ruta show del registro padre cuando el asiento nace de un detalle que
     * cuelga de otro (un movimiento de caja pertenece a una caja; una
     * depreciación a un activo). Resuelve la FK $columna desde origen_id.
     */
    private function rutaPorPadre(string $tabla, string $columna, string $ruta): ?string
    {
        $padreId = DB::table($tabla)->where('id', $this->origen_id)->value($columna);

        return $padreId ? route($ruta, $padreId) : null;
    }

    /**
     * Resuelve la ruta show de un documento de submayor (cxc/cxp) según su
     * tipo_documento, que distingue factura/cobro-pago/nota dentro de una sola
     * tabla. $rutas mapea nombre_ruta => lista de tipos que le corresponden.
     *
     * @param  array<string,array<int,string>>  $rutas
     */
    private function rutaDocumentoSubmayor(string $tabla, array $rutas): ?string
    {
        $tipo = DB::table($tabla)->where('id', $this->origen_id)->value('tipo_documento');

        if (! $tipo) {
            return null;
        }

        foreach ($rutas as $ruta => $tipos) {
            if (in_array($tipo, $tipos, true)) {
                return route($ruta, $this->origen_id);
            }
        }

        return null;
    }

    /**
     * Siguiente número AS-NNNNNN de la compañía.
     * Llamar dentro de una transacción.
     */
    public static function siguienteNumero(int $companiaId): string
    {
        // PostgreSQL no permite FOR UPDATE con agregados (max); usamos un
        // advisory lock de transacción para serializar la numeración.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['asiento-'.$companiaId]);
        }

        $ultimo = self::where('compania_id', $companiaId)
            ->where('numero', 'like', 'AS-%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, 3)) + 1 : 1;

        return 'AS-'.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }
}
