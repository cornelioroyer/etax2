<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de DEMO para el módulo de Inventario (datos de desarrollo).
 *
 * Siembra movimientos de ejemplo (inv_movimientos + inv_movimientos_detalle) y
 * recalcula los saldos de inv_existencias a partir de ellos. El Kardex NO se
 * siembra: es un reporte derivado en vivo de inv_movimientos (ver InvKardexController).
 *
 * Usa IDs FIJOS de demo que deben existir previamente: compañía 1, almacenes 3 y 4,
 * ítems 15..19. Por eso NO está registrado en DatabaseSeeder (correrlo sobre una BD
 * sin esos registros no actualizaría existencias). Ejecutar explícitamente:
 *
 *     php artisan db:seed --class=Database\\Seeders\\InventarioDemoSeeder
 */
class InventarioDemoSeeder extends Seeder
{
    /** Compañía de demo sobre la que se siembran los movimientos. */
    private int $companiaId = 1;

    /** Usuario (created_by) registrado en los datos de demo. */
    private string $usuario = 'cornelioroyer@winsof.com';

    public function run(): void
    {
        DB::transaction(function () {
            $now = now();
            $cid = $this->companiaId;
            $uid = $this->usuario;

            $alm3 = 3;
            $alm4 = 4;
            $i15 = 15;
            $i16 = 16;
            $i17 = 17;
            $i18 = 18;
            $i19 = 19;

            $this->command->info('=== Movimientos junio 2026 ===');

            $movEntJun = DB::table('inv_movimientos')->insertGetId([
                'compania_id' => $cid, 'almacen_id' => $alm3, 'fecha' => '2026-06-02',
                'tipo_movimiento' => 'ENTRADA', 'documento_origen' => 'COMPRA', 'documento_id' => null,
                'descripcion' => 'Reposicion de stock junio', 'estado' => 'APLICADO',
                'created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => null,
            ]);
            DB::table('inv_movimientos_detalle')->insert([
                ['movimiento_id' => $movEntJun, 'item_id' => $i15, 'cantidad' => 5, 'costo_unitario' => 625.00, 'total' => 3125.00, 'created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => null],
                ['movimiento_id' => $movEntJun, 'item_id' => $i16, 'cantidad' => 10, 'costo_unitario' => 198.00, 'total' => 1980.00, 'created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => null],
                ['movimiento_id' => $movEntJun, 'item_id' => $i19, 'cantidad' => 20, 'costo_unitario' => 11.50, 'total' => 230.00, 'created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => null],
            ]);
            $this->command->info("  mov ENTRADA junio id={$movEntJun}");

            $movSalJun = DB::table('inv_movimientos')->insertGetId([
                'compania_id' => $cid, 'almacen_id' => $alm3, 'fecha' => '2026-06-10',
                'tipo_movimiento' => 'SALIDA', 'documento_origen' => 'VENTA', 'documento_id' => 2,
                'descripcion' => 'Salida por factura FC-000005', 'estado' => 'APLICADO',
                'created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => null,
            ]);
            DB::table('inv_movimientos_detalle')->insert([
                ['movimiento_id' => $movSalJun, 'item_id' => $i16, 'cantidad' => 2, 'costo_unitario' => 195.00, 'total' => 390.00, 'created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => null],
                ['movimiento_id' => $movSalJun, 'item_id' => $i18, 'cantidad' => 5, 'costo_unitario' => 5.20, 'total' => 26.00, 'created_at' => $now, 'created_by' => $uid, 'updated_at' => $now, 'updated_by' => null],
            ]);
            $this->command->info("  mov SALIDA junio id={$movSalJun}");

            // El kardex ya no es una tabla propia (inv_kardex fue eliminada): se deriva en
            // vivo de inv_movimientos en InvKardexController. Aquí solo recorremos los
            // movimientos sembrados para recalcular los saldos de inv_existencias.
            $this->command->info('=== Calculando saldos para inv_existencias ===');

            $lineas = [
                ['2026-01-15', $alm3, $i15, 'ENTRADA', 10, 620.00, 6200.00, 'COMPRA', null, 5],
                ['2026-01-15', $alm3, $i16, 'ENTRADA', 15, 195.00, 2925.00, 'COMPRA', null, 5],
                ['2026-01-15', $alm3, $i17, 'ENTRADA', 20, 42.00, 840.00, 'COMPRA', null, 5],
                ['2026-01-15', $alm3, $i18, 'ENTRADA', 50, 5.20, 260.00, 'COMPRA', null, 5],
                ['2026-01-15', $alm3, $i19, 'ENTRADA', 30, 11.50, 345.00, 'COMPRA', null, 5],
                ['2026-02-10', $alm3, $i15, 'SALIDA', 2, 620.00, 1240.00, 'VENTA', 1, 6],
                ['2026-02-10', $alm3, $i16, 'SALIDA', 3, 195.00, 585.00, 'VENTA', 1, 6],
                ['2026-03-01', $alm3, $i15, 'SALIDA', 2, 620.00, 1240.00, 'TRANSFERENCIA', 2, 7],
                ['2026-03-01', $alm3, $i17, 'SALIDA', 5, 42.00, 210.00, 'TRANSFERENCIA', 2, 7],
                ['2026-03-01', $alm4, $i15, 'ENTRADA', 2, 620.00, 1240.00, 'TRANSFERENCIA', 2, 8],
                ['2026-03-01', $alm4, $i17, 'ENTRADA', 5, 42.00, 210.00, 'TRANSFERENCIA', 2, 8],
                ['2026-06-02', $alm3, $i15, 'ENTRADA', 5, 625.00, 3125.00, 'COMPRA', null, $movEntJun],
                ['2026-06-02', $alm3, $i16, 'ENTRADA', 10, 198.00, 1980.00, 'COMPRA', null, $movEntJun],
                ['2026-06-02', $alm3, $i19, 'ENTRADA', 20, 11.50, 230.00, 'COMPRA', null, $movEntJun],
                ['2026-06-10', $alm3, $i16, 'SALIDA', 2, 195.00, 390.00, 'VENTA', 2, $movSalJun],
                ['2026-06-10', $alm3, $i18, 'SALIDA', 5, 5.20, 26.00, 'VENTA', 2, $movSalJun],
            ];

            $saldos = [];
            foreach ($lineas as $l) {
                [$fecha, $alm, $item, $tipo, $qty, $costoU, $total, $docOrigen, $docId, $movId] = $l;
                $key = $alm.'_'.$item;
                if (! isset($saldos[$key])) {
                    $saldos[$key] = ['qty' => 0, 'costo' => 0];
                }

                if ($tipo === 'ENTRADA') {
                    $saldos[$key]['qty'] += $qty;
                    $saldos[$key]['costo'] += $total;
                } else {
                    $saldos[$key]['qty'] -= $qty;
                    $saldos[$key]['costo'] -= $total;
                }
            }

            $this->command->info('=== Actualizando inv_existencias ===');
            foreach ($saldos as $key => $s) {
                [$alm, $item] = explode('_', $key);
                DB::table('inv_existencias')
                    ->where('compania_id', $cid)->where('almacen_id', $alm)->where('item_id', $item)
                    ->update([
                        'cantidad' => $s['qty'],
                        'costo_promedio' => $s['qty'] > 0 ? round($s['costo'] / $s['qty'], 4) : 0,
                    ]);
                $this->command->info("  alm={$alm} item={$item} qty={$s['qty']}");
            }

            $this->command->info('OK');
        });
    }
}
