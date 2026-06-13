<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Inserta las 4 tasas ITBMS globales (compania_id null) si no existen.
return new class extends Migration
{
    public function up(): void
    {
        $tasas = [
            ['ITBMS_0', 'Exento (0%)', 0],
            ['ITBMS_7', 'ITBMS 7%', 7],
            ['ITBMS_10', 'ITBMS 10%', 10],
            ['ITBMS_15', 'ITBMS 15%', 15],
        ];

        foreach ($tasas as [$codigo, $nombre, $porcentaje]) {
            $existe = DB::table('tax_impuestos')
                ->whereNull('compania_id')
                ->where('codigo', $codigo)
                ->exists();

            if (! $existe) {
                DB::table('tax_impuestos')->insert([
                    'compania_id' => null,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'tipo' => 'VENTAS',
                    'porcentaje' => $porcentaje,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tax_impuestos')
            ->whereNull('compania_id')
            ->whereIn('codigo', ['ITBMS_0', 'ITBMS_7', 'ITBMS_10', 'ITBMS_15'])
            ->delete();
    }
};
