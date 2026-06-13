<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvTransferencia extends Model
{
    protected $table = 'inv_transferencias';

    protected $fillable = [
        'compania_id', 'almacen_origen_id', 'almacen_destino_id',
        'fecha', 'estado', 'created_by', 'updated_by',
    ];

    protected $casts = ['fecha' => 'date'];

    const ESTADO_APLICADA = 'APLICADA';
    const ESTADO_ANULADA  = 'ANULADA';

    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_origen_id');
    }

    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_destino_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InvMovimiento::class, 'documento_id')
            ->where('documento_origen', 'TRANSFERENCIA');
    }
}
