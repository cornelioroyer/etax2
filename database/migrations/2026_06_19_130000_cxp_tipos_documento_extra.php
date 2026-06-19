<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía el maestro core_tipos_documento para el submayor de compras (CXP) con
 * cuatro tipos de documento de la operación de compras de Panamá:
 *
 *   REEMBOLSO   +1 CARGO  cobrable   RE-   Gasto reembolsable facturado por el
 *                                           proveedor (igual que el de ventas).
 *   IMPORTACION +1 CARGO  cobrable   IM-   Factura/liquidación de importación;
 *                                           genera saldo pagable como una factura.
 *   ANTICIPO    -1 ABONO  no cobrable AN-  Pago anticipado al proveedor; crea un
 *                                           crédito a favor que se aplica a
 *                                           facturas futuras (como el PAGO/NC).
 *   RETENCION   -1 ABONO  no cobrable RT-  Retención de ITBMS/ISR al proveedor;
 *                                           reduce lo pagable (se traslada a la DGI).
 *
 * Solo agrega filas PADRE al maestro (lado referenciado de la FK
 * cxp_documentos_tipodoc_fk, ya activa): no puede violar integridad referencial.
 * Aditiva e idempotente (upsert): segura en la BD compartida dev/prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_tipos_documento')) {
            return;
        }

        $ahora = now();

        // [tipo, descripcion, signo, naturaleza, cobrable, prefijo, reversa]
        $tipos = [
            ['REEMBOLSO',   'Reembolso de compra',        1, 'CARGO', true,  'RE-', false],
            ['IMPORTACION', 'Factura de importación',     1, 'CARGO', true,  'IM-', false],
            ['ANTICIPO',    'Anticipo a proveedor',      -1, 'ABONO', false, 'AN-', false],
            ['RETENCION',   'Retención a proveedor',     -1, 'ABONO', false, 'RT-', false],
        ];

        $filas = [];
        foreach ($tipos as [$tipo, $desc, $signo, $nat, $cobrable, $prefijo, $reversa]) {
            $filas[] = [
                'auxiliar'       => 'CXP',
                'tipo_documento' => $tipo,
                'descripcion'    => $desc,
                'signo'          => $signo,
                'naturaleza'     => $nat,
                'cobrable'       => $cobrable,
                'prefijo'        => $prefijo,
                'reversa'        => $reversa,
                'created_at'     => $ahora,
                'updated_at'     => $ahora,
            ];
        }

        DB::table('core_tipos_documento')->upsert(
            $filas,
            ['auxiliar', 'tipo_documento'],
            ['descripcion', 'signo', 'naturaleza', 'cobrable', 'prefijo', 'reversa', 'updated_at']
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('core_tipos_documento')) {
            return;
        }

        // Solo elimina los tipos que aún no estén en uso por ningún documento,
        // para no romper la FK ni dejar documentos huérfanos de su tipo.
        $enUso = Schema::hasTable('cxp_documentos')
            ? DB::table('cxp_documentos')->distinct()->pluck('tipo_documento')->all()
            : [];

        foreach (['REEMBOLSO', 'IMPORTACION', 'ANTICIPO', 'RETENCION'] as $tipo) {
            if (in_array($tipo, $enUso, true)) {
                continue;
            }
            DB::table('core_tipos_documento')
                ->where('auxiliar', 'CXP')
                ->where('tipo_documento', $tipo)
                ->delete();
        }
    }
};
