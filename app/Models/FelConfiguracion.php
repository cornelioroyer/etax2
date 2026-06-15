<?php

namespace App\Models;

use App\Services\FelConfiguracionDefault;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * Con las credenciales demo compartidas, todas las compañías emiten bajo
     * la misma cuenta PAC de HKA, así que el consecutivo debe ser único entre
     * TODAS ellas (mismo ambiente/sucursal/punto) o la DGI rechaza por
     * "Documento duplicado". Con tokens propios cada compañía lleva su propio
     * espacio de folios. PostgreSQL no permite FOR UPDATE con agregados, por lo
     * que serializamos con un advisory lock de transacción.
     *
     * Llamar dentro de una transacción.
     */
    public function siguienteNumeroFiscal(): int
    {
        $cfg = self::lockForUpdate()->find($this->id);

        if (app(FelConfiguracionDefault::class)->esDemo($cfg)) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['fel-demo-'.$cfg->codigo_sucursal.'-'.$cfg->punto_facturacion]);
            }

            $cfg->correlativo = (int) self::where('ambiente', $cfg->ambiente)
                ->where('codigo_sucursal', $cfg->codigo_sucursal)
                ->where('punto_facturacion', $cfg->punto_facturacion)
                ->max('correlativo') + 1;
        } else {
            $cfg->correlativo += 1;
        }

        $cfg->save();

        return $cfg->correlativo;
    }
}
