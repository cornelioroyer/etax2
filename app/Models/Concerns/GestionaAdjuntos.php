<?php

namespace App\Models\Concerns;

use App\Models\Adjunto;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Da a un modelo de documento (CxP, caja, etc.) acceso a sus adjuntos centrales
 * en `core_adjuntos`, ligados por tabla_origen = nombre de su tabla y
 * registro_id = su id. El módulo lo expone el propio modelo vía moduloAdjuntos().
 */
trait GestionaAdjuntos
{
    public function adjuntos(): HasMany
    {
        // El filtro por tabla_origen es constante por modelo (getTable()), así que
        // es seguro tanto para acceso directo como para eager loading.
        return $this->hasMany(Adjunto::class, 'registro_id')
            ->where('core_adjuntos.tabla_origen', $this->getTable())
            ->latest('core_adjuntos.created_at');
    }

    /**
     * Código de módulo que se guarda en core_adjuntos.modulo. Por defecto deriva
     * del prefijo de la tabla (cxp_documentos -> CXP); un modelo puede
     * sobreescribirlo.
     */
    public function moduloAdjuntos(): string
    {
        $prefijo = strtoupper(strtok($this->getTable(), '_'));

        return $prefijo ?: 'GENERAL';
    }
}
