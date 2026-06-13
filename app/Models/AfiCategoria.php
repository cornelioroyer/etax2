<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AfiCategoria extends Model
{
    protected $table = 'afi_categorias';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'vida_util_meses_default',
        'cuenta_activo_id', 'cuenta_depreciacion_acum_id', 'cuenta_gasto_depreciacion_id',
        'created_by', 'updated_by',
    ];

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class);
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

    public function activos(): HasMany
    {
        return $this->hasMany(AfiActivo::class, 'categoria_id');
    }
}
