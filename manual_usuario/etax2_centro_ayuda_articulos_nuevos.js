/* =============================================================================
   eTax2 — Centro de Ayuda: contenido adicional listo para pegar
   Archivo destino: resources/views/admin/ayuda/index.blade.php
   -----------------------------------------------------------------------------
   Cómo usarlo:
   1) Agrega los objetos de CATEGORÍAS NUEVAS al final del arreglo `categories`.
   2) Agrega los objetos de ARTÍCULOS NUEVOS al final del arreglo `articles`.
   Los IDs continúan desde 38 para no chocar con los 37 artículos existentes.
   El formato (key/cat/title/body con HTML) es idéntico al que ya usa la vista.
   ============================================================================= */


/* ───────────────────────── 1) CATEGORÍAS NUEVAS ─────────────────────────────
   Pegar dentro del arreglo `categories: [ ... ]`                               */

            { key: 'primeros-pasos', label: 'Primeros pasos', icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', color: 'bg-sky-50 text-sky-700 ring-sky-200' },
            { key: 'reportes',       label: 'Reportes',       icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z', color: 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-200' },
            { key: 'admin',          label: 'Administración', icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z', color: 'bg-gray-50 text-gray-700 ring-gray-200' },
            { key: 'ia',             label: 'Documentos IA',  icon: 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z', color: 'bg-violet-50 text-violet-700 ring-violet-200' },


/* ───────────────────────── 2) ARTÍCULOS NUEVOS ──────────────────────────────
   Pegar dentro del arreglo `articles: [ ... ]`                                 */

            // ── Primeros pasos ────────────────────────────────────────────
            { id: 38, cat: 'primeros-pasos', title: '¿Cómo creo mi cuenta e inicio sesión?',
              body: `<p>Entra a <strong>etax2.com</strong>. Si no tienes cuenta, haz clic en <em>«Regístrate aquí»</em>: puedes registrarte con <strong>correo y contraseña</strong> (no necesitas Gmail) o con <strong>«Entrar con Google»</strong>. Revisa tu correo y confirma la cuenta.<br><br>
              Para entrar, escribe tu correo y contraseña y haz clic en <strong>Log in</strong>. Marca <em>«Remember me»</em> para mantener la sesión abierta en tu equipo.</p>` },
            { id: 39, cat: 'primeros-pasos', title: 'Olvidé mi contraseña, ¿cómo la recupero?',
              body: `<p>En la pantalla de inicio de sesión haz clic en <em>«Forgot your password?»</em>, ingresa tu correo y sigue el enlace que recibirás para crear una nueva contraseña.</p>` },
            { id: 40, cat: 'primeros-pasos', title: '¿Cómo cambio de empresa (compañía activa)?',
              body: `<p>eTax2 es multiempresa. En la parte superior izquierda aparece el nombre de la compañía activa y su RUC. Haz clic ahí para desplegar la lista de compañías a las que tienes acceso y selecciona otra. <strong>Todo el sistema cambiará a esa empresa</strong>: los reportes, documentos y saldos que verás corresponden siempre a la compañía activa.</p>` },
            { id: 41, cat: 'primeros-pasos', title: '¿Cómo me muevo por el sistema?',
              body: `<p>El <strong>menú lateral izquierdo</strong> contiene todos los módulos; haz clic en uno para desplegar sus opciones. Usa el buscador <em>«Buscar opción»</em> para llegar rápido a una pantalla escribiendo su nombre.<br><br>
              En la <strong>barra superior</strong> están el selector de compañía, la fecha, las notificaciones (campana) y tu perfil. El botón <em>«Colapsar menú»</em> oculta el menú para ganar espacio.</p>` },
            { id: 42, cat: 'primeros-pasos', title: 'No veo un módulo en el menú, ¿por qué?',
              body: `<p>Cada usuario tiene permisos asignados <strong>por compañía</strong>. Si no ves una opción, es porque tu usuario no tiene ese permiso en la compañía activa. Solicítalo al administrador de tu empresa (lo configura en <em>Usuarios → Accesos por compañía</em>).</p>` },
            { id: 43, cat: 'primeros-pasos', title: '¿Cómo actualizo mi perfil o contraseña?',
              body: `<p>Haz clic en tu nombre en la esquina superior derecha para abrir tu <strong>perfil</strong>. Desde ahí puedes actualizar tu información personal, cambiar tu contraseña o eliminar tu cuenta.</p>` },
            { id: 44, cat: 'primeros-pasos', title: '¿Qué es el Dashboard (Estado Financiero)?',
              body: `<p>Es la pantalla de inicio. Muestra el <strong>Estado Financiero</strong> de la compañía activa: Activos, Pasivos, Patrimonio y Utilidad Neta del año, con gráficos. Puedes <strong>Exportar PDF</strong> o <strong>Imprimir</strong>.<br><br>
              Si la empresa es nueva y no tiene asientos posteados, verás valores en B/. 0.00; los datos se llenan a medida que registras y posteas operaciones.</p>` },

            // ── Reportes ──────────────────────────────────────────────────
            { id: 45, cat: 'reportes', title: 'Balance de Situación',
              body: `<p>Ve a <em>Reportes → Balance de Situación</em>. Muestra los <strong>activos, pasivos y patrimonio</strong> de la empresa a una fecha determinada. Permite imprimir y exportar.</p>` },
            { id: 46, cat: 'reportes', title: 'Estado de Resultado',
              body: `<p>Ve a <em>Reportes → Estado de Resultado</em>. Presenta los <strong>ingresos, costos y gastos</strong> de un período y la <strong>utilidad o pérdida</strong> resultante.</p>` },
            { id: 47, cat: 'reportes', title: 'Comparativo Mensual',
              body: `<p>Ve a <em>Reportes → Comparativo Mensual</em>. Muestra la evolución <strong>mes a mes</strong> de las cuentas, útil para detectar variaciones y tendencias.</p>` },
            { id: 48, cat: 'reportes', title: 'Flujo de Efectivo',
              body: `<p>Ve a <em>Reportes → Flujo de Efectivo</em>. Resume las <strong>entradas y salidas de dinero</strong> del período, para entender la liquidez del negocio.</p>` },
            { id: 49, cat: 'reportes', title: 'Liquidación de ITBMS',
              body: `<p>Ve a <em>Reportes → Liquidación ITBMS</em>. Muestra, por año y en Balboas: <strong>Ventas gravadas</strong>, <strong>ITBMS cobrado</strong>, <strong>Compras gravadas</strong>, <strong>ITBMS crédito</strong> e <strong>ITBMS a pagar</strong>. Es la base para preparar tu declaración de ITBMS ante la DGI.</p>` },

            // ── Contabilidad (complementos) ───────────────────────────────
            { id: 50, cat: 'contabilidad', title: '¿Qué son los diarios contables?',
              body: `<p>Los <strong>diarios</strong> clasifican los asientos por origen (Diario General, Ventas, Compras, Bancos, etc.). Ve a <em>Contabilidad → Diarios</em> para crearlos y activarlos o desactivarlos. Cada documento se registra en el diario que le corresponde.</p>` },
            { id: 51, cat: 'contabilidad', title: 'Postear y anular un asiento',
              body: `<p>En el detalle de un asiento, <strong>Postear</strong> lo confirma y refleja en los saldos contables; <strong>Anular</strong> revierte un asiento ya posteado. Recuerda que un asiento debe estar cuadrado (débitos = créditos) para guardarse.</p>` },

            // ── CxC / CxP (complementos) ──────────────────────────────────
            { id: 52, cat: 'cxc', title: '¿Cómo consulto el estado de cuenta de un cliente?',
              body: `<p>Ve a <em>Cuentas por Cobrar → Estado de cuenta</em>, selecciona el cliente y el rango de fechas. Verás el detalle de facturas, cobros y notas con el saldo resultante. Ideal para enviárselo al cliente.</p>` },
            { id: 53, cat: 'cxp', title: 'Antigüedad de saldos y estado de cuenta de proveedores',
              body: `<p>En <em>Cuentas por Pagar</em> tienes <strong>Antigüedad de saldos</strong> (lo que debes agrupado por días vencidos) y <strong>Estado de cuenta</strong> por proveedor (detalle de facturas, pagos y notas con saldo). Te ayudan a planificar los pagos.</p>` },

            // ── Ventas / Compras (complementos) ───────────────────────────
            { id: 54, cat: 'ventas', title: '¿Cómo registro vendedores?',
              body: `<p>Ve a <em>Ventas → Vendedores</em> y haz clic en <strong>Nuevo vendedor</strong>. Registrar a tu equipo permite asociarlo a las facturas y medir su desempeño. Puedes editarlos y activarlos/desactivarlos.</p>` },
            { id: 55, cat: 'compras', title: '¿Cómo recibo mercancía de una orden de compra?',
              body: `<p>Abre la orden en <em>Compras → Órdenes de compra</em>. Tras <strong>Aprobarla</strong>, registra una <strong>Recepción</strong> con las cantidades recibidas (puede ser parcial). Cuando llegue la factura del proveedor, usa <strong>Facturar</strong> para convertir la orden en factura de compra.</p>` },

            // ── Inventario (complemento) ──────────────────────────────────
            { id: 56, cat: 'inventario', title: '¿Cómo creo un producto y su lista de precios?',
              body: `<p>Ve a <em>Inventario → Productos</em> y haz clic en <strong>Nuevo ítem</strong>. Indica código, nombre, tipo (producto o servicio) y cuentas asociadas. En la ficha del ítem puedes mantener una <strong>lista de precios</strong> y activarlo o desactivarlo.</p>` },

            // ── Activos Fijos (complemento) ───────────────────────────────
            { id: 57, cat: 'activos', title: 'Categorías, ubicaciones y revaluación de activos',
              body: `<p>Las <strong>categorías</strong> (en <em>Activos Fijos → Categorías</em>) definen la vida útil y el método de depreciación; las <strong>ubicaciones</strong> indican dónde está físicamente cada activo. Desde la ficha de un activo puedes registrar una <strong>revaluación</strong> para ajustar su valor.</p>` },

            // ── Documentos IA ─────────────────────────────────────────────
            { id: 58, cat: 'ia', title: '¿Qué es el módulo Documentos IA?',
              body: `<p>Es un asistente con inteligencia artificial para capturar documentos (por ejemplo, facturas recibidas) y dejarlos en la bandeja <em>«Por registrar»</em>. Puedes conectar <strong>Fuentes (Drive)</strong> para que el sistema procese documentos automáticamente y agilice la entrada de datos.</p>` },

            // ── Administración ────────────────────────────────────────────
            { id: 59, cat: 'admin', title: '¿Cómo agrego una nueva compañía?',
              body: `<p>Ve a <em>Compañías → Nueva compañía</em> e ingresa los datos fiscales (RUC, DV, nombre, etc.). Podrás administrar varias empresas con un mismo usuario y cambiar entre ellas desde el selector de la barra superior.</p>` },
            { id: 60, cat: 'admin', title: '¿Cómo doy acceso a un usuario y configuro sus permisos?',
              body: `<p>Ve a <em>Usuarios → Accesos por compañía</em>. Ahí defines qué usuarios entran a cada compañía y, para cada uno, editas los permisos módulo por módulo:<br><br>
              &bull; <strong>Ver:</strong> puede consultar la información del módulo.<br>
              &bull; <strong>Gestionar:</strong> puede crear, editar, confirmar y anular documentos.<br><br>
              Así, por ejemplo, un vendedor solo ve Ventas y un contador tiene acceso completo a Contabilidad.</p>` },
            { id: 61, cat: 'admin', title: '¿Qué se configura en «Configuración general»?',
              body: `<p>Ve a <em>Configuración</em> para administrar los catálogos base que usan los demás módulos: <strong>Sucursales, Departamentos, Centros de costo, Proyectos, Monedas y Tasas de cambio, y Retenciones</strong>. Cada catálogo se administra con el botón <strong>Agregar</strong> y puede editarse o desactivarse.</p>` },
            { id: 62, cat: 'admin', title: '¿Para qué sirven las Zonas?',
              body: `<p>Las <strong>Zonas</strong> (en <em>Compañías → Zonas</em>) son un catálogo geográfico o comercial para clasificar clientes y operaciones, útil para segmentar reportes y rutas de venta.</p>` },
