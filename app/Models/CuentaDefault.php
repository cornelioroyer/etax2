<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaDefault extends Model
{
    protected $table = 'core_cuentas_default';

    protected $fillable = [
        'compania_id',
        'clave',
        'cuenta_id',
        'descripcion',
        'created_by',
        'updated_by',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }

    /**
     * Id de la cuenta configurada para la clave (CXC, CXP, VENTAS,
     * ITBMS_POR_PAGAR, ...) en la compañía, o null si no está configurada.
     */
    public static function idPara(int $companiaId, string $clave): ?int
    {
        $id = self::where('compania_id', $companiaId)
            ->where('clave', $clave)
            ->value('cuenta_id');

        return $id !== null ? (int) $id : null;
    }
}
