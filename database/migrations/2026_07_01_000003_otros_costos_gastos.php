<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo DGI "Otros costos y gastos" (anexo 94 de la declaración de renta).
        // Fuente: dba.v_otros_costos_gastos de planilla; se conservan los MISMOS ids.
        if (! Schema::hasTable('core_otros_costos_gastos')) {
            Schema::create('core_otros_costos_gastos', function (Blueprint $table) {
                $table->bigInteger('id')->primary();
                $table->string('descripcion', 250);
                $table->boolean('activo')->default(true);
            });
        }

        foreach (self::CATALOGO as [$id, $descripcion]) {
            DB::table('core_otros_costos_gastos')->insertOrIgnore([
                'id' => $id,
                'descripcion' => $descripcion,
            ]);
        }

        if (! Schema::hasColumn('contact_contactos', 'otros_costos_gastos_id')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->bigInteger('otros_costos_gastos_id')->nullable()->after('concepto');
                $table->foreign('otros_costos_gastos_id')->references('id')->on('core_otros_costos_gastos');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_contactos', 'otros_costos_gastos_id')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->dropForeign(['otros_costos_gastos_id']);
                $table->dropColumn('otros_costos_gastos_id');
            });
        }

        Schema::dropIfExists('core_otros_costos_gastos');
    }

    private const CATALOGO = [
        [1, 'COBRANZAS (1)'],
        [3, 'COSTO DE MANEJO (3)'],
        [12, 'SERVICIOS OCASIONALES (12)'],
        [15, 'ARRENDAMIENTOS FINANCIEROS (15)'],
        [16, 'ARTÍCULOS DE CAFETERÍA (16)'],
        [17, 'ATENCIÓN Y PROMOCIÓN A CLIENTES (17)'],
        [18, 'ATENCIÓN A EMPLEADOS (18)'],
        [34, 'GASOLINA Y LUBRICANTES (34)'],
        [39, 'GASTOS DE ITBMS (40)'],
        [48, 'IMPRESOS, MATERIALES E INSUMOS (50)'],
        [50, 'INTERNET (52)'],
        [53, 'MANTENIMIENTO Y REPARACIÓN DE EQUIPOS DE OFICINA (55)'],
        [55, 'MANTENIMIENTO Y REPARACIÓN DE EQUIPOS RODANTES (57)'],
        [60, 'OTROS GASTOS Y MISCELANEOS VARIOS(No Aplica para Naturales) (62)'],
        [65, 'PEAJES, ESTACIONAMIENTO (67)'],
        [82, 'SUMINISTROS (84)'],
        [91, 'ÚTILES DE ASEO (93)'],
        [92, 'ÚTILES DE OFICINA (94)'],
        [132, 'REASEGUROS(No Aplica para Naturales) (10)'],
        [133, 'RESCATES Y DIVIDENDOS (11)'],
        [134, 'SUB-CONTRATISTAS (13)'],
        [135, 'OTROS COSTOS (14)'],
        [136, 'BENEFICIOS SINDICALES (19)'],
        [137, 'COMISIÓN POR SERVICIOS DE DESCUENTO (2)'],
        [138, 'COMUNICACIÓN (20)'],
        [139, 'CONTRIBUCIONES SOCIALES (21)'],
        [140, 'CORREO, COURIER Y APARTADO (22)'],
        [141, 'CORRESPONSALÍA (23)'],
        [142, 'CUOTAS Y SUSCRIPCIONES (24)'],
        [143, 'DECORACIONES (25)'],
        [144, 'DIETAS (26)'],
        [145, 'DIFERENCIA EN CAMBIO DE MONEDAS (27)'],
        [146, 'DISTRIBUCIÓN PARCIAL DE UTILIDADES A SOCIOS (28)'],
        [147, 'DISTRIBUCIÓN TOTAL DE UTILIDADES A SOCIOS (29)'],
        [148, 'ENCUADERNACIÓN/COMPAGINACIÓN (30)'],
        [149, 'EXCESOS Y PÉRDIDAS (31)'],
        [150, 'FALTANTE EN EFECTIVO (32)'],
        [151, 'FLETES Y ACARREOS (33)'],
        [152, 'GASTOS DE ADMINISTRACIÓN/COASEGUROS (35)'],
        [153, 'GASTO POR COMPRA DE BONOS (36)'],
        [154, 'GASTOS DE EMISIÓN DE TARJETAS (37)'],
        [155, 'GASTOS DE FIDEICOMISO (38)'],
        [156, 'GASTOS DE INVERSIONES (39)'],
        [157, 'COMISIÓN POR TARJETA DE CRÉDITO O CLAVE (4)'],
        [158, 'GASTOS DE ORGANIZACIÓN O PRE-OPERATIVOS (NIIF) (41)'],
        [159, 'GASTOS DE SUBASTAS (42)'],
        [160, 'GASTOS DE VIAJE LOCALES (43)'],
        [161, 'GASTOS DE VIAJES (44)'],
        [162, 'GASTOS LEGALES Y NOTARIALES (45)'],
        [163, 'GASTOS POR INGRESOS EN ESPECIES (46)'],
        [164, 'HERRAMIENTAS (47)'],
        [165, 'HORAS EXTRAS (48)'],
        [166, 'COSTO DE MERCANCÍA DAÑADA O DETERIORADA (5)'],
        [167, 'MPUESTOS Y PRIMAS RECUPERADAS (51)'],
        [168, 'LEVANTAMIENTO DE TEXTOS (53)'],
        [169, 'MANTENIMIENTO Y REPARACIÓN DE EQUIPOS DE INFORMÁTICA (54)'],
        [170, 'MANTENIMIENTO Y REPARACIÓN DE EQUIPOS DE SEGURIDAD (56)'],
        [171, 'MANTENIMIENTO Y REPARACIÓN DE OFICINA Y/O LOCALES (58)'],
        [172, 'MEDICAMENTOS (59)'],
        [173, 'INTERESES PAGADOS (6)'],
        [174, 'MEMBRESÍAS, CUOTAS Y SUSCRIPCIONES (60)'],
        [175, 'MULTAS Y RECARGOS (61)'],
        [176, 'OPERACIONES EXTERIORES ZONA LIBRE (63)'],
        [177, 'OTROS BENEFICIOS A EMPLEADOS (UNIFORMES, ETC.) (64)'],
        [178, 'PARTICIPACIÓN EN REASEGUROS (65)'],
        [179, 'PAPELERIA Y FOTOCOPIAS (66)'],
        [180, 'PÉRDIDA Y DESCARTES DE PROPIEDAD, PLANTA Y EQUIPO (68)'],
        [181, 'PÉRDIDA POR ROBO (69)'],
        [182, 'MERMA EN INVENTARIO (7)'],
        [183, 'PERIÓDICOS Y REVISTAS (70)'],
        [184, 'PIEZAS Y REPUESTOS (71)'],
        [185, 'PLACA Y REVISADOS (72)'],
        [186, 'PRIMA DE PRODUCCIÓN (73)'],
        [187, 'PROGRAMAS Y LICENCIAS (74)'],
        [188, 'PROMOCIÓN Y PROPAGANDA (75)'],
        [189, 'PUBLICIDAD Y RELACIONES PÚBLICAS (76)'],
        [190, 'REGALOS NAVIDEÑOS (77)'],
        [191, 'RESERVA MATEMÁTICA (78)'],
        [192, 'RESERVA CATASTRÓFICA (79)'],
        [193, 'MUESTRAS (8)'],
        [194, 'RESERVA ESTADÍSTICA (80)'],
        [195, 'RESERVAS (AUMENTOS Y DISMINUCIÓN) (81)'],
        [196, 'ROAMING (82)'],
        [197, 'SOBRANTES Y RECUPERACIÓN (83)'],
        [198, 'SEMINARIOS Y CAPACITACIONES (85)'],
        [199, 'SERVICIOS OCASIONALES (86)'],
        [200, 'TAXI (87)'],
        [201, 'TASA DEL MICI (88)'],
        [202, 'TIMBRES Y PAPEL SELLADO (89)'],
        [203, 'PRIMA DE PRODUCCIÓN (9)'],
        [204, 'TRAMITACIÓN DE ENTRADAS (90)'],
        [205, 'TRANSPORTE (91)'],
        [206, 'UNIFORME (92)'],
        [207, 'VIÁTICOS (95)'],
    ];
};
