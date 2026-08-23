INSTRUCCIONES OBLIGATORIAS PARA EL AGENTE EJECUTOR (LLM DE DESARROLLO)

LECTURA DE ESTADO: Lee el archivo 'plan_ejecucion.md' y ubica la primera tarea pendiente (marcada con '- [ ]').

EJECUCIÓN FOCALIZADA: Construye ÚNICAMENTE el código, archivo o módulo correspondiente a esa tarea específica o sub-bloque activo.

ACTUALIZACIÓN DEL PLAN: Tras entregar el código funcional sin omisiones, REESCRIBE / ACTUALIZA el archivo 'plan_ejecucion.md' cambiando la casilla de la tarea ejecutada de '- [ ]' a '- [x]'.

CONFIRMACIÓN Y PASO SIGUIENTE: Informa al usuario qué tarea fue completada y cuál es el siguiente paso según el plan. NUNCA te saltes fases ni dejes tareas a medias.
================================================================================

HOJA DE RUTA MAESTRA Y PLAN DE EJECUCIÓN DEL SISTEMA

Sistema: Automatización de Cobranza y Control de Despachos — Distribuidora de Agua Mineral

Stack Técnico: PHP Vanilla, MySQL / MariaDB (InnoDB, utf8mb4), HTML5, CSS3 (#2077F9, #00001C, #F2F2F2), Vanilla JavaScript (ES6+ / Fetch API).

FASE 1: BASE DE DATOS, MODELO RELACIONAL Y CATÁLOGOS DINÁMICOS

Objetivo: Crear la estructura de directorios del proyecto, la base de datos relacional MySQL con soporte utf8mb4 e InnoDB, los catálogos dinámicos (marcas_agua, formas_pago) y las tablas operativas con sus datos semilla (mocks) correspondientes.

Tareas Atómicas:

[ ] [TASK-1.1] Crear la estructura base de directorios del proyecto (config/, database/, api/, views/, assets/js/, assets/css/).

[ ] [TASK-1.2] Crear el script DDL database/schema.sql definiendo las tablas de catálogos dinámicos (marcas_agua y formas_pago) con sus respectivos campos, restricciones de unicidad y claves primarias.

[ ] [TASK-1.3] Completar database/schema.sql con la DDL de las tablas core (clientes, despachos, saldos_pendientes, alertas_revision), incluyendo relaciones FK, índices optimizados y compatibilidad con campos de tipo JSON o TEXT en MySQL.

[ ] [TASK-1.4] Crear el archivo database/seeds.sql (o integrar en schema.sql) con datos semilla para:

marcas_agua: La Zenda ($7.00 USD), Los Alpes ($3.00 USD).

formas_pago: Pago Móvil, Transferencia Bancaria, Efectivo USD, Efectivo Bs.

clientes: Ejemplos representativos (incluyendo clientes estándar, alias de despacho y al menos un cliente con categoria_cliente = 'facturacion_legal').

saldos_pendientes: Registros de prueba para simular cartera inicial.

Criterios de Aceptación - Fase 1:

El archivo database/schema.sql se ejecuta sin errores ni advertencias en MySQL / MariaDB via phpMyAdmin o CLI.

Todas las claves foráneas están declaradas explícitamente y con integridad referencial.

Los catálogos dinámicos contienen datos iniciales válidos y activos.

FASE 2: CONEXIÓN Y ENDPOINTS BACKEND PHP (APIS CORE)

Objetivo: Construir la capa de persistencia y la suite de APIs RESTful / controladores backend en PHP Vanilla que sirvan como motor de lógica de negocio para la ingesta OCR, conciliación, cobranza y pagos.

Tareas Atómicas:

[ ] [TASK-2.1] Crear el archivo config/db.php con la clase/función de conexión PDO (singleton / conexión segura), UTF-8 habilitado, manejo de excepciones y headers de respuesta JSON estandarizados.

[ ] [TASK-2.2] Crear la API api/ingesta.php para procesar e importar la lista diaria (despachos_diarios.json). Debe calcular precios dinámicamente consultando la tabla marcas_agua según la fórmula:


$$\text{monto\_despacho\_usd} = \sum_{i=1}^{N} \left( \text{cantidad\_botellas}_i \times \text{precio\_usd}_i \right)$$


E implementar la regla de asignación por defecto si no se especifica marca en la nota.

[ ] [TASK-2.3] Crear la API api/conciliacion.php con las acciones de:

Comparación de nombres (Fuzzy Matching usando similar_text / levenshtein).

Clasificación: Si la similitud es $< 85\%$, registra el caso en alertas_revision.

Acción action=resolver_alerta para asociación manual por el operador.

Regla de negocio: Si el cliente asociado es facturacion_legal, se procesa el despacho y saldo, pero se excluye automáticamente de la cola de WhatsApp.

[ ] [TASK-2.4] Crear la API api/cobranza.php para la construcción y consulta de la cola de notificaciones de WhatsApp.

INVARIANTE ABSOLUTA: CERO montos financieros ($ o Bs) en los textos generados. Únicamente conteo físico de botellones.

Simulación/Gestión asíncrona de tiempos de envío (Rate-limiting de 18 a 42 segundos).

[ ] [TASK-2.5] Crear la API api/pagos.php para:

Listar métodos de pago activos (action=listar_formas_pago).

Registrar pagos recibidos vinculados a forma_pago_id.

Ejecutar reconciliación de cartera: Liquidación Total vs. Pago Parcial (aplicando descuento FIFO a las botellas adeudadas más antiguas y actualizando saldos_pendientes y despachos.estado_pago).

Criterios de Aceptación - Fase 2:

Todos los endpoints responden con Content-Type: application/json y manejan códigos de estado HTTP adecuados (200, 400, 404, 500).

La invariante de $0 financieros en mensajes de WhatsApp se verifica en el 100% de los outputs de api/cobranza.php.

La liquidación de pagos realiza transacciones ACID con PDO::beginTransaction() y commit().

FASE 3: CONTROLADORES JS Y LÓGICA DE NEGOCIO CLIENT-SIDE

Objetivo: Desarrollar los módulos JavaScript puros (sin frameworks pesados) para la gestión del estado en el cliente, peticiones asíncronas vía Fetch API, manipulación interactiva del DOM y cálculo dinámico local.

Tareas Atómicas:

[ ] [TASK-3.1] Crear assets/js/app.js como controlador principal del sistema: funciones utilitarias de Fetch API, formateadores numéricos/fechas, renderizado de KPIs globales e interacciones del Header/Sidebar.

[ ] [TASK-3.2] Crear assets/js/conciliacion.js para controlar:

Carga y validación en cliente del archivo despachos_diarios.json en el área de Dropzone.

Pre-inspección local del archivo JSON antes de enviar al servidor.

Control modal autocompletado para resolución manual de alertas con filtro dinámico sobre el catálogo de clientes.

[ ] [TASK-3.3] Crear assets/js/pagos.js para gestionar:

Carga dinámica del selector <select> de formas de pago desde api/pagos.php.

Habilitación/deshabilitación reactiva de inputs según el campo requiere_referencia.

Cálculo inmediato en pantalla de montos y equivalencias.

Actualización reactiva de la tabla de saldos pendientes tras la confirmación de pago.

Criterios de Aceptación - Fase 3:

Toda comunicación asíncrona se efectúa vía async/await Fetch API con manejo de errores .catch() y retroalimentación al usuario.

No existen recargas de página completas durante la resolución de alertas o el registro de pagos.

FASE 4: INTERFACES WEB / MÓDULOS UI PHP Y ESTILOS CSS

Objetivo: Construir las vistas HTML/PHP y la hoja de estilos unificada aplicando estrictamente la paleta de colores corporativa y la distribución de layout especificada en la guía UI/UX.

Tareas Atómicas:

[ ] [TASK-4.1] Crear assets/css/styles.css implementando la paleta obligatoria:

Color Primario / Botones: #2077F9 (Azul Eléctrico).

Headers / Sidebar / Tablas: #00001C (Oscuro Profundo).

Backgrounds / Dropzones / KPI Cards: #F2F2F2 (Gris Claro).

Incluir reglas de diseño responsivo, modales, badges y zonas de arrastre (dropzone).

[ ] [TASK-4.2] Crear la vista del Dashboard General en views/dashboard.php:

Header con controles de fecha y filtros de despachador.

Grid de 4 Tarjetas KPI (Total Botellas Despachadas Hoy por marca, Deuda Total Recuperable $, Estatus Cobranza WhatsApp, Alertas Pendientes).

Tabla Resumen de Despachos de la jornada.

Panel Modal de Ajuste de Tarifas y Formas de Pago.

[ ] [TASK-4.3] Crear la vista de Ingesta OCR en views/ingesta_view.php:

Componente Dropzone visual (.json).

Panel de Métricas Extraídas por Marca (Tarifario Dinámico).

Tabla de Pre-inspección de Despachos antes del procesamiento definitivo.

[ ] [TASK-4.4] Crear la vista de Alertas y Ambigüedades en views/alertas_view.php:

Bandeja de resolución de alertas no coincidentes ($<85\%$).

Indicadores visuales de % de similitud y candidato sugerido.

Estructura modal para vincular con el cliente oficial.

[ ] [TASK-4.5] Crear la vista de Registro de Pagos en views/pagos_view.php:

Layout dividido en dos paneles (Panel Izquierdo: Formulario de Registro con selector dinámico de formas_pago; Panel Derecho: Tabla interactiva de saldos_pendientes y cartera).

Criterios de Aceptación - Fase 4:

Fidelidad visual del 100% con los códigos HEX #2077F9, #00001C, #F2F2F2.

Los componentes son plenamente navegables y accesibles desde dispositivos de escritorio y tablets.

FASE 5: INTEGRACIÓN, ROUTER PRINCIPAL Y PRUEBAS E2E

Objetivo: Unificar todos los módulos bajo un enrutador central PHP (index.php), realizar pruebas de integración de extremo a extremo (E2E) y verificar el cumplimiento de todas las invariantes del sistema.

Tareas Atómicas:

[ ] [TASK-5.1] Crear index.php como Layout Principal/Router del sistema que incluya el Sidebar dinámico, Header global, ruteo de vistas (?view=dashboard, ?view=ingesta, ?view=alertas, ?view=pagos) y la inyección de los scripts JS correspondientes.

[ ] [TASK-5.2] Ejecutar Pruebas E2E del Flujo Completo:

Carga de despachos_diarios.json en ingesta_view.php.

Verificación de cálculo de precios dinámicos según marcas_agua.

Detección y resolución manual de alerta en alertas_view.php.

Verificación de exclusión de WhatsApp para clientes facturacion_legal.

Registro de pago parcial y total en pagos_view.php con descuento FIFO.

Validación de cola de WhatsApp en dashboard.php confirmando $0 montos financieros.

[ ] [TASK-5.3] Crear el archivo README.md con las instrucciones de instalación, importación de base de datos, configuración de servidor local (XAMPP/WAMP/Nginx) y manual rápido de usuario.

Criterios de Aceptación - Fase 5:

Navegación fluida e integrada a través de index.php.

Cero errores en logs de PHP y cero excepciones no capturadas en la consola del navegador.

Validación final exitosa de todas las invariantes operativas y financieras del sistema.