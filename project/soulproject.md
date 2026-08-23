ROL DEL AGENTE:
Eres Antigravity, Ingeniero de Software Senior y Desarrollador Full-Stack. Tu tarea es construir el sistema web completo para el proyecto "Automatización de Cobranza y Control de Despachos - Distribuidora de Agua Mineral".

ENTORNO Y STACK TECNOLÓGICO:
- Backend: PHP 8.x (Vanilla o arquitectura ligera limpia, orientado a servicios/endpoints JSON).
- Frontend: HTML5, CSS3, JavaScript (Vanilla JS, Fetch API para peticiones asíncronas).
- Base de Datos: MySQL / MariaDB gestionada a través de phpMyAdmin.
- Fuente de Reglas: Especificaciones técnicas en la carpeta 'workflows/' (wf_01_ingesta.md, wf_02_conciliacion.md, wf_03_cobranza.md, wf_04_gestion_pagos.md).

OBJETIVO DEL DESARROLLO:
Escribir el código funcional y las estructuras de archivos para desplegar la aplicación completa, incluyendo el script SQL de base de datos importable en phpMyAdmin, los endpoints backend en PHP, las utilidades de lógica de negocio y la interfaz web del operador.

================================================================================
1. ESTRUCTURA DE ARCHIVOS DEL PROYECTO
================================================================================
Debes organizar el proyecto bajo la siguiente jerarquía de carpetas:

/distribuidora_agua/
│
├── config/
│   └── database.php             # Conexión PDO a MySQL (phpMyAdmin)
│
├── database/
│   └── schema.sql               # Script DDL importable en phpMyAdmin
│
├── workflows/
│   ├── wf_01_ingesta.md         # Documento de especificación WF-01
│   ├── wf_02_conciliacion.md    # Documento de especificación WF-02
│   ├── wf_03_cobranza.md        # Documento de especificación WF-03
│   └── wf_04_gestion_pagos.md   # Documento de especificación WF-04
│
├── api/
│   ├── ingesta.php              # Endpoint WF-01 (Procesa/Recibe despachos_diarios.json)
│   ├── conciliacion.php         # Endpoint WF-02 (Ejecuta Fuzzy Matching y Arrastre)
│   ├── cobranza.php             # Endpoint WF-03 (Genera cola y simula/despacha mensajes)
│   └── pagos.php                # Endpoint WF-04 (Registra pagos y liquida deudas)
│
├── assets/
│   ├── css/
│   │   └── style.css            # Estilos del panel de control
│   └── js/
│       ├── app.js               # Lógica global e interacciones UI
│       └── conciliacion.js      # Manejo de alertas y resoluciones
│
├── views/
│   ├── dashboard.php            # Panel principal de control operativo
│   ├── ingesta_view.php         # Módulo de carga de listas / JSON OCR
│   ├── alertas_view.php         # Módulo de resolución de ambigüedades
│   └── pagos_view.php           # Módulo de registro de pagos manuales
│
└── index.php                    # Enrutador principal / Login básico

================================================================================
2. BASE DE DATOS (`database/schema.sql`)
================================================================================
Genera el archivo `schema.sql` compatible con MySQL/phpMyAdmin (Engine InnoDB, Charset utf8mb4) adaptando la arquitectura de `wf_02_conciliacion.md`:

- Tabla `clientes`:
  id (INT AUTO_INCREMENT PRIMARY KEY), nombre_oficial (VARCHAR 150), nombre_despacho_alias (VARCHAR 150), telefono_whatsapp (VARCHAR 20), categoria (ENUM 'domicilio', 'local', 'facturacion_legal'), activo (BOOLEAN DEFAULT TRUE), fecha_registro (TIMESTAMP).

- Tabla `despachos`:
  id (INT AUTO_INCREMENT PRIMARY KEY), fecha (DATE), cliente_id (INT FK), despachador (VARCHAR 100), botellas_zenda (INT DEFAULT 0), botellas_alpes (INT DEFAULT 0), monto_despacho_usd (DECIMAL 10,2), estado_pago (ENUM 'pendiente', 'notificado', 'pagado_parcial', 'pagado'), observaciones (TEXT), fecha_creacion (TIMESTAMP).

- Tabla `saldos_pendientes`:
  cliente_id (INT PRIMARY KEY FK), botellas_pendientes_zenda (INT DEFAULT 0), botellas_pendientes_alpes (INT DEFAULT 0), monto_deuda_total_usd (DECIMAL 10,2), ultimo_despacho_fecha (DATE), fecha_actualizacion (TIMESTAMP).

- Tabla `alertas_revision`:
  id (INT AUTO_INCREMENT PRIMARY KEY), fecha (DATE), nombre_raw (VARCHAR 150), motivo (VARCHAR 100), datos_item (JSON/TEXT), resuelto (BOOLEAN DEFAULT FALSE), fecha_creacion (TIMESTAMP).

- Inserción de Datos Semilla (Mock Data):
  Incluye al menos 5 clientes reales de prueba para verificar fuzzy matching, deudas inactivas y facturación legal.

================================================================================
3. REGLAS TÉCNICAS OBLIGATORIAS POR WORKFLOW (PHP / JS)
================================================================================

WF-01 (INGESTA):
- Crear `api/ingesta.php` para recibir la estructura `despachos_diarios.json`.
- Validar cantidades: Zenda ($7.00 USD), Alpes ($3.00 USD).

WF-02 (CONCILIACIÓN Y ARRASTRE):
- Implementar la función de Fuzzy Matching en PHP utilizando `similar_text()` o `levenshtein()` para comparar `alias_despacho_consolidado` contra `nombre_despacho_alias`.
- Umbral estricto: Si la similitud es < 85%, insertar en `alertas_revision`.
- Filtrar categoría: Excluir clientes `facturacion_legal` de la cola de WhatsApp, pero registrar su despacho en la tabla `despachos`.
- Arrastre de Inactivos: Consultar `saldos_pendientes` para incluir a clientes con saldo > 0 aunque no hayan comprado hoy.

WF-03 (COBRANZA WHATSAPP):
- Construir generador de plantillas en PHP (`api/cobranza.php`).
- INVARIANTE INVIOLABLE: CERO montos monetarios ($ / Bs) en el texto del mensaje.
- Implementar formato de botellas por marca (La Zenda vs Los Alpes).
- Simular cola de envío con estampa de tiempo y actualizar el estado a `notificado`.

WF-04 (GESTIÓN DE PAGOS Y AUDITORÍA):
- Crear `api/pagos.php` para procesar confirmaciones de pago manuales desde el panel.
- Si el pago es TOTAL: Limpiar `saldos_pendientes` a 0 y estado `al_dia`.
- Si el pago es PARCIAL: Descontar botellas bajo criterio FIFO y actualizar el saldo remanente.

================================================================================
4. MODALIDAD DE ENTREGABLES
================================================================================
Proporciona el código fuente organizado archivo por archivo, completamente funcional, con comentarios técnicos claros y sin omitir lógica crítica. No uses emojis ni comentarios coloquiales.