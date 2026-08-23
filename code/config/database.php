<?php
// -------------------------------------------------------------
// CONFIGURACIÓN DE CONEXIÓN A BASE DE DATOS (PDO)
// -------------------------------------------------------------

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'distribuidora_agua');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Retorna una instancia de PDO para conectarse a la base de datos.
 * Auto-inicializa la base de datos y sus tablas si no existen.
 * 
 * @return PDO
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // 1. Conexión inicial al servidor MySQL para asegurar que la base de datos exista
            $initDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
            $initPdo = new PDO($initDsn, DB_USER, DB_PASS, $options);
            $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            unset($initPdo);

            // 2. Conexión directa a la base de datos de la distribuidora
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // 3. Asegurar esquema de tablas y estructura
            initializeDatabaseSchema($pdo);
        } catch (PDOException $e) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos MySQL: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Función auxiliar para enviar respuestas JSON estandarizadas.
 */
function sendJsonResponse($success, $message, $data = [], $httpCode = 200) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
    }
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

/**
 * Asegura la existencia de todas las tablas y constraints del sistema.
 */
function initializeDatabaseSchema(PDO $pdo) {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    // 1. Usuarios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usuarios` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `usuario` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `nombre` VARCHAR(100) NOT NULL,
            `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Marcas de Agua
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `marcas_agua` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre_marca` VARCHAR(100) NOT NULL UNIQUE,
            `codigo_identificador` VARCHAR(50) NOT NULL UNIQUE,
            `precio_usd` DECIMAL(10, 2) NOT NULL,
            `activo` BOOLEAN DEFAULT TRUE,
            `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Formas de Pago
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `formas_pago` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre_forma` VARCHAR(100) NOT NULL UNIQUE,
            `codigo_identificador` VARCHAR(50) NOT NULL UNIQUE,
            `requiere_referencia` BOOLEAN DEFAULT FALSE,
            `moneda_defecto` VARCHAR(10) DEFAULT 'USD',
            `activo` BOOLEAN DEFAULT TRUE,
            `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Choferes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `choferes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre` VARCHAR(100) NOT NULL,
            `telefono` VARCHAR(20) NULL,
            `activo` BOOLEAN DEFAULT TRUE,
            `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Clientes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `clientes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre_oficial` VARCHAR(150) NOT NULL,
            `nombre_despacho_alias` VARCHAR(150) NOT NULL UNIQUE,
            `telefono_whatsapp` VARCHAR(20) NOT NULL,
            `categoria` ENUM('domicilio', 'local', 'facturacion_legal') NOT NULL DEFAULT 'local',
            `activo` BOOLEAN DEFAULT TRUE,
            `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 6. Despachos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `despachos` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `fecha` DATE NOT NULL,
            `cliente_id` INT NULL,
            `nombre_cliente_raw` VARCHAR(150) NULL,
            `alias_despacho_consolidado` VARCHAR(150) NULL,
            `despachador` VARCHAR(100) NOT NULL,
            `chofer_id` INT NULL,
            `botellas_zenda` INT DEFAULT 0,
            `botellas_alpes` INT DEFAULT 0,
            `monto_despacho_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `estado_pago` ENUM('pendiente', 'notificado', 'pagado_parcial', 'pagado') DEFAULT 'pendiente',
            `forma_pago_id` INT NULL,
            `referencia_pago` VARCHAR(100) NULL,
            `observaciones` TEXT NULL,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_despachos_fecha_despachador` (`fecha`, `despachador`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 7. Saldos Pendientes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `saldos_pendientes` (
            `cliente_id` INT PRIMARY KEY,
            `botellas_pendientes_zenda` INT DEFAULT 0,
            `botellas_pendientes_alpes` INT DEFAULT 0,
            `monto_deuda_total_usd` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `ultimo_despacho_fecha` DATE NULL,
            `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 8. Alertas de Revisión
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `alertas_revision` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `fecha` DATE NOT NULL,
            `nombre_raw` VARCHAR(150) NOT NULL,
            `motivo` VARCHAR(100) NOT NULL,
            `datos_item` JSON NOT NULL,
            `resuelto` BOOLEAN DEFAULT FALSE,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 9. Asegurar columnas en tablas existentes (Migración no destructiva)
    $columnStmt = $pdo->query('SHOW COLUMNS FROM despachos');
    $columns = [];
    foreach ($columnStmt->fetchAll() as $col) {
        $columns[$col['Field']] = $col;
    }

    if (!isset($columns['nombre_cliente_raw'])) {
        $pdo->exec('ALTER TABLE despachos ADD COLUMN nombre_cliente_raw VARCHAR(150) NULL AFTER cliente_id');
    }
    if (!isset($columns['alias_despacho_consolidado'])) {
        $pdo->exec('ALTER TABLE despachos ADD COLUMN alias_despacho_consolidado VARCHAR(150) NULL AFTER nombre_cliente_raw');
    }
    if (!isset($columns['chofer_id'])) {
        $pdo->exec('ALTER TABLE despachos ADD COLUMN chofer_id INT NULL AFTER despachador');
    }

    // Permitir cliente_id NULL para despachos con alertas pendientes
    if (isset($columns['cliente_id']) && strtoupper($columns['cliente_id']['Null']) === 'NO') {
        try {
            $fkStmt = $pdo->query("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'despachos'
                  AND COLUMN_NAME = 'cliente_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            foreach ($fkStmt->fetchAll() as $fk) {
                $name = $fk['CONSTRAINT_NAME'];
                $pdo->exec("ALTER TABLE despachos DROP FOREIGN KEY `{$name}`");
            }
        } catch (Exception $e) {}

        $pdo->exec('ALTER TABLE despachos MODIFY cliente_id INT NULL');
    }

    // Asegurar semillas por defecto
    $userCount = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ((int)$userCount === 0) {
        $pdo->exec("
            INSERT INTO `usuarios` (`id`, `usuario`, `password`, `nombre`) VALUES
            (1, 'admin', '\$2y\$10\$IaGnnEmPDqYfudh7Bt0X6eXtfviNZmAXXU2RC0I2gCUe1xqQCXTg6', 'Administrador General');
        ");
    }

    $marcasCount = $pdo->query("SELECT COUNT(*) FROM marcas_agua")->fetchColumn();
    if ((int)$marcasCount === 0) {
        $pdo->exec("
            INSERT INTO `marcas_agua` (`id`, `nombre_marca`, `codigo_identificador`, `precio_usd`) VALUES
            (1, 'La Zenda', 'zenda', 7.00),
            (2, 'Los Alpes', 'alpes', 3.00);
        ");
    }

    $formasCount = $pdo->query("SELECT COUNT(*) FROM formas_pago")->fetchColumn();
    if ((int)$formasCount === 0) {
        $pdo->exec("
            INSERT INTO `formas_pago` (`id`, `nombre_forma`, `codigo_identificador`, `requiere_referencia`, `moneda_defecto`) VALUES
            (1, 'Pago Móvil', 'pago_movil', 1, 'BS'),
            (2, 'Referencia / Transferencia Bancaria', 'transferencia', 1, 'BS'),
            (3, 'Efectivo Dólares ($)', 'efectivo_usd', 0, 'USD'),
            (4, 'Efectivo Bolívares Soberanos', 'efeditvo_bs', 0, 'BS');
        ");
    }
}
