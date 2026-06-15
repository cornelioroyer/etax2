<?php

namespace App\Services;

use App\Models\FelConfiguracion;

/**
 * Credenciales FEL por defecto (The Factory HKA, ambiente de PRUEBAS) que
 * heredan las compañías nuevas y las existentes sin configuración propia.
 *
 * Son las credenciales demo de WIN SOFT CORP (RUC 62758-19-353075); sirven
 * de punto de partida hasta que cada compañía cargue las suyas. OJO: al
 * compartir la misma cuenta PAC, los números de documento pueden colisionar
 * entre compañías en el ambiente demo — en PRODUCCIÓN cada compañía debe
 * tener su propio RUC y tokens.
 */
class FelConfiguracionDefault
{
    /** Valores por defecto que se copian a fel_configuracion. */
    public const VALORES = [
        'ambiente' => 'PRUEBAS',
        'proveedor' => 'HKA',
        'token_empresa' => 'winsoffelpademo',
        'token_password' => 'winsoffelpademo',
        'codigo_sucursal' => '3075',
        'punto_facturacion' => '075',
        'correlativo' => 0,
        'activa' => true,
    ];

    /**
     * Crea la configuración FEL por defecto para una compañía si todavía no
     * tiene una. Idempotente: no pisa la config existente.
     */
    public function aplicar(int $companiaId, ?string $usuario = null): ?FelConfiguracion
    {
        if (FelConfiguracion::where('compania_id', $companiaId)->exists()) {
            return null;
        }

        return FelConfiguracion::create(self::VALORES + [
            'compania_id' => $companiaId,
            'created_by' => $usuario,
        ]);
    }
}
