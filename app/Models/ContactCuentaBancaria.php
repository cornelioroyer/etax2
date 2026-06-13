<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactCuentaBancaria extends Model
{
    protected $table = 'contact_cuentas_bancarias';

    protected $fillable = [
        'contacto_id', 'banco', 'numero_cuenta', 'tipo_cuenta',
        'moneda_id', 'principal', 'created_by', 'updated_by',
    ];

    protected $casts = ['principal' => 'boolean'];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }
}
