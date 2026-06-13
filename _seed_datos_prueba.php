<?php
use Carbon\Carbon;

$now = Carbon::now();
$cid = 1;
$uid = 2;

DB::beginTransaction();
try {

// ─── 1. MONEDAS + TASAS ──────────────────────────────────────────────────────
$mUSD = DB::table('core_monedas')->insertGetId([
    'compania_id'=>$cid,'codigo'=>'USD','nombre'=>'Dólar Estadounidense','simbolo'=>'$','activa'=>true,
    'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid,
]);
$mEUR = DB::table('core_monedas')->insertGetId([
    'compania_id'=>$cid,'codigo'=>'EUR','nombre'=>'Euro','simbolo'=>'€','activa'=>true,
    'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid,
]);
DB::table('core_tasas_cambio')->insert([
    ['moneda_id'=>$mUSD,'fecha'=>'2026-06-01','tasa'=>1.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['moneda_id'=>$mEUR,'fecha'=>'2026-06-01','tasa'=>1.08,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// ─── 2. SUCURSALES / DEPTS / CENTROS / PROYECTOS ─────────────────────────────
DB::table('core_sucursales')->insert([
    ['compania_id'=>$cid,'codigo'=>'MAIN','nombre'=>'Ciudad de Panamá','direccion'=>'Calle 50, Punta Pacífica','telefono'=>'507-200-1000','activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'codigo'=>'CHP','nombre'=>'La Chorrera','direccion'=>'Av. Omar Torrijos, Local 12','telefono'=>'507-250-2000','activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'codigo'=>'COL','nombre'=>'Colón','direccion'=>'Av. del Frente, Zona Libre','telefono'=>'507-441-3000','activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

$dAdmin = DB::table('core_departamentos')->insertGetId(['compania_id'=>$cid,'codigo'=>'ADM','nombre'=>'Administración','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('core_departamentos')->insert([
    ['compania_id'=>$cid,'codigo'=>'VEN','nombre'=>'Ventas','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'codigo'=>'CTB','nombre'=>'Contabilidad','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'codigo'=>'OPS','nombre'=>'Operaciones','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

$ccAdm = DB::table('core_centros_costos')->insertGetId(['compania_id'=>$cid,'codigo'=>'CC-ADM','nombre'=>'Centro Admin','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$ccVen = DB::table('core_centros_costos')->insertGetId(['compania_id'=>$cid,'codigo'=>'CC-VEN','nombre'=>'Centro Ventas','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('core_centros_costos')->insert(['compania_id'=>$cid,'codigo'=>'CC-OPS','nombre'=>'Centro Operaciones','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

DB::table('core_proyectos')->insert([
    ['compania_id'=>$cid,'codigo'=>'PROY-ERP','nombre'=>'Implementación ERP','fecha_inicio'=>'2026-01-01','fecha_fin'=>'2026-12-31','estado'=>'ACTIVO','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'codigo'=>'PROY-COL','nombre'=>'Expansión Colón','fecha_inicio'=>'2026-03-01','fecha_fin'=>'2026-09-30','estado'=>'ACTIVO','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// ─── 3. RETENCIONES ──────────────────────────────────────────────────────────
DB::table('tax_retenciones')->insert([
    ['compania_id'=>$cid,'codigo'=>'RET-ITBMS50','nombre'=>'Retención ITBMS 50%','tipo'=>'ITBMS','porcentaje'=>50,'cuenta_id'=>165,'activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'codigo'=>'RET-ISR5','nombre'=>'Retención ISR 5%','tipo'=>'ISR','porcentaje'=>5,'cuenta_id'=>167,'activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// ─── 4. CONTACTOS ADICIONALES + DATOS EXTENDIDOS ─────────────────────────────
$c9  = DB::table('contact_contactos')->insertGetId(['compania_id'=>$cid,'codigo'=>'CLI-003','nombre'=>'TECNO SOLUTIONS, S.A.','razon_social'=>'TECNO SOLUTIONS, S.A.','tipo_persona'=>'JURIDICA','identificacion'=>'155-711234-2-2019','dv'=>'89','email'=>'ventas@tecnosolutions.pa','telefono'=>'507-300-5000','direccion'=>'Torre Global Bank, Piso 12','pais'=>'PA','provincia'=>'Panamá','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$c10 = DB::table('contact_contactos')->insertGetId(['compania_id'=>$cid,'codigo'=>'CLI-004','nombre'=>'FERRETERÍA PANAMÁ, S.A.','razon_social'=>'FERRETERÍA PANAMÁ, S.A.','tipo_persona'=>'JURIDICA','identificacion'=>'155-321456-2-2015','dv'=>'31','email'=>'compras@ferrpanama.pa','telefono'=>'507-214-7890','direccion'=>'Vía Brasil, Local 5','pais'=>'PA','provincia'=>'Panamá','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$c11 = DB::table('contact_contactos')->insertGetId(['compania_id'=>$cid,'codigo'=>'PROV-003','nombre'=>'SUMINISTROS RÁPIDOS, S.A.','razon_social'=>'SUMINISTROS RÁPIDOS, S.A.','tipo_persona'=>'JURIDICA','identificacion'=>'155-654789-2-2018','dv'=>'55','email'=>'pedidos@sumrap.pa','telefono'=>'507-265-4321','direccion'=>'Zona Industrial, Galpón 8','pais'=>'PA','provincia'=>'Panamá','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// Direcciones
DB::table('contact_direcciones')->insert([
    ['contacto_id'=>2,'tipo'=>'PRINCIPAL','direccion'=>'Calle 50, Edificio Torre Delta, Piso 3','pais'=>'PA','provincia'=>'Panamá','distrito'=>'Panamá','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>2,'tipo'=>'ENTREGA','direccion'=>'Milla 8 Carretera Transístmica, Bodega 4','pais'=>'PA','provincia'=>'Panamá','distrito'=>'San Miguelito','principal'=>false,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>3,'tipo'=>'PRINCIPAL','direccion'=>'Zona Libre de Colón, Módulo 22','pais'=>'PA','provincia'=>'Colón','distrito'=>'Colón','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>$c9,'tipo'=>'PRINCIPAL','direccion'=>'Torre Global Bank, Piso 12, Panama City','pais'=>'PA','provincia'=>'Panamá','distrito'=>'Panamá','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>$c11,'tipo'=>'PRINCIPAL','direccion'=>'Zona Industrial Juan Díaz, Galpón 8','pais'=>'PA','provincia'=>'Panamá','distrito'=>'Juan Díaz','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Personas de contacto
DB::table('contact_personas_contacto')->insert([
    ['contacto_id'=>2,'nombre'=>'Roberto Sánchez','cargo'=>'Gerente de Compras','email'=>'rsanchez@comercial.pa','telefono'=>'507-6500-1111','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>3,'nombre'=>'Ana González','cargo'=>'Administradora','email'=>'agonzalez@distribuidora.pa','telefono'=>'507-6600-2222','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>$c9,'nombre'=>'Luis Herrera','cargo'=>'Director Técnico','email'=>'lherrera@tecnosolutions.pa','telefono'=>'507-6700-3333','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>$c9,'nombre'=>'Sofía Martínez','cargo'=>'Jefa de Ventas','email'=>'smartinez@tecnosolutions.pa','telefono'=>'507-6700-4444','principal'=>false,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>$c11,'nombre'=>'Marco Ríos','cargo'=>'Logística','email'=>'mrios@sumrap.pa','telefono'=>'507-6800-5555','principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Cuentas bancarias de contactos
DB::table('contact_cuentas_bancarias')->insert([
    ['contacto_id'=>2,'banco'=>'Banco General','numero_cuenta'=>'04-88-123456-7','tipo_cuenta'=>'CORRIENTE','moneda_id'=>null,'principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>3,'banco'=>'Banistmo','numero_cuenta'=>'02-55-987654-3','tipo_cuenta'=>'AHORROS','moneda_id'=>null,'principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['contacto_id'=>$c9,'banco'=>'BAC Panama','numero_cuenta'=>'00-33-555321-0','tipo_cuenta'=>'CORRIENTE','moneda_id'=>null,'principal'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// ─── 5. ITEM CATEGORIAS ──────────────────────────────────────────────────────
$catElec = DB::table('item_categorias')->insertGetId(['compania_id'=>$cid,'nombre'=>'Electrónica','activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$catPap  = DB::table('item_categorias')->insertGetId(['compania_id'=>$cid,'nombre'=>'Papelería','activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$catServ = DB::table('item_categorias')->insertGetId(['compania_id'=>$cid,'nombre'=>'Servicios','activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// ─── 6. ITEMS + PRECIOS ──────────────────────────────────────────────────────
$i1 = DB::table('item_productos_servicios')->insertGetId(['compania_id'=>$cid,'codigo'=>'LAP-001','nombre'=>'Laptop Dell Inspiron 15','descripcion'=>'Laptop 15" Intel i5 16GB RAM 512GB SSD','tipo'=>'PRODUCTO','categoria_id'=>$catElec,'unidad_medida_id'=>1,'precio_venta'=>850.00,'costo'=>620.00,'cuenta_ingreso_id'=>184,'cuenta_gasto_id'=>197,'cuenta_inventario_id'=>147,'cuenta_costo_venta_id'=>197,'impuesto_id'=>2,'activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$i2 = DB::table('item_productos_servicios')->insertGetId(['compania_id'=>$cid,'codigo'=>'MON-001','nombre'=>'Monitor LG 24"','descripcion'=>'Monitor 24 pulgadas Full HD IPS','tipo'=>'PRODUCTO','categoria_id'=>$catElec,'unidad_medida_id'=>1,'precio_venta'=>280.00,'costo'=>195.00,'cuenta_ingreso_id'=>184,'cuenta_gasto_id'=>197,'cuenta_inventario_id'=>147,'cuenta_costo_venta_id'=>197,'impuesto_id'=>2,'activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$i3 = DB::table('item_productos_servicios')->insertGetId(['compania_id'=>$cid,'codigo'=>'TEC-001','nombre'=>'Teclado Logitech Inalámbrico','descripcion'=>'Combo teclado y mouse inalámbrico','tipo'=>'PRODUCTO','categoria_id'=>$catElec,'unidad_medida_id'=>1,'precio_venta'=>65.00,'costo'=>42.00,'cuenta_ingreso_id'=>184,'cuenta_gasto_id'=>197,'cuenta_inventario_id'=>147,'cuenta_costo_venta_id'=>197,'impuesto_id'=>2,'activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$i4 = DB::table('item_productos_servicios')->insertGetId(['compania_id'=>$cid,'codigo'=>'PAP-001','nombre'=>'Resma Papel A4 75gr','descripcion'=>'Resma 500 hojas papel bond blanco','tipo'=>'PRODUCTO','categoria_id'=>$catPap,'unidad_medida_id'=>1,'precio_venta'=>8.50,'costo'=>5.20,'cuenta_ingreso_id'=>184,'cuenta_gasto_id'=>197,'cuenta_inventario_id'=>147,'cuenta_costo_venta_id'=>197,'impuesto_id'=>2,'activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$i5 = DB::table('item_productos_servicios')->insertGetId(['compania_id'=>$cid,'codigo'=>'CAR-001','nombre'=>'Cartucho Tinta HP 664 Negro','descripcion'=>'Cartucho de tinta original HP color negro','tipo'=>'PRODUCTO','categoria_id'=>$catPap,'unidad_medida_id'=>1,'precio_venta'=>18.00,'costo'=>11.50,'cuenta_ingreso_id'=>184,'cuenta_gasto_id'=>197,'cuenta_inventario_id'=>147,'cuenta_costo_venta_id'=>197,'impuesto_id'=>2,'activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$s1 = DB::table('item_productos_servicios')->insertGetId(['compania_id'=>$cid,'codigo'=>'SRV-001','nombre'=>'Soporte Técnico por Hora','descripcion'=>'Soporte técnico en sitio o remoto','tipo'=>'SERVICIO','categoria_id'=>$catServ,'unidad_medida_id'=>2,'precio_venta'=>45.00,'costo'=>0,'cuenta_ingreso_id'=>184,'cuenta_gasto_id'=>225,'cuenta_inventario_id'=>null,'cuenta_costo_venta_id'=>null,'impuesto_id'=>2,'activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$s2 = DB::table('item_productos_servicios')->insertGetId(['compania_id'=>$cid,'codigo'=>'SRV-002','nombre'=>'Consultoría ERP','descripcion'=>'Consultoría e implementación de sistemas ERP','tipo'=>'SERVICIO','categoria_id'=>$catServ,'unidad_medida_id'=>3,'precio_venta'=>1200.00,'costo'=>0,'cuenta_ingreso_id'=>186,'cuenta_gasto_id'=>225,'cuenta_inventario_id'=>null,'cuenta_costo_venta_id'=>null,'impuesto_id'=>2,'activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// Precios adicionales (lista mayorista)
DB::table('item_precios')->insert([
    ['item_id'=>$i1,'lista'=>'MAYORISTA','precio'=>780.00,'fecha_inicio'=>'2026-01-01','fecha_fin'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['item_id'=>$i2,'lista'=>'MAYORISTA','precio'=>255.00,'fecha_inicio'=>'2026-01-01','fecha_fin'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['item_id'=>$i3,'lista'=>'MAYORISTA','precio'=>58.00,'fecha_inicio'=>'2026-01-01','fecha_fin'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['item_id'=>$i4,'lista'=>'CAJA','precio'=>75.00,'fecha_inicio'=>'2026-01-01','fecha_fin'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// ─── 7. ALMACENES + INVENTARIO ───────────────────────────────────────────────
$alm1 = DB::table('inv_almacenes')->insertGetId(['compania_id'=>$cid,'codigo'=>'ALM-CEN','nombre'=>'Almacén Central','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$alm2 = DB::table('inv_almacenes')->insertGetId(['compania_id'=>$cid,'codigo'=>'ALM-COL','nombre'=>'Almacén Colón','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// Entrada inicial ALM-CEN
$mov1 = DB::table('inv_movimientos')->insertGetId(['compania_id'=>$cid,'almacen_id'=>$alm1,'fecha'=>'2026-01-15','tipo_movimiento'=>'ENTRADA','documento_origen'=>'COMPRA','documento_id'=>null,'descripcion'=>'Entrada inicial de mercancía','asiento_id'=>null,'estado'=>'APLICADO','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('inv_movimientos_detalle')->insert([
    ['movimiento_id'=>$mov1,'item_id'=>$i1,'cantidad'=>10,'costo_unitario'=>620.00,'total'=>6200.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$mov1,'item_id'=>$i2,'cantidad'=>15,'costo_unitario'=>195.00,'total'=>2925.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$mov1,'item_id'=>$i3,'cantidad'=>20,'costo_unitario'=>42.00,'total'=>840.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$mov1,'item_id'=>$i4,'cantidad'=>50,'costo_unitario'=>5.20,'total'=>260.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$mov1,'item_id'=>$i5,'cantidad'=>30,'costo_unitario'=>11.50,'total'=>345.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Salida por venta
$mov2 = DB::table('inv_movimientos')->insertGetId(['compania_id'=>$cid,'almacen_id'=>$alm1,'fecha'=>'2026-02-10','tipo_movimiento'=>'SALIDA','documento_origen'=>'VENTA','documento_id'=>1,'descripcion'=>'Salida por venta FV-000001','asiento_id'=>null,'estado'=>'APLICADO','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('inv_movimientos_detalle')->insert([
    ['movimiento_id'=>$mov2,'item_id'=>$i1,'cantidad'=>2,'costo_unitario'=>620.00,'total'=>1240.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$mov2,'item_id'=>$i2,'cantidad'=>3,'costo_unitario'=>195.00,'total'=>585.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Transferencia ALM-CEN → ALM-COL
$trans1 = DB::table('inv_transferencias')->insertGetId(['compania_id'=>$cid,'almacen_origen_id'=>$alm1,'almacen_destino_id'=>$alm2,'fecha'=>'2026-03-01','estado'=>'APLICADA','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$movSal = DB::table('inv_movimientos')->insertGetId(['compania_id'=>$cid,'almacen_id'=>$alm1,'fecha'=>'2026-03-01','tipo_movimiento'=>'SALIDA','documento_origen'=>'TRANSFERENCIA','documento_id'=>$trans1,'descripcion'=>'Transferencia a ALM-COL','asiento_id'=>null,'estado'=>'APLICADO','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$movEnt = DB::table('inv_movimientos')->insertGetId(['compania_id'=>$cid,'almacen_id'=>$alm2,'fecha'=>'2026-03-01','tipo_movimiento'=>'ENTRADA','documento_origen'=>'TRANSFERENCIA','documento_id'=>$trans1,'descripcion'=>'Transferencia desde ALM-CEN','asiento_id'=>null,'estado'=>'APLICADO','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('inv_movimientos_detalle')->insert([
    ['movimiento_id'=>$movSal,'item_id'=>$i1,'cantidad'=>2,'costo_unitario'=>620.00,'total'=>1240.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$movSal,'item_id'=>$i3,'cantidad'=>5,'costo_unitario'=>42.00,'total'=>210.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$movEnt,'item_id'=>$i1,'cantidad'=>2,'costo_unitario'=>620.00,'total'=>1240.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['movimiento_id'=>$movEnt,'item_id'=>$i3,'cantidad'=>5,'costo_unitario'=>42.00,'total'=>210.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Existencias finales
DB::table('inv_existencias')->insert([
    ['compania_id'=>$cid,'item_id'=>$i1,'almacen_id'=>$alm1,'cantidad'=>6,'costo_promedio'=>620.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'item_id'=>$i2,'almacen_id'=>$alm1,'cantidad'=>12,'costo_promedio'=>195.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'item_id'=>$i3,'almacen_id'=>$alm1,'cantidad'=>13,'costo_promedio'=>42.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'item_id'=>$i4,'almacen_id'=>$alm1,'cantidad'=>50,'costo_promedio'=>5.20,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'item_id'=>$i5,'almacen_id'=>$alm1,'cantidad'=>30,'costo_promedio'=>11.50,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'item_id'=>$i1,'almacen_id'=>$alm2,'cantidad'=>2,'costo_promedio'=>620.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'item_id'=>$i3,'almacen_id'=>$alm2,'cantidad'=>5,'costo_promedio'=>42.00,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// ─── 8. ACTIVOS FIJOS ────────────────────────────────────────────────────────
$afiCat1 = DB::table('afi_categorias')->insertGetId(['compania_id'=>$cid,'codigo'=>'EQC','nombre'=>'Equipo de Cómputo','vida_util_meses_default'=>36,'cuenta_activo_id'=>153,'cuenta_depreciacion_acum_id'=>155,'cuenta_gasto_depreciacion_id'=>212,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$afiCat2 = DB::table('afi_categorias')->insertGetId(['compania_id'=>$cid,'codigo'=>'MOB','nombre'=>'Mobiliario y Enseres','vida_util_meses_default'=>84,'cuenta_activo_id'=>154,'cuenta_depreciacion_acum_id'=>155,'cuenta_gasto_depreciacion_id'=>212,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$afiCat3 = DB::table('afi_categorias')->insertGetId(['compania_id'=>$cid,'codigo'=>'MAQ','nombre'=>'Maquinaria y Equipos','vida_util_meses_default'=>60,'cuenta_activo_id'=>153,'cuenta_depreciacion_acum_id'=>155,'cuenta_gasto_depreciacion_id'=>212,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

$afiUb1 = DB::table('afi_ubicaciones')->insertGetId(['compania_id'=>$cid,'codigo'=>'OFC-CEN','nombre'=>'Oficina Central','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$afiUb2 = DB::table('afi_ubicaciones')->insertGetId(['compania_id'=>$cid,'codigo'=>'BOD','nombre'=>'Bodega','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('afi_ubicaciones')->insert(['compania_id'=>$cid,'codigo'=>'SUC-COL','nombre'=>'Sucursal Colón','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

$afi1 = DB::table('afi_activos')->insertGetId(['compania_id'=>$cid,'codigo'=>'AF-001','descripcion'=>'Servidor HP ProLiant DL380','categoria_id'=>$afiCat1,'ubicacion_id'=>$afiUb1,'fecha_compra'=>'2024-01-15','fecha_inicio_depreciacion'=>'2024-02-01','valor_compra'=>4500.00,'valor_residual'=>500.00,'vida_util_meses'=>36,'metodo_depreciacion'=>'LINEA_RECTA','cuenta_activo_id'=>153,'cuenta_depreciacion_acum_id'=>155,'cuenta_gasto_depreciacion_id'=>212,'estado'=>'ACTIVO','asiento_compra_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$afi2 = DB::table('afi_activos')->insertGetId(['compania_id'=>$cid,'codigo'=>'AF-002','descripcion'=>'Escritorios de Oficina (10 unidades)','categoria_id'=>$afiCat2,'ubicacion_id'=>$afiUb1,'fecha_compra'=>'2023-06-01','fecha_inicio_depreciacion'=>'2023-07-01','valor_compra'=>2800.00,'valor_residual'=>200.00,'vida_util_meses'=>84,'metodo_depreciacion'=>'LINEA_RECTA','cuenta_activo_id'=>154,'cuenta_depreciacion_acum_id'=>155,'cuenta_gasto_depreciacion_id'=>212,'estado'=>'ACTIVO','asiento_compra_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('afi_activos')->insert([
    ['compania_id'=>$cid,'codigo'=>'AF-003','descripcion'=>'Fotocopiadora Canon iR2625','categoria_id'=>$afiCat3,'ubicacion_id'=>$afiUb1,'fecha_compra'=>'2024-08-20','fecha_inicio_depreciacion'=>'2024-09-01','valor_compra'=>1800.00,'valor_residual'=>150.00,'vida_util_meses'=>60,'metodo_depreciacion'=>'LINEA_RECTA','cuenta_activo_id'=>153,'cuenta_depreciacion_acum_id'=>155,'cuenta_gasto_depreciacion_id'=>212,'estado'=>'ACTIVO','asiento_compra_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'codigo'=>'AF-004','descripcion'=>'Laptop HP EliteBook 840 G9','categoria_id'=>$afiCat1,'ubicacion_id'=>$afiUb1,'fecha_compra'=>'2025-03-10','fecha_inicio_depreciacion'=>'2025-04-01','valor_compra'=>1200.00,'valor_residual'=>100.00,'vida_util_meses'=>36,'metodo_depreciacion'=>'LINEA_RECTA','cuenta_activo_id'=>153,'cuenta_depreciacion_acum_id'=>155,'cuenta_gasto_depreciacion_id'=>212,'estado'=>'ACTIVO','asiento_compra_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Revaluación
DB::table('afi_revaluaciones')->insert(['activo_id'=>$afi2,'fecha'=>'2026-01-31','valor_anterior'=>2800.00,'valor_nuevo'=>3200.00,'asiento_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// ─── 9. BANCOS ───────────────────────────────────────────────────────────────
$banco1 = DB::table('bco_bancos')->insertGetId(['codigo'=>'BGEN','nombre'=>'Banco General, S.A.','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$banco2 = DB::table('bco_bancos')->insertGetId(['codigo'=>'BANI','nombre'=>'Banistmo, S.A.','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$banco3 = DB::table('bco_bancos')->insertGetId(['codigo'=>'BAC','nombre'=>'BAC Credomatic Panama','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

$bco1 = DB::table('bco_cuentas')->insertGetId(['compania_id'=>$cid,'banco_id'=>$banco1,'cuenta_contable_id'=>137,'numero_cuenta'=>'04-88-01-1234567-8','nombre'=>'Cta. Corriente — Banco General','tipo_cuenta'=>'CORRIENTE','moneda_id'=>$mUSD,'saldo_inicial'=>15000.00,'activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$bco2 = DB::table('bco_cuentas')->insertGetId(['compania_id'=>$cid,'banco_id'=>$banco2,'cuenta_contable_id'=>137,'numero_cuenta'=>'02-55-02-9876543-1','nombre'=>'Cta. Corriente — Banistmo','tipo_cuenta'=>'CORRIENTE','moneda_id'=>$mUSD,'saldo_inicial'=>8500.00,'activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$bco3 = DB::table('bco_cuentas')->insertGetId(['compania_id'=>$cid,'banco_id'=>$banco3,'cuenta_contable_id'=>137,'numero_cuenta'=>'00-33-03-5551234-9','nombre'=>'Cta. Ahorros — BAC','tipo_cuenta'=>'AHORROS','moneda_id'=>$mUSD,'saldo_inicial'=>3200.00,'activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// Movimientos bancarios (bco1 - Banco General)
$saldo = 15000.00 + 5000.00;
DB::table('bco_movimientos')->insert(['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'fecha'=>'2026-01-10','tipo_movimiento'=>'DEPOSITO','descripcion'=>'Cobro factura cliente COMERCIAL EJEMPLO','referencia'=>'DEP-2601-001','debito'=>0,'credito'=>5000.00,'saldo'=>$saldo,'contacto_id'=>2,'conciliado'=>true,'asiento_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$saldo -= 1200.00;
DB::table('bco_movimientos')->insert(['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'fecha'=>'2026-01-20','tipo_movimiento'=>'CHEQUE','descripcion'=>'Pago proveedor DISTRIBUIDORA MODELO','referencia'=>'CHQ-0001','debito'=>1200.00,'credito'=>0,'saldo'=>$saldo,'contacto_id'=>3,'conciliado'=>true,'asiento_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$saldo -= 35.50;
DB::table('bco_movimientos')->insert(['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'fecha'=>'2026-01-31','tipo_movimiento'=>'CARGO','descripcion'=>'Comisión mantenimiento cuenta enero 2026','referencia'=>'BGEN-ENE-2026','debito'=>35.50,'credito'=>0,'saldo'=>$saldo,'contacto_id'=>null,'conciliado'=>true,'asiento_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$saldo += 3500.00;
DB::table('bco_movimientos')->insert(['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'fecha'=>'2026-02-05','tipo_movimiento'=>'DEPOSITO','descripcion'=>'Abono de TECNO SOLUTIONS factura FV-002','referencia'=>'DEP-2602-001','debito'=>0,'credito'=>3500.00,'saldo'=>$saldo,'contacto_id'=>$c9,'conciliado'=>false,'asiento_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$saldo -= 800.00;
DB::table('bco_movimientos')->insert(['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'fecha'=>'2026-02-15','tipo_movimiento'=>'TRANSFERENCIA','descripcion'=>'Transferencia a cta. Banistmo para nomina','referencia'=>'TRF-2602-001','debito'=>800.00,'credito'=>0,'saldo'=>$saldo,'contacto_id'=>null,'conciliado'=>false,'asiento_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// Cheques
DB::table('bco_cheques')->insert([
    ['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'numero_cheque'=>'0001','fecha'=>'2026-01-20','beneficiario_id'=>3,'monto'=>1200.00,'estado'=>'COBRADO','adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'numero_cheque'=>'0002','fecha'=>'2026-02-28','beneficiario_id'=>$c11,'monto'=>650.00,'estado'=>'EMITIDO','adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco2,'numero_cheque'=>'1001','fecha'=>'2026-03-05','beneficiario_id'=>4,'monto'=>320.00,'estado'=>'EMITIDO','adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Depósitos
DB::table('bco_depositos')->insert([
    ['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'fecha'=>'2026-01-10','referencia'=>'DEP-ENE-001','monto'=>5000.00,'asiento_id'=>null,'adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'fecha'=>'2026-02-05','referencia'=>'DEP-FEB-001','monto'=>3500.00,'asiento_id'=>null,'adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// Transferencia entre cuentas
DB::table('bco_transferencias')->insert(['compania_id'=>$cid,'cuenta_origen_id'=>$bco1,'cuenta_destino_id'=>$bco2,'fecha'=>'2026-02-15','monto'=>800.00,'referencia'=>'TRF-FEB-001','asiento_id'=>null,'estado'=>'APLICADA','adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// Conciliación enero cerrada (cuenta_bancaria_id XOR cuenta_contable_id)
DB::table('bco_conciliaciones')->insert(['compania_id'=>$cid,'cuenta_bancaria_id'=>$bco1,'cuenta_contable_id'=>null,'fecha_corte'=>'2026-01-31','saldo_banco'=>18764.50,'saldo_libros'=>18764.50,'diferencia'=>0.00,'estado'=>'CERRADA','usuario_id'=>$uid,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// ─── 10. CAJA CHICA ──────────────────────────────────────────────────────────
$caja1 = DB::table('caj_cajas')->insertGetId(['compania_id'=>$cid,'codigo'=>'CAJ-001','nombre'=>'Caja Chica Principal','cuenta_contable_id'=>136,'responsable_id'=>$uid,'activa'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('caj_movimientos')->insert([
    ['compania_id'=>$cid,'caja_id'=>$caja1,'fecha'=>'2026-05-02','tipo_movimiento'=>'INGRESO','beneficiario'=>'Reembolso inicial','descripcion'=>'Apertura de fondo caja chica B/.500','monto'=>500.00,'cuenta_contable_id'=>136,'centro_costo_id'=>$ccAdm,'proyecto_id'=>null,'asiento_id'=>null,'adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'caja_id'=>$caja1,'fecha'=>'2026-05-05','tipo_movimiento'=>'EGRESO','beneficiario'=>'Supermercado Rey','descripcion'=>'Café, agua y suministros para oficina','monto'=>38.50,'cuenta_contable_id'=>225,'centro_costo_id'=>$ccAdm,'proyecto_id'=>null,'asiento_id'=>null,'adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['compania_id'=>$cid,'caja_id'=>$caja1,'fecha'=>'2026-05-10','tipo_movimiento'=>'EGRESO','beneficiario'=>'Cable & Wireless','descripcion'=>'Pago internet auxiliar oficina','monto'=>65.00,'cuenta_contable_id'=>221,'centro_costo_id'=>$ccAdm,'proyecto_id'=>null,'asiento_id'=>null,'adjunto_id'=>null,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);
DB::table('caj_vales')->insert(['caja_id'=>$caja1,'fecha'=>'2026-05-08','beneficiario'=>'Pedro Morales','monto'=>25.00,'motivo'=>'Transporte a notaría para trámite','adjunto_id'=>null,'estado'=>'LIQUIDADO','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

// ─── 11. VENDEDORES + COMISIONES ─────────────────────────────────────────────
$vend1 = DB::table('ventas_vendedores')->insertGetId(['compania_id'=>$cid,'contacto_id'=>7,'usuario_id'=>null,'codigo'=>'VEND-01','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
$vend2 = DB::table('ventas_vendedores')->insertGetId(['compania_id'=>$cid,'contacto_id'=>6,'usuario_id'=>null,'codigo'=>'VEND-02','activo'=>true,'created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);
DB::table('ventas_comisiones')->insert([
    ['vendedor_id'=>$vend1,'factura_id'=>1,'porcentaje'=>3.00,'monto'=>45.50,'estado'=>'PENDIENTE','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
    ['vendedor_id'=>$vend2,'factura_id'=>2,'porcentaje'=>3.00,'monto'=>28.20,'estado'=>'PAGADA','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid],
]);

// ─── 12. CIERRE CONTABLE ─────────────────────────────────────────────────────
DB::table('cgl_cierres')->insert(['compania_id'=>$cid,'periodo_id'=>26,'estado'=>'COMPLETADO','cerrado_por'=>$uid,'fecha_cierre'=>'2026-02-05','observacion'=>'Cierre período enero 2026. Verificado por contabilidad sin diferencias.','created_at'=>$now,'created_by'=>$uid,'updated_at'=>$now,'updated_by'=>$uid]);

DB::commit();
echo "\n=== DATOS DE PRUEBA INSERTADOS EXITOSAMENTE ===\n";
echo "core_monedas: 2 | core_sucursales: 3 | core_departamentos: 4\n";
echo "core_centros_costos: 3 | core_proyectos: 2 | tax_retenciones: 2\n";
echo "contactos nuevos: 3 | direcciones: 5 | personas: 5 | cuentas_bancarias_contacto: 3\n";
echo "item_categorias: 3 | items: 7 (5 prod + 2 serv) | item_precios: 4\n";
echo "inv_almacenes: 2 | inv_movimientos: 4 | inv_existencias: 7 | inv_transferencias: 1\n";
echo "afi: 3 cat | 3 ub | 4 activos | 1 revaluacion\n";
echo "bco_bancos: 3 | bco_cuentas: 3 | bco_movimientos: 5\n";
echo "bco_cheques: 3 | bco_depositos: 2 | bco_transferencias: 1 | bco_conciliaciones: 1\n";
echo "caj_cajas: 1 | caj_movimientos: 3 | caj_vales: 1\n";
echo "ventas_vendedores: 2 | ventas_comisiones: 2\n";
echo "cgl_cierres: 1\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "ERROR: ".$e->getMessage()."\n";
    echo "En: ".$e->getFile()." línea ".$e->getLine()."\n";
}
