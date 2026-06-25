<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ItemProducto extends Model
{
    protected $table = 'item_productos_servicios';

    public const TIPO_PRODUCTO  = 'PRODUCTO';
    public const TIPO_SERVICIO  = 'SERVICIO';

    /**
     * Genera el siguiente código correlativo para la compañía y tipo dados,
     * siguiendo la convención existente: PROD-001 / SERV-001.
     *
     * Debe invocarse DENTRO de una transacción: usa un advisory lock de
     * transacción para serializar la numeración entre solicitudes
     * concurrentes (se libera al cerrar la transacción). La restricción
     * UNIQUE (compania_id, codigo) es la red de seguridad final.
     */
    public static function siguienteCodigo(int $companiaId, string $tipo): string
    {
        $prefijo = strtoupper($tipo) === self::TIPO_SERVICIO ? 'SERV-' : 'PROD-';

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$companiaId.'-item-'.$prefijo]);
        }

        $len  = strlen($prefijo);
        $base = static::where('compania_id', $companiaId)
            ->where('codigo', 'like', $prefijo.'%');

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Solo correlativos con formato estricto PREFIJO+dígitos; toma el
            // máximo NUMÉRICO (no lexical) para que un código manual con otro
            // formato no envenene la secuencia ni cause colisiones.
            $maxNum = (clone $base)
                ->where('codigo', '~', '^'.$prefijo.'[0-9]+$')
                ->selectRaw('MAX(CAST(SUBSTRING(codigo FROM '.($len + 1).') AS INTEGER)) AS n')
                ->value('n');
            $siguiente = ((int) $maxNum) + 1;
        } else {
            $ultimo    = $base->max('codigo');
            $siguiente = $ultimo ? ((int) substr($ultimo, $len)) + 1 : 1;
        }

        return $prefijo.str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'descripcion', 'tipo',
        'categoria_id', 'unidad_medida_id', 'precio_venta', 'costo',
        'cuenta_ingreso_id', 'cuenta_gasto_id', 'cuenta_inventario_id', 'cuenta_costo_venta_id',
        'impuesto_id', 'extra', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:4',
            'costo'        => 'decimal:4',
            'activo'       => 'boolean',
            'extra'        => 'array',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ItemCategoria::class, 'categoria_id');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(ItemUnidadMedida::class, 'unidad_medida_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(TaxImpuesto::class, 'impuesto_id');
    }

    public function cuentaIngreso(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_ingreso_id');
    }

    public function cuentaGasto(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_gasto_id');
    }

    public function cuentaInventario(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_inventario_id');
    }

    public function cuentaCostoVenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_costo_venta_id');
    }
}
