<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Compania extends Model
{
    protected $table = 'core_companias';

    /** Clave de caché del conjunto de compañías marcadas como solo lectura. */
    public const CACHE_SOLO_LECTURA = 'core_companias_solo_lectura';

    protected $fillable = [
        'nombre',
        'razon_social',
        'ruc',
        'dv',
        'firma_cartas',
        'direccion',
        'telefono',
        'telefono2',
        'email',
        'cargo',
        'mensaje',
        'correlativo_ss',
        'fecha_de_apertura',
        'fecha_de_expiracion',
        'activa',
        'solo_lectura',
        'no_patronal',
        'act_economica',
        'cedula',
        'licencia',
        'repre_legal',
        'zonas_id',
        'plan_id',
        'tipo_de_entidad',
        'constitucion',
        'logo_url',
        'sello_url',
        'token',
        'cliente_id',
        'nit',
        'cedula_repre_legal',
        'municipio',
        'clave_municipio',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_de_apertura' => 'date',
            'fecha_de_expiracion' => 'date',
            'activa' => 'boolean',
            'solo_lectura' => 'boolean',
        ];
    }

    /**
     * Al guardar o borrar una compañía se invalida la caché del conjunto de
     * compañías en solo lectura (la consulta el Gate en cada petición).
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_SOLO_LECTURA));
        static::deleted(fn () => Cache::forget(self::CACHE_SOLO_LECTURA));
    }

    /**
     * IDs de las compañías marcadas como solo lectura. Cacheado: el Gate lo
     * consulta en cada can() y normalmente el conjunto es vacío o muy pequeño.
     * Si la columna aún no existe (antes de migrar) devuelve vacío.
     *
     * @return array<int,int>
     */
    public static function idsSoloLectura(): array
    {
        return Cache::remember(self::CACHE_SOLO_LECTURA, 3600, function () {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('core_companias', 'solo_lectura')) {
                return [];
            }

            return self::query()->where('solo_lectura', true)->pluck('id')
                ->map(fn ($id) => (int) $id)->all();
        });
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zonas_id');
    }
}
