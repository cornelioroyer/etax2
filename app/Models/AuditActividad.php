<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bitácora de actividad de usuarios (tabla audit_actividad).
 *
 * No se observa a sí misma (ver AppServiceProvider) ni dispara eventos al
 * escribir: el registro se hace con DB::table()->insert() en {@see registrar()}
 * para evitar recursión infinita con el AuditObserver.
 */
class AuditActividad extends Model
{
    protected $table = 'audit_actividad';

    public const UPDATED_AT = null; // solo created_at

    protected $fillable = [
        'compania_id', 'usuario_id', 'usuario_nombre', 'evento',
        'entidad', 'entidad_tabla', 'entidad_id', 'descripcion',
        'valores_anteriores', 'valores_nuevos', 'url', 'metodo', 'ip', 'user_agent',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'created_at' => 'datetime',
    ];

    /** Claves que nunca se guardan en el diff (secretos). */
    public const OCULTAR = [
        'password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes', 'api_token',
    ];

    /** Etiquetas legibles por evento, para la UI. */
    public const ETIQUETAS = [
        'created' => 'Creó',
        'updated' => 'Editó',
        'deleted' => 'Eliminó',
        'login' => 'Inició sesión',
        'logout' => 'Cerró sesión',
        'login_fallido' => 'Login fallido',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function getEventoLabelAttribute(): string
    {
        return self::ETIQUETAS[$this->evento] ?? $this->evento;
    }

    /**
     * Inserta un registro de bitácora resolviendo el contexto actual
     * (usuario autenticado, compañía activa y datos de la petición HTTP).
     * Escribe directo a la tabla para no disparar el observer.
     *
     * @param  array<string,mixed>  $datos
     */
    public static function registrar(array $datos): void
    {
        $usuario = $datos['usuario'] ?? Auth::user();
        $request = app()->bound('request') ? app('request') : null;

        // En consola (seeders, migraciones, tinker) sin usuario no registramos
        // operaciones de modelo: la bitácora es de actividad de usuarios.
        if (! $usuario && empty($datos['usuario_nombre'])) {
            return;
        }

        DB::table('audit_actividad')->insert([
            'compania_id' => $datos['compania_id'] ?? session('compania_activa_id'),
            'usuario_id' => $datos['usuario_id'] ?? $usuario?->id,
            'usuario_nombre' => $datos['usuario_nombre'] ?? ($usuario?->name ?: $usuario?->email),
            'evento' => $datos['evento'],
            'entidad' => $datos['entidad'] ?? null,
            'entidad_tabla' => $datos['entidad_tabla'] ?? null,
            'entidad_id' => $datos['entidad_id'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'valores_anteriores' => isset($datos['valores_anteriores'])
                ? json_encode($datos['valores_anteriores'], JSON_UNESCAPED_UNICODE) : null,
            'valores_nuevos' => isset($datos['valores_nuevos'])
                ? json_encode($datos['valores_nuevos'], JSON_UNESCAPED_UNICODE) : null,
            'url' => $datos['url'] ?? ($request ? substr($request->method().' '.$request->path(), 0, 500) : null),
            'metodo' => $datos['metodo'] ?? $request?->method(),
            'ip' => $datos['ip'] ?? $request?->ip(),
            'user_agent' => $datos['user_agent'] ?? ($request ? substr((string) $request->userAgent(), 0, 1000) : null),
            'created_at' => now(),
        ]);
    }

    /**
     * Quita las claves secretas de un arreglo de atributos.
     *
     * @param  array<string,mixed>  $valores
     * @return array<string,mixed>
     */
    public static function depurar(array $valores): array
    {
        foreach (self::OCULTAR as $clave) {
            if (array_key_exists($clave, $valores)) {
                $valores[$clave] = '••••';
            }
        }

        return $valores;
    }
}
