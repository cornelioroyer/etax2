<?php

namespace App\Observers;

use App\Models\AuditActividad;
use Illuminate\Database\Eloquent\Model;

/**
 * Observa todos los modelos del dominio (registrado en bucle en
 * AppServiceProvider) y escribe en la bitácora cada creación, edición y
 * borrado que realiza un usuario, con los valores antes/después.
 */
class AuditObserver
{
    /** Solo timestamps cambiados: no aporta nada al diff, se omite. */
    private const TIMESTAMPS = ['created_at', 'updated_at'];

    public function created(Model $model): void
    {
        AuditActividad::registrar([
            'evento' => 'created',
            'descripcion' => $this->describir($model),
            'valores_nuevos' => AuditActividad::depurar($model->attributesToArray()),
        ] + $this->contexto($model));
    }

    public function updated(Model $model): void
    {
        $cambios = $model->getChanges();
        $antes = array_intersect_key($model->getOriginal(), $cambios);

        // Quita timestamps puros: si no queda nada sustancial, no registramos.
        foreach (self::TIMESTAMPS as $ts) {
            unset($cambios[$ts], $antes[$ts]);
        }

        if (empty($cambios)) {
            return;
        }

        AuditActividad::registrar([
            'evento' => 'updated',
            'descripcion' => $this->describir($model),
            'valores_anteriores' => AuditActividad::depurar($antes),
            'valores_nuevos' => AuditActividad::depurar($cambios),
        ] + $this->contexto($model));
    }

    public function deleted(Model $model): void
    {
        AuditActividad::registrar([
            'evento' => 'deleted',
            'descripcion' => $this->describir($model),
            'valores_anteriores' => AuditActividad::depurar($model->attributesToArray()),
        ] + $this->contexto($model));
    }

    /**
     * Datos comunes de identificación de la entidad afectada.
     *
     * @return array<string,mixed>
     */
    private function contexto(Model $model): array
    {
        $key = $model->getKey();

        return [
            'entidad' => class_basename($model),
            'entidad_tabla' => $model->getTable(),
            'entidad_id' => is_numeric($key) ? (int) $key : null,
            'compania_id' => $model->getAttribute('compania_id') ?? session('compania_activa_id'),
        ];
    }

    /**
     * Etiqueta corta de la fila afectada para listarla sin abrir el detalle.
     * Usa el primer campo "humano" disponible (nombre, código, número…).
     */
    private function describir(Model $model): string
    {
        foreach (['nombre', 'name', 'codigo', 'numero', 'descripcion', 'titulo', 'email'] as $campo) {
            $valor = $model->getAttribute($campo);
            if (! empty($valor) && is_scalar($valor)) {
                return class_basename($model).' · '.mb_substr((string) $valor, 0, 80);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
