<?php

namespace App\Models;

use App\Providers\AppServiceProvider;
use App\Services\FelConfiguracionDefault;
use Illuminate\Database\Eloquent\Model;

class FelConfiguracion extends Model
{
    protected $table = 'fel_configuracion';

    protected $fillable = [
        'compania_id',
        'ambiente',
        'proveedor',
        'token_empresa',
        'token_password',
        'punto_facturacion',
        'codigo_sucursal',
        'correlativo',
        'activa',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'token_empresa' => 'encrypted',
            'token_password' => 'encrypted',
            'activa' => 'boolean',
        ];
    }

    /**
     * Reserva y devuelve el siguiente número de documento fiscal.
     *
     * Con las credenciales demo compartidas, todas las compañías emiten bajo la
     * MISMA cuenta PAC de HKA (incluso entre los entornos dev y prod, que usan
     * los mismos tokens), así que el consecutivo debe ser único entre todas o la
     * DGI rechaza con "Documento duplicado". Para que sea monotónico y a prueba
     * de borrados (eliminar una compañía no debe regresar el contador), el
     * consecutivo demo lo lleva una sola fila ancla: la config de la compañía
     * del sistema. Con tokens propios, cada compañía mantiene su propio espacio
     * de folios. El bloqueo de fila (FOR UPDATE) serializa la numeración.
     *
     * Llamar dentro de una transacción.
     */
    public function siguienteNumeroFiscal(): int
    {
        if (app(FelConfiguracionDefault::class)->esDemo($this)) {
            $cfg = self::lockForUpdate()->firstWhere('compania_id', AppServiceProvider::COMPANIA_SISTEMA)
                ?? self::lockForUpdate()->find($this->id);
        } else {
            $cfg = self::lockForUpdate()->find($this->id);
        }

        $cfg->correlativo += 1;
        $cfg->save();

        return $cfg->correlativo;
    }
}
