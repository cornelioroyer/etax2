<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Ítem del menú lateral (tabla core_menu_items). Árbol auto-referenciado por
 * parent_id. El render lo arma App\Services\MenuBuilder.
 */
class MenuItem extends Model
{
    protected $table = 'core_menu_items';

    /** Clave de caché de las filas activas (ver MenuBuilder). */
    public const CACHE_KEY = 'core_menu_items_v1';

    protected $fillable = [
        'parent_id',
        'clave',
        'etiqueta',
        'icono',
        'ruta_nombre',
        'ruta_params',
        'dispatch_evento',
        'ruta_activa_patron',
        'activa_query_key',
        'activa_query_val',
        'permiso',
        'solo_admin',
        'modulo',
        'orden',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ruta_params' => 'array',
        'solo_admin' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    protected static function booted(): void
    {
        // Cualquier alta/cambio/baja invalida la caché del menú.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden');
    }
}
