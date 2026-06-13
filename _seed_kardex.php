<?php
use Illuminate\Support\Facades\DB;

$now = now();
$cid = 1;
$uid = 'cornelioroyer@winsof.com';

$alm3 = 3;
$alm4 = 4;
$i15=15; $i16=16; $i17=17; $i18=18; $i19=19;

DB::beginTransaction();
try {

echo "=== Movimientos junio 2026 ===\n";

$mov_ent_jun = DB::table('inv_movimientos')->insertGetId([
    'compania_id'=>$cid,'almacen_id'=>$alm3,'fecha'=>'2026-06-02',
    'tipo_movimiento'=>'ENTRADA','documento_origen'=>'COMPRA','documento_id'=>null,
    'descripcion'=>'Reposicion de stock junio','estado'=>'APLICADO',
    'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null,
]);
DB::table('inv_movimientos_detalle')->insert([
    ['movimiento_id'=>$mov_ent_jun,'item_id'=>$i15,'cantidad'=>5,'costo_unitario'=>625.00,'total'=>3125.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null],
    ['movimiento_id'=>$mov_ent_jun,'item_id'=>$i16,'cantidad'=>10,'costo_unitario'=>198.00,'total'=>1980.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null],
    ['movimiento_id'=>$mov_ent_jun,'item_id'=>$i19,'cantidad'=>20,'costo_unitario'=>11.50,'total'=>230.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null],
]);
echo "  mov ENTRADA junio id=$mov_ent_jun\n";

$mov_sal_jun = DB::table('inv_movimientos')->insertGetId([
    'compania_id'=>$cid,'almacen_id'=>$alm3,'fecha'=>'2026-06-10',
    'tipo_movimiento'=>'SALIDA','documento_origen'=>'VENTA','documento_id'=>2,
    'descripcion'=>'Salida por factura FC-000005','estado'=>'APLICADO',
    'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null,
]);
DB::table('inv_movimientos_detalle')->insert([
    ['movimiento_id'=>$mov_sal_jun,'item_id'=>$i16,'cantidad'=>2,'costo_unitario'=>195.00,'total'=>390.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null],
    ['movimiento_id'=>$mov_sal_jun,'item_id'=>$i18,'cantidad'=>5,'costo_unitario'=>5.20,'total'=>26.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null],
]);
echo "  mov SALIDA junio id=$mov_sal_jun\n";

echo "\n=== Generando kardex ===\n";

$lineas = [
    ['2026-01-15',$alm3,$i15,'ENTRADA',10,620.00,6200.00,'COMPRA',null,5],
    ['2026-01-15',$alm3,$i16,'ENTRADA',15,195.00,2925.00,'COMPRA',null,5],
    ['2026-01-15',$alm3,$i17,'ENTRADA',20,42.00,840.00,'COMPRA',null,5],
    ['2026-01-15',$alm3,$i18,'ENTRADA',50,5.20,260.00,'COMPRA',null,5],
    ['2026-01-15',$alm3,$i19,'ENTRADA',30,11.50,345.00,'COMPRA',null,5],
    ['2026-02-10',$alm3,$i15,'SALIDA',2,620.00,1240.00,'VENTA',1,6],
    ['2026-02-10',$alm3,$i16,'SALIDA',3,195.00,585.00,'VENTA',1,6],
    ['2026-03-01',$alm3,$i15,'SALIDA',2,620.00,1240.00,'TRANSFERENCIA',2,7],
    ['2026-03-01',$alm3,$i17,'SALIDA',5,42.00,210.00,'TRANSFERENCIA',2,7],
    ['2026-03-01',$alm4,$i15,'ENTRADA',2,620.00,1240.00,'TRANSFERENCIA',2,8],
    ['2026-03-01',$alm4,$i17,'ENTRADA',5,42.00,210.00,'TRANSFERENCIA',2,8],
    ['2026-06-02',$alm3,$i15,'ENTRADA',5,625.00,3125.00,'COMPRA',null,$mov_ent_jun],
    ['2026-06-02',$alm3,$i16,'ENTRADA',10,198.00,1980.00,'COMPRA',null,$mov_ent_jun],
    ['2026-06-02',$alm3,$i19,'ENTRADA',20,11.50,230.00,'COMPRA',null,$mov_ent_jun],
    ['2026-06-10',$alm3,$i16,'SALIDA',2,195.00,390.00,'VENTA',2,$mov_sal_jun],
    ['2026-06-10',$alm3,$i18,'SALIDA',5,5.20,26.00,'VENTA',2,$mov_sal_jun],
];

$saldos = [];
$inserts = [];
foreach($lineas as $l) {
    list($fecha,$alm,$item,$tipo,$qty,$costo_u,$total,$doc_origen,$doc_id,$mov_id) = $l;
    $key = $alm.'_'.$item;
    if(!isset($saldos[$key])) $saldos[$key] = ['qty'=>0,'costo'=>0];
    $s = &$saldos[$key];

    if($tipo === 'ENTRADA') {
        $nuevo_costo = $s['costo'] + $total;
        $nueva_qty   = $s['qty'] + $qty;
        $costo_prom  = $nueva_qty > 0 ? round($nuevo_costo/$nueva_qty,4) : $costo_u;
        $s['qty']    = $nueva_qty;
        $s['costo']  = $nuevo_costo;
        $inserts[] = [
            'compania_id'=>$cid,'item_id'=>$item,'almacen_id'=>$alm,'fecha'=>$fecha,
            'tipo_movimiento'=>$tipo,'documento_origen'=>$doc_origen,'documento_id'=>$doc_id,
            'entrada_cantidad'=>$qty,'entrada_costo'=>$total,
            'salida_cantidad'=>0,'salida_costo'=>0,
            'saldo_cantidad'=>$s['qty'],'saldo_costo'=>round($s['costo'],2),
            'costo_promedio'=>$costo_prom,'asiento_id'=>null,
            'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null,
        ];
    } else {
        $s['qty']   -= $qty;
        $s['costo'] -= $total;
        $inserts[] = [
            'compania_id'=>$cid,'item_id'=>$item,'almacen_id'=>$alm,'fecha'=>$fecha,
            'tipo_movimiento'=>$tipo,'documento_origen'=>$doc_origen,'documento_id'=>$doc_id,
            'entrada_cantidad'=>0,'entrada_costo'=>0,
            'salida_cantidad'=>$qty,'salida_costo'=>$total,
            'saldo_cantidad'=>$s['qty'],'saldo_costo'=>round($s['costo'],2),
            'costo_promedio'=>$s['qty']>0 ? round($s['costo']/$s['qty'],4) : 0,'asiento_id'=>null,
            'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>null,
        ];
    }
}

DB::table('inv_kardex')->insert($inserts);
echo "  Insertados: ".count($inserts)." lineas de kardex\n";

echo "\n=== Actualizando inv_existencias ===\n";
foreach($saldos as $key => $s) {
    list($alm,$item) = explode('_',$key);
    DB::table('inv_existencias')
        ->where('compania_id',$cid)->where('almacen_id',$alm)->where('item_id',$item)
        ->update(['cantidad'=>$s['qty'],'costo_promedio'=>$s['qty']>0?round($s['costo']/$s['qty'],4):0]);
    echo "  alm=$alm item=$item qty={$s['qty']}\n";
}

DB::commit();
echo "\nOK\n";
} catch(Exception $e) {
    DB::rollBack();
    echo "ERROR: ".$e->getMessage()."\n";
}
