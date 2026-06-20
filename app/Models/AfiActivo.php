<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AfiActivo extends Model
{
    protected $table = 'afi_activos';

    public const ESTADO_ACTIVO        = 'ACTIVO';
    public const ESTADO_DADO_DE_BAJA  = 'DADO_DE_BAJA';

    protected $fillable = [
        'compania_id', 'codigo', 'descripcion', 'categoria_id', 'ubicacion_id',
        'fecha_compra', 'fecha_inicio_depreciacion',
        'valor_compra', 'valor_residual', 'vida_util_meses', 'metodo_depreciacion',
        'cuenta_activo_id', 'cuenta_depreciacion_acum_id', 'cuenta_gasto_depreciacion_id',
        'estado', 'asiento_compra_id', 'cxp_detalle_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_compra'              => 'date',
        'fecha_inicio_depreciacion' => 'date',
        'valor_compra'              => 'float',
        'valor_residual'            => 'float',
        'vida_util_meses'           => 'integer',
    ];

    // ───── Relations ─────────────────────────────────────────────────────────

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(AfiCategoria::class, 'categoria_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(AfiUbicacion::class, 'ubicacion_id');
    }

    public function cuentaActivo(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_activo_id');
    }

    public function cuentaDepreciacionAcum(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_depreciacion_acum_id');
    }

    public function cuentaGastoDepreciacion(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_gasto_depreciacion_id');
    }

    public function asientoCompra(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_compra_id');
    }

    public function cxpDetalle(): BelongsTo
    {
        return $this->belongsTo(CxpDocumentoDetalle::class, 'cxp_detalle_id');
    }

    public function depreciaciones(): HasMany
    {
        return $this->hasMany(AfiDepreciacion::class, 'activo_id')->orderBy('fecha');
    }

    public function baja(): HasOne
    {
        return $this->hasOne(AfiBaja::class, 'activo_id');
    }

    // ───── Computed ──────────────────────────────────────────────────────────

    public function depreciacionAcumulada(): float
    {
        return (float) $this->depreciaciones()->where('estado', 'POSTEADA')->sum('monto');
    }

    public function valorLibros(): float
    {
        return round($this->valor_compra - $this->depreciacionAcumulada(), 2);
    }

    /** Cuota mensual linea recta. */
    public function depreciacionMensual(): float
    {
        if ($this->vida_util_meses <= 0) {
            return 0;
        }
        return round(($this->valor_compra - $this->valor_residual) / $this->vida_util_meses, 2);
    }

    public function mesesDepreciados(): int
    {
        return $this->depreciaciones()->where('estado', 'POSTEADA')->count();
    }

    public function mesesRestantes(): int
    {
        return max(0, $this->vida_util_meses - $this->mesesDepreciados());
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function estaDepreciadoTotal(): bool
    {
        return round($this->valor_compra - $this->valor_residual - $this->depreciacionAcumulada(), 2) <= 0;
    }

    /** Cuenta de activo efectiva: la propia o la de la categoría. */
    public function cuentaActivoEfectivaId(): ?int
    {
        return $this->cuenta_activo_id ?? $this->categoria?->cuenta_activo_id;
    }

    public function cuentaDepAcumEfectivaId(): ?int
    {
        return $this->cuenta_depreciacion_acum_id ?? $this->categoria?->cuenta_depreciacion_acum_id;
    }

    public function cuentaGastoDepEfectivaId(): ?int
    {
        return $this->cuenta_gasto_depreciacion_id ?? $this->categoria?->cuenta_gasto_depreciacion_id;
    }

    // ───── Numbering ─────────────────────────────────────────────────────────

    public static function siguienteNumero(int $companiaId): string
    {
        $prefix = 'AF-';
        $max = null;

        if (\DB::getDriverName() === 'pgsql') {
            \DB::statement("SELECT pg_advisory_xact_lock(hashtext('afi_activos:{$companiaId}'))");
        }

        $last = static::where('compania_id', $companiaId)
            ->where('codigo', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('codigo');

        if ($last && preg_match('/' . preg_quote($prefix) . '(\d+)$/', $last, $m)) {
            $max = (int) $m[1];
        }

        return $prefix . str_pad((string) (($max ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }
}
