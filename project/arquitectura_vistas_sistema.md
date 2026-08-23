ESPECIFICACIÓN ARQUITECTÓNICA Y DISEÑO UI/UX DE VISTAS Y MÓDULOS

Sistema: Automatización de Cobranza y Control de Despachos — Distribuidora de Agua Mineral

Rol: Arquitecto de Software Senior & Diseñador UI/UX

Estado: Documento de Especificación Definitiva (Versión Actualizada con Catálogos Dinámicos)

Versión: 2.0.0

GUÍA DE ESTILO VISUAL Y PALETA DE COLORES OBLIGATORIA

Para garantizar la coherencia tipográfica y cromática en los módulos y vistas del sistema, se aplica estrictamente la siguiente distribución de color:

Color Primario / Botones de Acción / Highlights: #2077F9 (Azul Eléctrico)

Dark Mode / Textos Principales / Headers / Sidebar / Tablas Estructurales: #00001C (Oscuro Profundo)

Backgrounds / Tarjetas de KPI / Áreas de Trabajo / Dropzones: #F2F2F2 (Gris Claro)

1. MÓDULO 1: DASHBOARD GENERAL (views/dashboard.php)

1.1 Propósito Operativo y Workflow Asociado

Propósito Operativo: Servir como centro de mando unificado para el monitoreo en tiempo real de la operación diaria. Permite visualizar el resumen de despachos, la cobranza ejecutada, el balance general de botellas comercializadas por marca (según catálogo dinámico marcas_agua) y el estado global de notificaciones y alertas pendientes.

Workflows Asociados: Consolida y supervisa la ejecución transversal de WF-01 (Ingesta), WF-02 (Conciliación y Arrastre), WF-03 (Cobranza por WhatsApp) y WF-04 (Gestión de Pagos).

1.2 Layout y Estructura Visual

Header Superior:

Título Principal: "Panel de Control y Supervisión General" (Tipografía #00001C).

Filtros de Cabecera:

Control de Fecha: <input type="date"> (Por defecto: CURRENT_DATE, Formato: YYYY-MM-DD).

Filtro por Despachador: <input type="text"> o <select> de texto dinámico basado en los valores existentes en despachos.despachador.

Botones de Acción Global:

Botón "Ejecutar Conciliación del Día" (Fondo #2077F9, Texto Blanco).

Botón "Lanzar Cola de Cobranza" (Fondo #2077F9, Texto Blanco).

Botón "Gestionar Catálogos (Precios / Métodos)" (Fondo #00001C, Borde #2077F9, Texto Blanco).

Componentes Principales:

Contenedor de KPIs (Grid de 4 Tarjetas en Parte Superior):

KPI 1: Total Botellas Despachadas Hoy (Desglose dinámico por marcas activas en marcas_agua, ej. La Zenda @ $\$P_{\text{zenda}}$ USD | Los Alpes @ $\$P_{\text{alpes}}$ USD | Total Líquidos).

KPI 2: Deuda Total Recuperable ($ USD) (Suma acumulada de la tabla saldos_pendientes).

KPI 3: Estatus de Cobranza WhatsApp (Mensajes Enviados vs. Mensajes Pendientes).

KPI 4: Alertas de Ambigüedad (Conteo de registros en alertas_revision con resuelto = FALSE).

Tabla Resumen de Despachos de la Jornada: Muestra los últimos despachos procesados con su estatus de pago, forma de pago asociada y notificaciones.

Aplicación de Paleta de Colores:

Barra Lateral (Sidebar) y Header principal: #00001C.

Fondo general de la vista y contenedores de KPI Cards: #F2F2F2.

Botones de acción, indicadores activos y bordes contextuales: #2077F9.

1.3 Detalle de Elementos de Interacción

Elemento UI

Tipo / Estructura

Validaciones y Reglas

Evento JS / Endpoint Consumido

Filtro de Fecha

<input type="date">

max = CURRENT_DATE. No permite fechas futuras.

change $\rightarrow$ Ejecuta app.js recargando los datos de la vista.

Filtro Despachador

<input type="text">

Búsqueda por coincidencia de subcadena contra despachos.despachador.

keyup/change $\rightarrow$ Filtra dinámicamente la tabla local.

Botón 'Ejecutar Conciliación'

<button class="btn-primary">

Deshabilitado si no hay ingesta procesada para la fecha seleccionada.

click $\rightarrow$ Consume POST /api/conciliacion.php.

Botón 'Lanzar Cola Cobranza'

<button class="btn-primary">

Se bloquea si existen alertas en $< 85\%$ sin resolver.

click $\rightarrow$ Consume POST /api/cobranza.php.

Tabla Resumen

<table>

Muestra columnas: ID, Fecha, Cliente, Despachador, Desglose Marcas, Monto ($), Forma Pago, Estado Pago.

Carga inicial vía GET /api/conciliacion.php?action=resumen.

1.4 Manejo de Excepciones y Reglas de Negocio Visibles

Cálculo Financiero Dinámico: El monto total en dólares por despacho se determina consultando los precios vigentes en la tabla marcas_agua:

$$\text{monto\_despacho\_usd} = \sum_{i=1}^{N} \left( \text{cantidad\_botellas}_i \times \text{precio\_usd}_i \right)$$

Alerta Visual de Coincidencias Dudosas: Si la tabla alertas_revision posee registros no resueltos, la KPI Card de Alertas parpadea en color #2077F9 y el botón de lanzamiento de cobranza requiere confirmación explícita o resolución previa en el Módulo de Alertas.

2. MÓDULO 2: INGESTA OCR (views/ingesta_view.php)

2.1 Propósito Operativo y Workflow Asociado

Propósito Operativo: Permitir la carga, inspección previa, validación matemática e importación del archivo estructurado JSON (despachos_diarios.json) generado a partir del OCR de listas manuscritas en papel o capturas digitales de notas enviadas por los choferes, aplicando las tarifas dinámicas registradas en la tabla marcas_agua.

Workflow Asociado: Implementa el frontend del Workflow 01 (WF-01: Ingesta, OCR y Extracción Estructurada de Datos).

2.2 Layout y Estructura Visual

Header Superior:

Título Principal: "Módulo de Ingesta OCR y Carga de Listas Diarias" (Texto #00001C).

Controles de Cabecera:

Selector de Archivo (.json).

Campo de Fecha de Procesamiento (fecha_procesamiento).

Campo opcional para sobrescribir Despachador (si no viene especificado en JSON).

Botón de Acción: "Procesar e Importar Ingesta" (Fondo #2077F9, Texto Blanco).

Componentes Principales:

Contenedor Drag & Drop (Dropzone): Área receptora para arrastrar o examinar el archivo despachos_diarios.json (Fondo #F2F2F2, Borde discontinuo #2077F9).

Panel de Resumen Diario (Métricas Extraídas):

Listas Procesadas vs. Esperadas: Lunes (4 listas); Martes a Viernes (3 listas).

Totales Calculados por Marca: Total Botellas por cada marca registrada en marcas_agua, Total Líquidos Comercializados, Monto Bruto USD calculado según tarifas vigentes.

Tabla Previa de Inspección de Despachos (despachos array):

Permite verificar cada ítem antes de guardarlo en la base de datos.

Aplicación de Paleta de Colores:

Contenedores de carga y fondo de tarjetas: #F2F2F2.

Estructura de tablas, textos y encabezados: #00001C.

Bordes de enfoque y botón de confirmación: #2077F9.

2.3 Detalle de Elementos de Interacción

+-----------------------------------------------------------------------------------+
| LAYOUT VISUAL: views/ingesta_view.php                                              |
+-----------------------------------------------------------------------------------+
| [ Header: Título | DateInput | Despachador Override (Text) ]                      |
|                                                                                   |
| ┌───────────────────────────────────────────────────────────────────────────────┐ |
| │ DROPZONE: Arrastre aquí 'despachos_diarios.json' o haga clic para examinar    │ |
| └───────────────────────────────────────────────────────────────────────────────┘ |
|                                                                                   |
| ┌─────────────────────────┐ ┌─────────────────────────┐ ┌───────────────────────┐ │
| │ Listas Procesadas: 3/3  │ │ Total Zenda: 22         │ │ Total Alpes: 48       │ │
| │                         │ │ (Tarifa: $7.00/bot)     │ │ (Tarifa: $3.00/bot)   │ │
| └─────────────────────────┘ └─────────────────────────┘ └───────────────────────┘ │
|                                                                                   |
| TABLA PREVIA DE IMPORTE:                                                          |
| +----+----------------------+-------+-------+------------+----------------------+ |
| | ID | Cliente Raw / Alias  | Zenda | Alpes | Monto USD  | Estado Declarado     | |
| +----+----------------------+-------+-------+------------+----------------------+ |
| | 1  | EDF TUCURABUA 4-04   |   0   |   1   |   $3.00    | Pendiente            | |
| | 2  | PASTELERIA CHACAO    |   3   |   0   |  $21.00    | Pago Móvil Ref 65638 | |
| +----+----------------------+-------+-------+------------+----------------------+ |
|                                                                                   |
| [ Botón de Acción: "Procesar e Importar Ingesta" (#2077F9) ]                     |
+-----------------------------------------------------------------------------------+


Campos de Formulario:

Input File: <input type="file" accept=".json">. Validado en JS para verificar que cumpla el esquema JSON del WF-01.

Despachador Override: <input type="text" placeholder="Ej. Despachador Chacao">.

Columnas de la Tabla Previa de Ingesta:

id_item (Entero)

zona_edificio (Texto / Null)

unidad_sublocal (Texto / Null)

nombre_cliente_raw (Texto original de la nota)

alias_despacho_consolidado (Texto unificado)

botellas_zenda (Entero $\ge 0$)

botellas_alpes (Entero $\ge 0$)

monto_calculado_usd (Decimal $10,2$ derivado de marcas_agua.precio_usd)

estado_pago_declarado (Enum / Texto de la nota)

requiere_revision_humana (Booleano con Badge visual)

Acciones de Botones y Endpoints:

Botón "Procesar e Importar Ingesta": Al hacer clic, ejecuta app.js enviando el JSON completo a POST /api/ingesta.php.

2.4 Manejo de Excepciones y Reglas de Negocio Visibles

Tarifario Dinámico de Productos: Los precios de los botellones NO son estáticos. Se leen desde la tabla marcas_agua:

$$\text{Precio\_Marca}_k = \text{marcas\_agua.precio\_usd} \quad \text{donde } \text{activo} = \text{TRUE}$$

Regla de Ocultamiento de Marcas Secundarias: Si la nota del chofer contiene una cifra numérica sin especificar marca (ej. 53_______2), el parser asigna automáticamente las cantidades a la marca por defecto (Los Alpes u otra configurada como principal en marcas_agua).

Validación de Ráfaga de Listas: Si el conteo de listas procesadas es menor al esperado según el día de la semana (Lunes < 4, Mar-Vie < 3), el sistema muestra una advertencia en tono #2077F9 indicando que faltan listas por cargar.

3. MÓDULO 3: ALERTAS Y AMBIGÜEDADES (views/alertas_view.php)

3.1 Propósito Operativo y Workflow Asociado

Propósito Operativo: Proveer una bandeja de entrada donde el operador administrativo revise, asocie y resuelva manualmente los despachos cuya coincidencia de nombre (Fuzzy Matching) haya sido inferior al umbral mínimo del 85%, o contenga observaciones dudosas.

Workflow Asociado: Implementa la vista de gestión de excepciones del Workflow 02 (WF-02: Conciliación de Cartera, Arrastre y Segmentación).

3.2 Layout y Estructura Visual

Header Superior:

Título Principal: "Bandeja de Resolución de Alertas y Ambigüedades" (Texto #00001C).

Filtros de Cabecera:

Filtro por Estatus: <select> (Todas, Pendientes, Resueltas).

Filtro por Despachador: <input type="text"> para filtrar los casos según el chofer que generó la lista.

Botón de Acción: "Refrescar Lista de Alertas" (Fondo #2077F9, Texto Blanco).

Componentes Principales:

Tabla Principal de Alertas (alertas_revision): Listado interactivo de casos pendientes por asociar.

Modal Interactivo de Vinculación y Corrección: Ventana emergente que se despliega al presionar "Resolver" en una fila. Contiene un buscador autocompletado contra el catálogo formal de la tabla clientes.

Aplicación de Paleta de Colores:

Cabecera de tablas y estructura de modal: #00001C.

Área de trabajo, tarjetas de detalles y fondos: #F2F2F2.

Botón "Vincular y Resolver", badges de porcentaje y resaltados: #2077F9.

3.3 Detalle de Elementos de Interacción

Columnas de la Tabla de Alertas:

Nombre Columna

Campo en BD

Descripción y Formato UI

ID Alerta

alertas_revision.id

Identificador numérico de la alerta.

Fecha

alertas_revision.fecha

Fecha del despacho en conflicto.

Nombre Raw / Alias

nombre_raw

Texto tal cual fue escrito por el chofer.

Motivo Alerta

motivo

Razón de la bandera (ej. MATCH_AMBIGUO_O_NO_ENCONTRADO).

Similitud (%)

Calculado en PHP

Porcentaje devuelto por similar_text() / Levenshtein.

Candidato Sugerido

Calculado en PHP

Nombre del cliente con el porcentaje más cercano en BD.

Acciones

N/A

Botón "Resolver" (#2077F9) que dispara el modal.

Modal Interactivo de Resolución:

Select/Buscador Autocompletado: Permite buscar por nombre_oficial o nombre_despacho_alias de la tabla clientes.

Botón "Vincular y Confirmar": Ejecuta la función JavaScript en assets/js/conciliacion.js, enviando una petición POST /api/conciliacion.php?action=resolver_alerta con los datos {alerta_id, cliente_id_seleccionado}.

3.4 Manejo de Excepciones y Reglas de Negocio Visibles

Umbral Estricto de Similitud:

$$\text{Porcentaje Similitud} < 85\% \implies \text{Registro enviado a } \texttt{alertas\_revision}$$

Regla de Segmentación facturacion_legal: Si el operador asocia manualmente una alerta a un cliente cuya categoría es facturacion_legal:

Se registra el despacho en la tabla despachos.

Se actualiza el saldo adeudado en saldos_pendientes.

Se excluye automáticamente de la cola de cobranza (cola_cobranza.json). No se le enviará ningún mensaje por WhatsApp.

4. MÓDULO 4: REGISTRO DE PAGOS (views/pagos_view.php)

4.1 Propósito Operativo y Workflow Asociado

Propósito Operativo: Permitir la recepción, validación e ingreso manual de las confirmaciones de pago reportadas por los clientes. Dispone de un selector dinámico conectado a la tabla formas_pago (Pago Móvil, Referencia Bancaria / Transferencia, Efectivo USD o Efectivo Bolívares Soberanos), procesando la liquidación total o abonos parciales sobre las deudas registradas.

Workflow Asociado: Implementa la interfaz operativa del Workflow 04 (WF-04: Gestión de Pagos, Conciliación Manual y Auditoría).

4.2 Layout y Estructura Visual

Header Superior:

Título Principal: "Gestión de Pagos, Liquidación de Saldos y Auditoría" (Texto #00001C).

Filtros de Cabecera: Buscador rápido por Nombre de Cliente / Alias y Filtro de texto por Despachador.

Botón de Acción: "Exportar Historial de Auditoría" (Fondo #2077F9, Texto Blanco).

Componentes Principales:

Panel Izquierdo: Formulario de Registro de Pago (pago_ingresado.json): Captura los datos de la transacción seleccionando la forma de pago dinámica desde la base de datos.

Panel Derecho: Tabla de Saldos Pendientes y Cartera (saldos_pendientes): Visualización en tiempo real de los saldos deudores acumulados por cliente y su historial de pedidos.

Aplicación de Paleta de Colores:

Fondo de contenedores y formulario: #F2F2F2.

Textos, bordes de tabla y headers de columna: #00001C.

Botón "Aplicar y Registrar Pago" e indicadores de estado 'al_dia': #2077F9.

4.3 Detalle de Elementos de Interacción

+-----------------------------------------------------------------------------------+
| LAYOUT VISUAL: views/pagos_view.php                                               |
+-----------------------------------------------------------------------------------+
| [ Header: Título | Buscador Cliente (Text) | Filtro Despachador (Text) ]          |
|                                                                                   |
| PANEL IZQUIERDO (FORMULARIO PAGO)       │ PANEL DERECHO (TABLA SALDOS PENDIENTES)  |
| ┌─────────────────────────────────────┐ │ +-----+-------------------+-----+-------+ |
| │ Cliente: [ Select Cliente (ID 104) ]│ │ | ID  | Cliente           | Zen | Alp   | |
| │ Forma Pago: [ Select Forma Pago v ] │ │ +-----+-------------------+-----+-------+ |
| │   -> Pago Móvil (Bs)                │ │ | 104 | Pasteleria Chacao |  2  |   1   | |
| │   -> Referencia / Transf. Bancaria  │ │ | 88  | Res. Tucurabua    |  0  |   4   | |
| │   -> Efectivo Dólares ($)           │ │ +-----+-------------------+-----+-------+ |
| │   -> Efectivo Bolívares Soberanos   │ │ Deuda Total Activa: $50.00 USD          |
| │ Ref Bancaria: [ 65638             ] │ │                                         |
| │ Monto Cancelado Bs: [ 1650.00     ] │ │                                         |
| │ Equiv. USD ($): [ 38.00           ] │ │                                         |
| │ Operador: [ admin_sistema         ] │ │                                         |
| │                                     │ │                                         |
| │ [ Botón: "Registrar Pago" #2077F9 ] │ │                                         |
| └─────────────────────────────────────┘ │                                         |
+-----------------------------------------------------------------------------------+


Campos del Formulario de Pago:

cliente_id: <select> autocompletado conectado a saldos_pendientes.

forma_pago_id: <select> desplegable dinámico alimentado desde la tabla formas_pago con opciones:

Pago Móvil (Bs)

Referencia / Transferencia Bancaria

Efectivo Dólares ($)

Efectivo Bolívares Soberanos (Bs)

referencia_bancaria: <input type="text" placeholder="Ej. 65638"> (Habilitado dinámicamente si formas_pago.requiere_referencia = TRUE).

monto_cancelado_bs: <input type="number" step="0.01">.

equivalente_aproximado_usd: <input type="number" step="0.01">.

operador_responsable: <input type="text" value="admin_sistema" readonly>.

Acciones de Botones y Endpoints:

Botón "Aplicar y Registrar Pago": Dispara la llamada POST /api/pagos.php enviando la estructura del pago vinculada al pedido y a la forma de pago seleccionada.

Columnas de la Tabla de Saldos Pendientes:

cliente_id

nombre_oficial

botellas_pendientes_zenda

botellas_pendientes_alpes

monto_deuda_total_usd

ultimo_metodo_pago_usado

estado_cartera (pendiente, pagado_parcial, al_dia)

ultimo_despacho_fecha

4.4 Manejo de Excepciones y Reglas de Negocio Visibles

INVARIANTE ABSOLUTA DE WHATSAPP (WF-03 / WF-04):

CERO MONTOS FINANCIEROS EN MENSAJES: Ningún mensaje enviado o generado para WhatsApp contendrá jamás montos o importes en Dólares ($) ni en Bolívares (Bs). La cobranza por WhatsApp se limita en un 100% al conteo numérico de botellas pendientes.

Lógica de Reconciliación de Saldos (Liquidación Total vs. Parcial):

                       [ Confirmación de Pago Ingresada ]
                                       │
                    ┌──────────────────┴──────────────────┐
                    ▼                                     ▼
        ¿Cubre el 100% Deuda?                 ¿Pago Parcial / Abono?
                    │                                     │
                    ▼                                     ▼
         [ ESCENARIO A: TOTAL ]                [ ESCENARIO B: PARCIAL ]
    * saldos_pendientes.zenda = 0         * Descuento FIFO de botellas
    * saldos_pendientes.alpes = 0           más antiguas.
    * monto_deuda_total_usd = 0.00        * Actualización de remanente.
    * despachos.estado_pago = 'pagado'    * despachos.estado_pago = 
    * estatus_cartera = 'al_dia'            'pagado_parcial'
    * guarda forma_pago_id en BD          * guarda forma_pago_id en BD


5. MÓDULO DE CONFIGURACIÓN Y CATÁLOGOS DINÁMICOS (marcas_agua Y formas_pago)

Para responder al requerimiento de que ni los precios ni los métodos de pago sean valores estáticos hardcoded, se especifica la integración de las siguientes tablas y su gestión desde un panel/modal interactivo de administración:

5.1 Especificación DDL para Catálogos Dinámicos

-- TABLA DE MARCAS DE AGUA Y PRECIOS DINÁMICOS
CREATE TABLE marcas_agua (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_marca VARCHAR(100) NOT NULL UNIQUE, -- Ej: 'La Zenda', 'Los Alpes'
    codigo_identificador VARCHAR(50) NOT NULL, -- Ej: 'zenda', 'alpes'
    precio_usd DECIMAL(10, 2) NOT NULL,        -- Ej: 7.00, 3.00
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA DE FORMAS Y MÉTODOS DE PAGO
CREATE TABLE formas_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_forma VARCHAR(100) NOT NULL UNIQUE,  -- Ej: 'Pago Móvil', 'Referencia / Transferencia', 'Efectivo Dólares', 'Efectivo Bolívares Soberanos'
    codigo_identificador VARCHAR(50) NOT NULL, -- Ej: 'pago_movil', 'transferencia', 'efectivo_usd', 'efectivo_bs'
    requiere_referencia BOOLEAN DEFAULT FALSE,  -- TRUE para Pago Móvil / Transferencia
    moneda_defecto VARCHAR(10) DEFAULT 'USD',   -- 'USD' o 'BS'
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RELACIÓN EN TABLA PAGOS / DESPACHOS
ALTER TABLE despachos ADD COLUMN forma_pago_id INT NULL FOREIGN KEY REFERENCES formas_pago(id);


5.2 Interfaz de Gestión de Precios y Formas de Pago

Panel Modal de Ajuste de Tarifas: Accesible desde el Header de la aplicación. Permite modificar los precios unitarios en dólares de cada marca de agua (UPDATE marcas_agua SET precio_usd = :nuevo_precio WHERE id = :id). Todos los cálculos futuros de las listas OCR e ingestas adoptan inmediatamente el nuevo precio ajustado.

Carga Dinámica en Formulario de Pagos: El selector de Forma de Pago en views/pagos_view.php se construye realizando una consulta GET /api/pagos.php?action=listar_formas_pago que recupera únicamente los registros donde activo = TRUE, permitiendo agregar o deshabilitar métodos de pago sin alterar el código fuente.

6. SECCIÓN DE VACÍOS Y OBSERVACIONES AUDITADAS

Tras la revisión exhaustiva cruzada entre los documentos de arquitectura (wf_01 a wf_04), los archivos del proyecto (soulproject.md, investigation.md), las restricciones operativas y las modificaciones de precios dinámicos y métodos de pago, se establecen las siguientes observaciones:

Tratamiento de la Entidad 'Despachador / Chofer':

Observación Auditada: En el esquema de base de datos (schema.sql), no existe una tabla aislada para chóferes. La columna despachador se almacena como un campo de texto plano (VARCHAR(100)) dentro de la tabla despachos.

Resolución UI/UX: Se descarta la creación de formularios CRUD para choferes. En las 4 vistas del frontend, el filtrado por chofer se realiza mediante cajas de texto o selectores dinámicos integrados en las cabeceras de los módulos.

Dinamización de Precios de Agua y Mapeo en Ingesta:

Observación Auditada: En las especificaciones originales se asumían precios fijos ($7.00 USD La Zenda y $3.00 USD Los Alpes).

Resolución TÉCNICA: Se incorpora la tabla marcas_agua. Al procesar despachos_diarios.json, la API de ingesta (api/ingesta.php) realiza un JOIN o consulta previa a marcas_agua para multiplicar las cantidades de botellones por el tarifa vigente precio_usd de la BD, eliminando valores estáticos.

Dinamización de Formas de Pago y Relación con Pedidos:

Observación Auditada: Anteriormente, los canales de pago se manejaban como un tipo ENUM o texto rígido en los formularios.

Resolución TÉCNICA: Se añade la tabla formas_pago. El select del panel izquierdo de views/pagos_view.php consulta esta tabla. Cada registro de pago o liquidación de despacho almacena el forma_pago_id correspondiente, vinculando directamente la transacción bancaria/efectivo con el pedido y la cartera del cliente.

Compatibilidad de Motores de Base de Datos (JSON vs JSONB):

Observación Auditada: La especificación wf_02_conciliacion.md hace referencia al tipo de dato JSONB (PostgreSQL), mientras que el stack técnico impone MySQL / MariaDB gestionado vía phpMyAdmin.

Resolución TÉCNICA: En la implementación del script DDL (database/schema.sql), la columna datos_item en la tabla alertas_revision se define como tipo JSON o TEXT nativo de MySQL, manteniendo la compatibilidad ACID e InnoDB.

Ejecución Asíncrona de Envíos de WhatsApp vs. Entorno PHP Vanilla:

Observación Auditada: El documento wf_03_cobranza.md establece pausas aleatorias de rate-limiting (18 a 42 segundos entre envíos). Una ejecución síncrona en un controlador PHP estándar bloquearía la solicitud HTTP del navegador.

Resolución de Arquitectura: El endpoint api/cobranza.php construye la cola de mensajes y registra las marcas de tiempo en estado en_espera_cola o notificado (modo simulación/worker), permitiendo que la interfaz gráfica (views/dashboard.php) responda instantáneamente al operador sin congelar la pantalla.