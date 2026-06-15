<x-app-layout>
<div
    x-data="{
        search: '',
        activeCategory: null,
        activeArticle: null,
        categories: [
            { key: 'contabilidad', label: 'Contabilidad',     icon: 'M9 7.5h6M9 12h6M9 16.5h3M6.75 3h10.5A2.25 2.25 0 0 1 19.5 5.25v13.5A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75V5.25A2.25 2.25 0 0 1 6.75 3Z',   color: 'bg-blue-50 text-blue-700 ring-blue-200' },
            { key: 'cxc',         label: 'Cuentas por Cobrar',icon: 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z', color: 'bg-green-50 text-green-700 ring-green-200' },
            { key: 'cxp',         label: 'Cuentas por Pagar', icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z', color: 'bg-orange-50 text-orange-700 ring-orange-200' },
            { key: 'ventas',      label: 'Ventas',             icon: 'M4.5 6.75h15M6 6.75l1.5 12h9l1.5-12M9 10.5h6M9.75 3h4.5',                                                                                                                 color: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
            { key: 'compras',     label: 'Compras',            icon: 'M3.75 6.75h16.5l-1.5 12h-13.5l-1.5-12ZM8.25 6.75a3.75 3.75 0 0 1 7.5 0',                                                                                                  color: 'bg-amber-50 text-amber-700 ring-amber-200' },
            { key: 'bancos',      label: 'Bancos',             icon: 'M3 10.5h18M4.5 10.5V18M8.25 10.5V18M12 10.5V18m3.75-7.5V18M19.5 10.5V18M3.75 21h16.5M12 3l8.25 4.5H3.75L12 3Z',                                                         color: 'bg-cyan-50 text-cyan-700 ring-cyan-200' },
            { key: 'inventario',  label: 'Inventario',         icon: 'M21 8.25 12 3 3 8.25m18 0-9 5.25m9-5.25v7.5L12 21m0-7.5L3 8.25m9 5.25V21M3 8.25v7.5L12 21',                                                                               color: 'bg-teal-50 text-teal-700 ring-teal-200' },
            { key: 'activos',     label: 'Activos Fijos',      icon: 'M4.5 20.25h15M6 20.25V6.75A2.25 2.25 0 0 1 8.25 4.5h7.5A2.25 2.25 0 0 1 18 6.75v13.5M9 9h6M9 12.75h6M9 16.5h3',                                                         color: 'bg-rose-50 text-rose-700 ring-rose-200' },
            { key: 'caja',        label: 'Caja chica',         icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z', color: 'bg-purple-50 text-purple-700 ring-purple-200' },
            { key: 'fel',         label: 'Facturación FEL',    icon: 'M9 12h6m-6 3h4M5.25 4.5h13.5a.75.75 0 0 1 .75.75v15l-2.625-1.5L14.25 20.25l-2.25-1.5-2.25 1.5-2.625-1.5L4.5 20.25v-15a.75.75 0 0 1 .75-.75Z',                          color: 'bg-slate-50 text-slate-700 ring-slate-200' },
        ],
        articles: [
            // ── Contabilidad ──────────────────────────────────────────────
            { id: 1,  cat: 'contabilidad', title: '¿Qué es un período contable y cómo abrirlo?',
              body: `<p>Un <strong>período contable</strong> es el rango de fechas (normalmente un mes) en el que se registran transacciones. Para abrir uno, ve a <em>Contabilidad → Períodos contables</em> y haz clic en <strong>Nuevo período</strong>. Ingresa la fecha de inicio y fin, y guarda. El período queda en estado <em>Abierto</em>.<br><br>
              <strong>Importante:</strong> solo puedes registrar asientos, facturas y cobros dentro de un período abierto. Si intentas guardar con fecha fuera de período, el sistema mostrará un error.</p>` },
            { id: 2,  cat: 'contabilidad', title: '¿Cómo crear un asiento contable manual?',
              body: `<p>Ve a <em>Contabilidad → Asientos</em> y haz clic en <strong>Nuevo asiento</strong>. Selecciona el diario (por ejemplo, <em>Diario General</em>), la fecha y agrega las líneas de débito y crédito. El asiento debe estar cuadrado (débitos = créditos) antes de guardarlo.<br><br>
              <strong>Nota:</strong> los módulos de Ventas, Compras y Bancos generan asientos automáticamente al confirmar documentos — no es necesario crearlos a mano.</p>` },
            { id: 3,  cat: 'contabilidad', title: '¿Cómo funciona el plan de cuentas?',
              body: `<p>El plan de cuentas es la lista de cuentas contables de tu empresa, organizada por tipo (Activo, Pasivo, Capital, Ingreso, Gasto). Ve a <em>Contabilidad → Plan de cuentas</em> para verlas y editarlas.<br><br>
              Cada cuenta tiene un código numérico (ej. <code>1-1-01</code>) y puede ser <em>cuenta padre</em> (agrupadora) o <em>cuenta detalle</em> (donde se registran movimientos). Solo las cuentas detalle aparecen disponibles al crear asientos.</p>` },
            { id: 4,  cat: 'contabilidad', title: '¿Qué son las cuentas por defecto?',
              body: `<p>Las cuentas por defecto son las cuentas contables que el sistema usa automáticamente para generar asientos en cada módulo. Ve a <em>Contabilidad → Cuentas por defecto</em> para configurarlas.<br><br>
              Ejemplos: <strong>CXC</strong> = cuenta de clientes por cobrar, <strong>CXP</strong> = cuenta de proveedores por pagar, <strong>VENTAS</strong> = ingresos por ventas, <strong>ITBMS_POR_PAGAR</strong> = impuesto ITBMS a pagar.<br><br>
              Si una cuenta por defecto no está configurada, el sistema no podrá generar el asiento automático y mostrará un error.</p>` },
            { id: 5,  cat: 'contabilidad', title: '¿Cómo hacer el cierre contable?',
              body: `<p>El cierre contable transfiere los saldos de cuentas de resultado (ingresos y gastos) a la cuenta de utilidad/pérdida del ejercicio. Ve a <em>Contabilidad → Cierre contable</em> y selecciona el período a cerrar.<br><br>
              <strong>Requisitos previos:</strong><br>
              &bull; El período debe estar abierto.<br>
              &bull; Todos los documentos del período deben estar confirmados.<br>
              &bull; Revisar que el Balance General cuadre antes de cerrar.<br><br>
              Una vez cerrado, el período no permite nuevos registros.</p>` },

            // ── CxC ───────────────────────────────────────────────────────
            { id: 6,  cat: 'cxc', title: '¿Cómo registrar un cliente?',
              body: `<p>Ve a <em>Cuentas por Cobrar → Clientes</em> y haz clic en <strong>Nuevo cliente</strong>. Completa el nombre, RUC/cédula, correo y teléfono. También puedes indicar la cuenta contable que se usará para las facturas de este cliente (si es diferente a la cuenta CXC por defecto).<br><br>
              El mismo contacto puede ser cliente y proveedor al mismo tiempo.</p>` },
            { id: 7,  cat: 'cxc', title: '¿Cómo crear una factura de cobro (CxC)?',
              body: `<p>Ve a <em>Cuentas por Cobrar → Facturas</em> y haz clic en <strong>Nueva factura</strong>. Selecciona el cliente, la fecha, el diario y agrega las líneas con descripción, cantidad, precio e ITBMS.<br><br>
              Al confirmar la factura, el sistema genera automáticamente el asiento contable (débito CXC, crédito Ventas e ITBMS). La factura queda en estado <em>Pendiente</em> hasta recibir el cobro.</p>` },
            { id: 8,  cat: 'cxc', title: '¿Cómo registrar un cobro?',
              body: `<p>Ve a <em>Cuentas por Cobrar → Cobros</em> y haz clic en <strong>Nuevo cobro</strong>. Selecciona el cliente, la fecha, el monto y la cuenta bancaria o de caja donde se recibe el dinero. Luego, en la sección <em>Documentos a aplicar</em>, elige la(s) factura(s) que cancela este cobro.<br><br>
              Al confirmar, el sistema registra el asiento (débito Banco/Caja, crédito CXC) y actualiza el saldo pendiente de la factura.</p>` },
            { id: 9,  cat: 'cxc', title: '¿Qué es una nota de crédito o débito?',
              body: `<p>Una <strong>nota de crédito</strong> reduce el saldo de una factura (por devoluciones, descuentos o errores). Una <strong>nota de débito</strong> lo aumenta.<br><br>
              Ve a <em>Cuentas por Cobrar → Notas crédito/débito</em> para crearlas. Al confirmar, se genera el asiento inverso y se puede aplicar contra la factura original.</p>` },
            { id: 10, cat: 'cxc', title: '¿Cómo ver la antigüedad de saldos?',
              body: `<p>Ve a <em>Cuentas por Cobrar → Antigüedad de saldos</em>. Este reporte muestra cuánto debe cada cliente agrupado por rango de días vencidos (0-30, 31-60, 61-90, +90 días). Útil para gestión de cobros y provisiones de cuentas incobrables.</p>` },

            // ── CxP ───────────────────────────────────────────────────────
            { id: 11, cat: 'cxp', title: '¿Cómo registrar un proveedor?',
              body: `<p>Ve a <em>Cuentas por Pagar → Proveedores</em> y haz clic en <strong>Nuevo proveedor</strong>. Ingresa nombre, RUC, correo y condiciones de pago. El mismo contacto puede ser cliente y proveedor.</p>` },
            { id: 12, cat: 'cxp', title: '¿Cómo registrar una factura de proveedor?',
              body: `<p>Ve a <em>Cuentas por Pagar → Facturas por pagar</em> y haz clic en <strong>Nueva factura</strong>. Selecciona el proveedor, ingresa el número de factura del proveedor, la fecha y las líneas de gasto o producto.<br><br>
              Al confirmar, el sistema genera el asiento (débito Gasto/Compra e ITBMS Crédito, crédito CXP).</p>` },
            { id: 13, cat: 'cxp', title: '¿Cómo registrar un pago a proveedor?',
              body: `<p>Ve a <em>Cuentas por Pagar → Pagos</em> y haz clic en <strong>Nuevo pago</strong>. Selecciona el proveedor, la fecha, el monto y la cuenta bancaria de donde sale el dinero. En <em>Documentos a aplicar</em>, elige las facturas que cancela este pago.<br><br>
              Al confirmar, el sistema registra el asiento (débito CXP, crédito Banco) y actualiza el saldo de la factura.</p>` },

            // ── Ventas ────────────────────────────────────────────────────
            { id: 14, cat: 'ventas', title: '¿Cómo crear una cotización?',
              body: `<p>Ve a <em>Ventas → Cotizaciones</em> y haz clic en <strong>Nueva cotización</strong>. Selecciona el cliente, ingresa los productos o servicios con precios y cantidades. La cotización no genera asiento contable — es solo una propuesta.<br><br>
              Cuando el cliente aprueba, haz clic en <strong>Convertir a factura</strong> para generar la factura de venta directamente.</p>` },
            { id: 15, cat: 'ventas', title: '¿Cómo emitir una factura de venta?',
              body: `<p>Ve a <em>Ventas → Facturas de venta</em> y haz clic en <strong>Nueva factura</strong>. Puedes crearla desde cero o convertirla desde una cotización. Agrega los ítems, verifica el ITBMS y confirma.<br><br>
              Al confirmar, el sistema genera el asiento contable automáticamente y la factura queda disponible para cobro.</p>` },
            { id: 16, cat: 'ventas', title: '¿Cómo emitir un recibo de cobro?',
              body: `<p>Ve a <em>Ventas → Cobros / Recibos</em> y haz clic en <strong>Nuevo recibo</strong>. Selecciona el cliente y el monto recibido. En la sección de documentos, aplica el cobro a una o varias facturas pendientes.<br><br>
              El recibo genera el asiento (débito Caja/Banco, crédito CXC) y descarga el saldo de las facturas seleccionadas.</p>` },
            { id: 17, cat: 'ventas', title: '¿Cómo crear una nota de crédito de venta?',
              body: `<p>Ve a <em>Ventas → Notas de crédito</em>. Puedes crearla desde una factura existente (botón <strong>Nota de crédito</strong> en el detalle de la factura) o desde cero.<br><br>
              Indica el motivo (devolución, descuento, corrección) y los ítems o monto a acreditar. Al confirmar, el sistema invierte el asiento y reduce el saldo pendiente del cliente.</p>` },

            // ── Compras ───────────────────────────────────────────────────
            { id: 18, cat: 'compras', title: '¿Cómo crear una orden de compra?',
              body: `<p>Ve a <em>Compras → Órdenes de compra</em> y haz clic en <strong>Nueva orden</strong>. Selecciona el proveedor y agrega los ítems que deseas comprar con cantidades y precios estimados.<br><br>
              La orden de compra no genera asiento — es un documento de autorización interna. Al recibir la mercancía, se convierte en factura de proveedor.</p>` },
            { id: 19, cat: 'compras', title: '¿Qué son los gastos directos?',
              body: `<p>Los gastos directos son egresos que no provienen de una factura de proveedor formal — por ejemplo, servicios públicos, taxis, papelería pagada en efectivo. Ve a <em>Compras → Gastos directos</em> para registrarlos.<br><br>
              Al confirmar un gasto directo, el sistema genera el asiento contable (débito Gasto, crédito Caja o Banco según el método de pago).</p>` },

            // ── Bancos ────────────────────────────────────────────────────
            { id: 20, cat: 'bancos', title: '¿Cómo registrar una cuenta bancaria?',
              body: `<p>Ve a <em>Bancos → Cuentas bancarias</em> y haz clic en <strong>Nueva cuenta</strong>. Selecciona el banco, ingresa el número de cuenta, el tipo (corriente o ahorro) y la cuenta contable asociada en el plan de cuentas. El saldo inicial se puede ingresar al momento de crear la cuenta.</p>` },
            { id: 21, cat: 'bancos', title: '¿Cómo registrar un movimiento bancario manual?',
              body: `<p>Ve a <em>Bancos → Movimientos</em> y haz clic en <strong>Nuevo movimiento</strong>. Selecciona la cuenta bancaria, la fecha, el tipo (entrada o salida), el monto y la cuenta contable de contrapartida.<br><br>
              Úsalo para registrar comisiones bancarias, intereses o cualquier cargo que no venga de otro módulo.</p>` },
            { id: 22, cat: 'bancos', title: '¿Cómo hacer una transferencia entre cuentas bancarias?',
              body: `<p>Ve a <em>Bancos → Transferencias</em> y haz clic en <strong>Nueva transferencia</strong>. Selecciona la cuenta origen, la cuenta destino, la fecha y el monto. El sistema crea automáticamente el egreso en la cuenta origen y el ingreso en la cuenta destino, con sus respectivos asientos.</p>` },
            { id: 23, cat: 'bancos', title: '¿Cómo conciliar una cuenta bancaria?',
              body: `<p>La conciliación bancaria compara los movimientos del sistema contra el estado de cuenta del banco. Ve a <em>Bancos → Conciliaciones</em>, selecciona la cuenta y la fecha de corte.<br><br>
              El sistema muestra los movimientos del sistema en ese período. Marca los que aparecen en tu estado bancario como <em>conciliados</em>. Los que no coinciden quedan pendientes para investigar (puede ser un error de fecha, un depósito en tránsito, etc.).</p>` },
            { id: 24, cat: 'bancos', title: '¿Cómo registrar un cheque emitido?',
              body: `<p>Ve a <em>Bancos → Cheques</em> y haz clic en <strong>Nuevo cheque</strong>. Ingresa el número de cheque, la cuenta bancaria, el beneficiario, la fecha y el monto. El cheque se puede asociar a un pago de proveedor ya existente.<br><br>
              Al confirmar, el sistema registra el egreso bancario y actualiza el saldo disponible de la cuenta.</p>` },
            { id: 25, cat: 'bancos', title: '¿Cómo registrar un depósito bancario?',
              body: `<p>Ve a <em>Bancos → Depósitos</em> y haz clic en <strong>Nuevo depósito</strong>. Selecciona la cuenta bancaria destino, la fecha, el monto y el origen del dinero (puede vincularse a cobros o recibos de clientes).<br><br>
              Al confirmar, el sistema actualiza el saldo de la cuenta bancaria y genera el asiento correspondiente.</p>` },

            // ── Inventario ────────────────────────────────────────────────
            { id: 26, cat: 'inventario', title: '¿Cómo crear un almacén?',
              body: `<p>Ve a <em>Inventario → Almacenes</em> y haz clic en <strong>Nuevo almacén</strong>. Ingresa el nombre y la ubicación (bodega principal, punto de venta, etc.). Los almacenes son los contenedores donde se registra el stock de productos.</p>` },
            { id: 27, cat: 'inventario', title: '¿Cómo registrar un movimiento de inventario?',
              body: `<p>Ve a <em>Inventario → Movimientos</em> y haz clic en <strong>Nuevo movimiento</strong>. Selecciona el tipo (entrada, salida o ajuste), el almacén, el producto y la cantidad.<br><br>
              <strong>Entradas:</strong> aumentan el stock (recepciones de compra, ajuste positivo).<br>
              <strong>Salidas:</strong> reducen el stock (ventas, consumo, merma).<br>
              <strong>Ajustes:</strong> corrigen diferencias detectadas en inventario físico.</p>` },
            { id: 28, cat: 'inventario', title: '¿Cómo hacer una transferencia entre almacenes?',
              body: `<p>Ve a <em>Inventario → Transferencias</em> y haz clic en <strong>Nueva transferencia</strong>. Selecciona el almacén origen, el almacén destino, los productos y las cantidades. Al confirmar, el stock se reduce en el origen y se suma en el destino sin afectar el costo total.</p>` },
            { id: 29, cat: 'inventario', title: '¿Qué es el kardex y cómo consultarlo?',
              body: `<p>El <strong>kardex</strong> es el historial cronológico de entradas y salidas de un producto, con saldo acumulado. Ve a <em>Inventario → Kardex</em>, selecciona el producto (y opcionalmente el almacén y rango de fechas) para ver cada movimiento con su impacto en el stock.<br><br>
              Es la herramienta principal para auditar diferencias de inventario.</p>` },

            // ── Activos Fijos ──────────────────────────────────────────────
            { id: 30, cat: 'activos', title: '¿Cómo registrar un activo fijo?',
              body: `<p>Ve a <em>Activos Fijos → Registro de activos</em> y haz clic en <strong>Nuevo activo</strong>. Ingresa el nombre, la categoría (determina la vida útil y método de depreciación), la fecha de adquisición, el costo y la cuenta contable.<br><br>
              La categoría define automáticamente el porcentaje y método de depreciación (línea recta es el más común en Panamá).</p>` },
            { id: 31, cat: 'activos', title: '¿Cómo se calcula y registra la depreciación?',
              body: `<p>La depreciación se calcula automáticamente según la categoría y la fecha de adquisición del activo. El sistema genera el asiento mensual de depreciación (débito Gasto por Depreciación, crédito Depreciación Acumulada).<br><br>
              Para ver el detalle de depreciación de un activo, entra al registro del activo y revisa la pestaña <em>Depreciaciones</em>.</p>` },
            { id: 32, cat: 'activos', title: '¿Cómo dar de baja un activo fijo?',
              body: `<p>Abre el activo desde <em>Activos Fijos → Registro de activos</em> y haz clic en <strong>Registrar baja</strong>. Indica la fecha, el motivo (venta, obsolescencia, siniestro) y el valor de rescate si aplica.<br><br>
              El sistema genera el asiento de baja (cancela costo y depreciación acumulada, reconoce ganancia o pérdida en la disposición).</p>` },

            // ── Caja ──────────────────────────────────────────────────────
            { id: 33, cat: 'caja', title: '¿Cómo abrir un arqueo de caja chica?',
              body: `<p>Ve a <em>Caja chica → Arqueos</em> y haz clic en <strong>Nuevo arqueo</strong>. Ingresa el monto inicial del fondo y la fecha de apertura. A partir de ese momento puedes registrar los gastos menores pagados con ese fondo.<br><br>
              Al agotar el fondo o al cierre del período, cierra el arqueo y el sistema genera el asiento de reposición de caja.</p>` },
            { id: 34, cat: 'caja', title: '¿Cómo registrar un gasto de caja chica?',
              body: `<p>Dentro de un arqueo abierto, haz clic en <strong>Agregar movimiento</strong>. Ingresa la descripción del gasto, el monto, la cuenta contable del gasto y adjunta el comprobante si tienes escaneado el recibo.<br><br>
              El saldo disponible del arqueo se reduce automáticamente con cada gasto registrado.</p>` },

            // ── FEL ───────────────────────────────────────────────────────
            { id: 35, cat: 'fel', title: '¿Qué es la Facturación Electrónica FEL?',
              body: `<p>La Facturación Electrónica (FEL) es el sistema de la DGI de Panamá para emitir facturas con validez fiscal digital a través de un Proveedor Autorizado de Certificación (PAC). eTax2 está integrado con <strong>The Factory HKA</strong> como PAC.<br><br>
              Las facturas FEL tienen un código QR, código CUFE y quedan registradas en la DGI en tiempo real.</p>` },
            { id: 36, cat: 'fel', title: '¿Cómo configurar el módulo FEL?',
              body: `<p>Ve a <em>Facturación FEL → Configuración</em>. Necesitas:<br>
              &bull; <strong>Token WS</strong> y <strong>contraseña WS</strong> (provisto por HKA).<br>
              &bull; <strong>Código de sucursal</strong> y <strong>punto de facturación</strong> (asignados por HKA).<br>
              &bull; RUC y DV de tu empresa.<br><br>
              Guarda la configuración y haz una emisión de prueba para verificar la conectividad con HKA antes de emitir documentos reales.</p>` },
            { id: 37, cat: 'fel', title: '¿Cómo emitir una factura electrónica?',
              body: `<p>Ve a <em>Facturación FEL → Nueva factura</em>. El proceso es similar a una factura normal: selecciona el cliente, agrega los ítems y confirma.<br><br>
              Al confirmar, eTax2 envía la factura a HKA en XML, HKA la valida y la reporta a la DGI, y devuelve el CUFE y el código QR. Si la DGI rechaza la factura, el sistema muestra el código de error y la descripción para que puedas corregir y reintentar.</p>` },

            // ── Estados de los documentos ─────────────────────────────────
            { id: 38, cat: 'cxc', title: '¿Qué significan los estados de una factura (Pendiente, Parcial, Pagado, Anulado)?',
              body: `<p>El <strong>estado</strong> de una factura indica en qué punto del cobro (o pago, en CxP) se encuentra. Lo calcula el sistema automáticamente según el saldo; tú no lo eliges al registrarla.</p>
              <p>&bull; <strong>Pendiente:</strong> es el estado con el que <strong>nace toda factura al registrarla</strong>. Significa que aún no se ha cobrado nada: el saldo es igual al total.<br>
              &bull; <strong>Parcial:</strong> se recibió un cobro (o se aplicó una nota de crédito) que abonó una parte, pero todavía queda saldo por cobrar.<br>
              &bull; <strong>Pagado:</strong> el saldo llegó a cero; la factura quedó totalmente cobrada.<br>
              &bull; <strong>Anulado:</strong> la factura se dejó sin efecto. Su asiento contable se reversa y deja de contar en los saldos y reportes.</p>
              <p>Una factura va pasando sola de <em>Pendiente → Parcial → Pagado</em> a medida que registras cobros. <strong>Anulado</strong> es el único estado que se aplica manualmente, al anular el documento. Estos mismos estados aplican a las facturas de <em>Cuentas por Pagar</em>, solo que en función de los pagos a proveedores.</p>` },
        ],
        get filteredArticles() {
            const q = this.search.trim().toLowerCase();
            const cat = this.activeCategory;
            return this.articles.filter(a =>
                (cat ? a.cat === cat : true) &&
                (q ? (a.title.toLowerCase().includes(q) || a.body.toLowerCase().includes(q)) : true)
            );
        },
        get groupedFiltered() {
            const groups = {};
            this.filteredArticles.forEach(a => {
                if (!groups[a.cat]) groups[a.cat] = [];
                groups[a.cat].push(a);
            });
            return groups;
        },
        countByCategory(key) {
            const q = this.search.trim().toLowerCase();
            return this.articles.filter(a =>
                a.cat === key &&
                (q ? (a.title.toLowerCase().includes(q) || a.body.toLowerCase().includes(q)) : true)
            ).length;
        },
        selectCategory(key) {
            this.activeCategory = this.activeCategory === key ? null : key;
            this.activeArticle = null;
            this.$nextTick(() => {
                const el = document.getElementById('articles-section');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },
        toggleArticle(id) {
            this.activeArticle = this.activeArticle === id ? null : id;
        }
    }"
    class="min-h-screen bg-slate-50"
>

    {{-- ── Hero ──────────────────────────────────────────────────────────── --}}
    <div class="bg-[#0d2d5e] px-4 pb-10 pt-12 text-center">
        <p class="mb-1 text-sm font-medium uppercase tracking-widest text-blue-300">eTax2</p>
        <h1 class="mb-3 text-3xl font-bold text-white">Centro de Ayuda</h1>
        <p class="mb-8 text-base text-blue-200">¿En qué podemos ayudarte?</p>
        <div class="relative mx-auto max-w-xl">
            <svg class="pointer-events-none absolute left-4 top-3.5 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
            </svg>
            <input
                x-model.debounce.200ms="search"
                @input="activeCategory = null; activeArticle = null"
                type="search"
                placeholder="Buscar artículos de ayuda..."
                class="h-12 w-full rounded-xl border-0 pl-12 pr-4 text-sm text-slate-900 shadow-lg focus:ring-2 focus:ring-blue-400"
            >
        </div>
        <p x-show="search !== ''" class="mt-3 text-sm text-blue-300">
            <span x-text="filteredArticles.length"></span> resultado(s) para "<span x-text="search" class="font-semibold"></span>"
        </p>
    </div>

    {{-- ── Categorías ────────────────────────────────────────────────────── --}}
    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <template x-for="cat in categories" :key="cat.key">
                <button
                    type="button"
                    @click="selectCategory(cat.key)"
                    :class="[
                        'group flex flex-col items-center gap-2 rounded-xl border-2 p-4 text-center transition-all',
                        activeCategory === cat.key
                            ? 'border-[#0d2d5e] bg-[#0d2d5e] text-white shadow-md'
                            : 'border-slate-200 bg-white hover:border-[#0d2d5e]/40 hover:shadow-sm'
                    ]"
                >
                    <div
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-lg ring-1',
                            activeCategory === cat.key ? 'bg-white/20 ring-white/30' : cat.color
                        ]"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="cat.icon" />
                        </svg>
                    </div>
                    <span class="text-xs font-semibold leading-tight" x-text="cat.label"></span>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-xs font-bold',
                            activeCategory === cat.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'
                        ]"
                        x-text="countByCategory(cat.key)"
                    ></span>
                </button>
            </template>
        </div>
    </div>

    {{-- ── Artículos ─────────────────────────────────────────────────────── --}}
    <div id="articles-section" class="mx-auto max-w-3xl px-4 pb-16">

        {{-- Sin resultados --}}
        <div x-show="filteredArticles.length === 0" class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center">
            <svg class="mx-auto mb-3 h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
            </svg>
            <p class="font-medium text-slate-500">No se encontraron artículos</p>
            <p class="mt-1 text-sm text-slate-400">Intenta con otra búsqueda o selecciona una categoría diferente.</p>
        </div>

        {{-- Artículos agrupados por categoría --}}
        <template x-for="cat in categories" :key="'section-' + cat.key">
            <div x-show="groupedFiltered[cat.key] && groupedFiltered[cat.key].length > 0" class="mb-6">

                {{-- Encabezado de sección --}}
                <div class="mb-3 flex items-center gap-2">
                    <div :class="['flex h-7 w-7 items-center justify-center rounded-md ring-1', cat.color]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="cat.icon" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500" x-text="cat.label"></h2>
                </div>

                {{-- Acordeón de artículos --}}
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <template x-for="(article, idx) in groupedFiltered[cat.key]" :key="article.id">
                        <div :class="idx > 0 ? 'border-t border-slate-100' : ''">
                            <button
                                type="button"
                                @click="toggleArticle(article.id)"
                                class="flex w-full items-center gap-3 px-5 py-4 text-left hover:bg-slate-50"
                            >
                                <svg
                                    :class="activeArticle === article.id ? 'rotate-90 text-[#0d2d5e]' : 'text-slate-400'"
                                    class="h-4 w-4 shrink-0 transition-transform duration-150"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                                </svg>
                                <span
                                    class="text-sm font-medium"
                                    :class="activeArticle === article.id ? 'text-[#0d2d5e]' : 'text-slate-800'"
                                    x-text="article.title"
                                ></span>
                            </button>
                            <div
                                x-show="activeArticle === article.id"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="border-t border-slate-100 bg-slate-50 px-5 py-4 text-sm leading-relaxed text-slate-700"
                                x-html="article.body"
                            ></div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- ── Footer soporte ────────────────────────────────────────────────── --}}
    <div class="border-t border-slate-200 bg-white px-4 py-10 text-center">
        <p class="text-sm font-medium text-slate-700">¿No encontraste lo que buscabas?</p>
        <p class="mt-1 text-sm text-slate-500">Escríbenos a <a href="mailto:CornelioRoyer@gmail.com" class="font-medium text-[#0d2d5e] hover:underline">CornelioRoyer@gmail.com</a></p>
    </div>

</div>
</x-app-layout>
