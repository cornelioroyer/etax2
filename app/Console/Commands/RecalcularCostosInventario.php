<?php

namespace App\Console\Commands;

use App\Models\InvAlmacen;
use App\Models\ItemProducto;
use App\Models\User;
use App\Services\RecalculadorCostosInventario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula el costo de las salidas de inventario por promedio ponderado en
 * orden de FECHA y postea el asiento de ajuste por la diferencia.
 *
 * Por seguridad corre en modo análisis (solo reporta). Use --aplicar para
 * escribir los cambios y postear el asiento de ajuste.
 *
 * Ejemplos:
 *   php artisan inventario:recalcular-costos --compania=8 --item=23
 *   php artisan inventario:recalcular-costos --compania=8 --item=23 --aplicar
 */
class RecalcularCostosInventario extends Command
{
    protected $signature = 'inventario:recalcular-costos
        {--compania= : ID de la compañía (obligatorio)}
        {--item= : Limitar a un ítem}
        {--almacen= : Limitar a un almacén}
        {--fecha= : Fecha del asiento de ajuste (por defecto hoy)}
        {--usuario= : Email del usuario que firma el asiento}
        {--aplicar : Aplicar los cambios (sin este flag solo analiza)}';

    protected $description = 'Recalcula costos de salida de inventario por promedio ponderado por fecha y postea el ajuste';

    public function handle(RecalculadorCostosInventario $recalc): int
    {
        $companiaId = (int) $this->option('compania');
        if ($companiaId <= 0) {
            $this->error('Indique --compania=<id>.');

            return self::FAILURE;
        }

        $itemId    = $this->option('item') ? (int) $this->option('item') : null;
        $almacenId = $this->option('almacen') ? (int) $this->option('almacen') : null;
        $fecha     = $this->option('fecha') ?: now()->toDateString();
        $aplicar   = (bool) $this->option('aplicar');

        $plan = $recalc->analizar($companiaId, $itemId, $almacenId);

        if ($plan['sinCambios']) {
            $this->info('Sin diferencias: los costos de salida ya están correctos. Nada que hacer.');

            return self::SUCCESS;
        }

        // Etiquetas para el reporte.
        $itemNombres = ItemProducto::whereIn('id', array_keys($plan['netoPorItem']))
            ->pluck('codigo', 'id');

        $this->line('');
        $this->line('<comment>Salidas a corregir:</comment>');
        $this->table(
            ['det', 'fecha', 'documento', 'ítem', 'cant', 'costo viejo', 'costo nuevo', 'Δ valor'],
            array_map(fn ($c) => [
                $c->det_id, $c->fecha, $c->doc, $itemNombres[$c->item_id] ?? $c->item_id,
                $c->cantidad, number_format($c->costo_viejo, 4), number_format($c->costo_nuevo, 4),
                number_format($c->delta, 2),
            ], $plan['cambios']),
        );

        $this->line('<comment>Existencias (estado final):</comment>');
        $this->table(
            ['ítem', 'almacén', 'cantidad', 'costo prom. actual', 'costo prom. nuevo'],
            array_map(fn ($e) => [
                $itemNombres[$e['item_id']] ?? $e['item_id'], $e['almacen_id'],
                number_format($e['cantidad'], 4),
                $e['costo_promedio_actual'] !== null ? number_format($e['costo_promedio_actual'], 4) : '—',
                number_format($e['costo_promedio'], 4),
            ], array_values($plan['existencias'])),
        );

        $this->line('<comment>Asiento de ajuste a postear:</comment>');
        if (empty($plan['ajusteLineas'])) {
            $this->line('  (neto cero — no requiere asiento)');
        } else {
            $this->table(
                ['cuenta_id', 'descripción', 'débito', 'crédito'],
                array_map(fn ($l) => [
                    $l['cuenta_id'], $l['descripcion'],
                    number_format((float) $l['debito'], 2), number_format((float) $l['credito'], 2),
                ], $plan['ajusteLineas']),
            );
        }

        if (! empty($plan['itemsSinCuenta'])) {
            $this->warn('Ítems sin cuenta de inventario/costo resolvible (se corrige el kárdex pero NO se postea su ajuste): '
                .implode(', ', array_map(fn ($id) => $itemNombres[$id] ?? $id, $plan['itemsSinCuenta'])));
        }

        if (! $aplicar) {
            $this->line('');
            $this->info('Modo análisis. Re-ejecute con --aplicar para escribir los cambios y postear el asiento.');

            return self::SUCCESS;
        }

        $usuario = $this->option('usuario')
            ? User::where('email', $this->option('usuario'))->firstOrFail()
            : User::orderBy('id')->firstOrFail();

        $asiento = DB::transaction(fn () => $recalc->aplicar($companiaId, $plan, $fecha, $usuario));

        $this->line('');
        if ($asiento) {
            $this->info("Aplicado. Asiento de ajuste #{$asiento->id} ({$asiento->numero}) posteado el {$fecha}.");
        } else {
            $this->info('Aplicado (costos y existencias corregidos; sin asiento por neto cero).');
        }

        return self::SUCCESS;
    }
}
