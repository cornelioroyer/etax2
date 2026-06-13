<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\Diario;
use App\Models\PeriodoContable;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Crea y postea asientos generados por los módulos (CxC, CxP, bancos...).
 * Llamar siempre dentro de una transacción: el módulo que lo invoca
 * debe poder revertir su documento si el asiento falla.
 */
class AsientoAutomatico
{
    /**
     * @param  array<int, array{cuenta_id:int, descripcion?:?string, contacto_id?:?int, debito:float, credito:float}>  $lineas
     */
    public function postear(
        int $companiaId,
        string $fecha,
        ?string $descripcion,
        ?string $referencia,
        array $lineas,
        string $origenModulo,
        ?string $origenTabla,
        ?int $origenId,
        User $usuario,
    ): Asiento {
        $debito = round(collect($lineas)->sum('debito'), 2);
        $credito = round(collect($lineas)->sum('credito'), 2);

        if (abs($debito - $credito) > 0.004 || $debito <= 0) {
            throw ValidationException::withMessages([
                'lineas' => sprintf('Asiento descuadrado: débito B/. %.2f ≠ crédito B/. %.2f.', $debito, $credito),
            ]);
        }

        $periodo = PeriodoContable::paraFecha($companiaId, \Carbon\Carbon::parse($fecha), $usuario->email);

        if (! $periodo->estaAbierto()) {
            throw ValidationException::withMessages([
                'fecha' => "El período {$periodo->anio}-".str_pad((string) $periodo->mes, 2, '0', STR_PAD_LEFT)." está {$periodo->estado}; no se puede registrar en esa fecha.",
            ]);
        }

        $asiento = Asiento::create([
            'compania_id' => $companiaId,
            'diario_id' => Diario::general($companiaId, $usuario->email)->id,
            'numero' => Asiento::siguienteNumero($companiaId),
            'fecha' => $fecha,
            'descripcion' => $descripcion,
            'referencia' => $referencia,
            'estado' => Asiento::ESTADO_BORRADOR,
            'origen_modulo' => $origenModulo,
            'origen_tabla' => $origenTabla,
            'origen_id' => $origenId,
            'total_debito' => $debito,
            'total_credito' => $credito,
            'usuario_id' => $usuario->id,
            'created_by' => $usuario->email,
        ]);

        foreach (array_values($lineas) as $i => $linea) {
            AsientoDetalle::create([
                'asiento_id' => $asiento->id,
                'linea' => $i + 1,
                'cuenta_id' => $linea['cuenta_id'],
                'contacto_id' => $linea['contacto_id'] ?? null,
                'descripcion' => $linea['descripcion'] ?? null,
                'debito' => round($linea['debito'], 2),
                'credito' => round($linea['credito'], 2),
                'tasa_cambio' => 1,
                'debito_local' => round($linea['debito'], 2),
                'credito_local' => round($linea['credito'], 2),
                'created_by' => $usuario->email,
            ]);
        }

        // El posteo dispara los triggers de control contable en PostgreSQL
        $asiento->update([
            'estado' => Asiento::ESTADO_POSTEADO,
            'periodo_id' => $periodo->id,
            'posteado_por' => $usuario->id,
            'fecha_posteo' => now(),
        ]);

        return $asiento;
    }

    /** Anula el asiento vinculado a un documento (si existe y está posteado). */
    public function anular(?Asiento $asiento, User $usuario): void
    {
        if ($asiento && $asiento->esPosteado()) {
            $asiento->update([
                'estado' => Asiento::ESTADO_ANULADO,
                'updated_by' => $usuario->email,
            ]);
        }
    }
}
