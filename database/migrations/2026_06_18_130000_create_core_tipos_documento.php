<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro transversal de tipos de documento por auxiliar (submayor). Centraliza
 * en DATOS el comportamiento contable que hoy está disperso y duplicado en cada
 * modelo (in_array para el signo, match() para los prefijos). Una sola verdad
 * que TODOS los módulos consumen (ventas, compras, CxC, CxP, …).
 *
 * Llave (auxiliar, tipo_documento): el mismo tipo (FACTURA, NOTA_CREDITO…)
 * existe en varios submayores con el mismo signo pero distinto auxiliar —tal
 * como en el sistema viejo (Peachtree/Sage).
 *
 * Columnas de comportamiento:
 *   signo      +1 cargo / -1 abono  → efecto en el saldo del submayor
 *   naturaleza CARGO | ABONO
 *   cobrable   ¿genera partida abierta (saldo)?
 *   prefijo    correlativo propio (FC-, NC-, ND-, RC-, PG-); null = sin serie propia
 *   reversa    ¿es contra-documento? (la NC revierte la factura)
 *
 * Los montos se graban POSITIVOS; el signo se DERIVA de aquí. El mayor contable
 * conserva su débito/crédito propio: este maestro es para el SUBMAYOR.
 *
 * Aditiva e idempotente (upsert): segura en la BD compartida dev/prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_tipos_documento')) {
            Schema::create('core_tipos_documento', function (Blueprint $table) {
                $table->string('auxiliar', 20);          // CXC, CXP, …
                $table->string('tipo_documento', 30);     // FACTURA, NOTA_CREDITO, …
                $table->string('descripcion', 80);
                $table->smallInteger('signo');            // +1 cargo, -1 abono
                $table->string('naturaleza', 20);         // CARGO | ABONO
                $table->boolean('cobrable')->default(false);
                $table->string('prefijo', 10)->nullable();
                $table->boolean('reversa')->default(false);
                $table->timestamps();

                $table->primary(['auxiliar', 'tipo_documento']);
            });
        }

        $ahora = now();
        $filas = [];

        // [tipo, descripcion, signo, naturaleza, cobrable, prefijoCXC, prefijoCXP, reversa]
        $tipos = [
            ['FACTURA',      'Factura',          1, 'CARGO', true,  'FC-', null,  false],
            ['NOTA_DEBITO',  'Nota de débito',   1, 'CARGO', true,  'ND-', 'ND-', false],
            ['NOTA_CREDITO', 'Nota de crédito', -1, 'ABONO', false, 'NC-', 'NC-', true],
            ['PAGO',         'Cobro / Pago',    -1, 'ABONO', false, 'RC-', 'PG-', false],
        ];

        foreach (['CXC', 'CXP'] as $aux) {
            foreach ($tipos as [$tipo, $desc, $signo, $nat, $cobrable, $pfxCxc, $pfxCxp, $reversa]) {
                // La factura de proveedor (CXP) usa el número del proveedor: sin serie propia.
                $descAux = $tipo === 'PAGO'
                    ? ($aux === 'CXC' ? 'Recibo de cobro' : 'Comprobante de pago')
                    : $desc;

                $filas[] = [
                    'auxiliar'       => $aux,
                    'tipo_documento' => $tipo,
                    'descripcion'    => $descAux,
                    'signo'          => $signo,
                    'naturaleza'     => $nat,
                    'cobrable'       => $cobrable,
                    'prefijo'        => $aux === 'CXC' ? $pfxCxc : $pfxCxp,
                    'reversa'        => $reversa,
                    'created_at'     => $ahora,
                    'updated_at'     => $ahora,
                ];
            }
        }

        // Reembolso (DGI tipo 09): solo aplica al submayor de ventas (CXC).
        $filas[] = [
            'auxiliar'       => 'CXC',
            'tipo_documento' => 'REEMBOLSO',
            'descripcion'    => 'Reembolso',
            'signo'          => 1,
            'naturaleza'     => 'CARGO',
            'cobrable'       => true,
            'prefijo'        => 'RE-',
            'reversa'        => false,
            'created_at'     => $ahora,
            'updated_at'     => $ahora,
        ];

        DB::table('core_tipos_documento')->upsert(
            $filas,
            ['auxiliar', 'tipo_documento'],
            ['descripcion', 'signo', 'naturaleza', 'cobrable', 'prefijo', 'reversa', 'updated_at']
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('core_tipos_documento');
    }
};
