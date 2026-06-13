<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactDireccion extends Model
{
    protected $table = 'contact_direcciones';

    protected $fillable = [
        'contacto_id', 'tipo', 'direccion', 'pais', 'provincia',
        'distrito', 'principal', 'created_by', 'updated_by',
    ];

    protected $casts = ['principal' => 'boolean'];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }
}
