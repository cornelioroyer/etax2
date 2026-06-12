<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zona extends Model
{
    protected $table = 'core_zonas';

    protected $fillable = [
        'description',
        'created_by',
        'updated_by',
    ];

    public function companias(): HasMany
    {
        return $this->hasMany(Compania::class, 'zonas_id');
    }
}
