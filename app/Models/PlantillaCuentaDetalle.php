<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Una cuenta dentro de una plantilla de plan de cuentas
 * (core_plantillas_cuentas_detalle). La jerarquía se referencia por `codigo_padre`
 * (código de la cuenta padre dentro de la MISMA plantilla), no por id.
 */
class PlantillaCuentaDetalle extends Model
{
    protected $table = 'core_plantillas_cuentas_detalle';

    protected $fillable = [
        'plantilla_id',
        'codigo',
        'nombre',
        'codigo_padre',
        'nivel',
        'tipo_cuenta_codigo',
        'naturaleza',
        'permite_movimiento',
        'conciliable',
        'clave_default',
        'renglon_isr',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'permite_movimiento' => 'boolean',
            'conciliable' => 'boolean',
            'nivel' => 'integer',
            'renglon_isr' => 'integer',
        ];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaCuenta::class, 'plantilla_id');
    }

    /**
     * Claves de cuenta por defecto conocidas (las que mapean los módulos), para
     * sugerirlas como datalist al asociar una cuenta de la plantilla a una clave.
     * Se derivan de lo ya usado en compañías y en plantillas, así no hay que
     * mantener una lista a mano.
     *
     * @return array<int, string>
     */
    public static function clavesConocidas(): array
    {
        return DB::table('core_cuentas_default')
            ->select('clave')
            ->distinct()
            ->union(
                DB::table('core_plantillas_cuentas_detalle')
                    ->select('clave_default as clave')
                    ->whereNotNull('clave_default')
                    ->distinct()
            )
            ->orderBy('clave')
            ->pluck('clave')
            ->all();
    }
}
