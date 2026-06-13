<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPersonaContacto extends Model
{
    protected $table = 'contact_personas_contacto';

    protected $fillable = [
        'contacto_id', 'nombre', 'cargo', 'email', 'telefono',
        'principal', 'created_by', 'updated_by',
    ];

    protected $casts = ['principal' => 'boolean'];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }
}
