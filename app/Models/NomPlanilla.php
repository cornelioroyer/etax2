<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Corrida de planilla: documento con ciclo de estados. Nada se borra tras
 * contabilizar — la anulación reversa el asiento (patrón de los módulos
 * CxC/CxP).
 */
class NomPlanilla extends Model
{
    protected $table = 'nom_planillas';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_PROCESADA = 'PROCESADA';

    public const ESTADO_CONTABILIZADA = 'CONTABILIZADA';

    public const ESTADO_ANULADA = 'ANULADA';

    public const TIPO_REGULAR = 'REGULAR';

    public const PREFIJO = 'NP-';

    protected $fillable = [
        'compania_id',
        'periodo_id',
        'numero',
        'tipo',
        'descripcion',
        'estado',
        'fecha',
        'total_ingresos',
        'total_deducciones',
        'total_neto',
        'total_patronal',
        'asiento_id',
        'usuario_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total_ingresos' => 'decimal:2',
            'total_deducciones' => 'decimal:2',
            'total_neto' => 'decimal:2',
            'total_patronal' => 'decimal:2',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NomPeriodo::class, 'periodo_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(NomMovimiento::class, 'planilla_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function esProcesada(): bool
    {
        return $this->estado === self::ESTADO_PROCESADA;
    }

    public function estaContabilizada(): bool
    {
        return $this->estado === self::ESTADO_CONTABILIZADA;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    /**
     * Siguiente número NP- de la compañía. Llamar dentro de una transacción.
     */
    public static function siguienteNumero(int $companiaId): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$companiaId.'-'.self::PREFIJO]);
        }

        $ultimo = self::where('compania_id', $companiaId)
            ->where('numero', 'like', self::PREFIJO.'%')
            ->max('numero');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen(self::PREFIJO))) + 1 : 1;

        return self::PREFIJO.str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }
}
