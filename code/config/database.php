<?php
// -------------------------------------------------------------
// CONFIGURACIÓN DE CONEXIÓN A BASE DE DATOS (PDO)
// -------------------------------------------------------------

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306'); // Puerto estándar de MySQL
define('DB_NAME', 'distribuidora_agua');
define('DB_USER', 'root');
define('DB_PASS', ''); // Contraseña vacía por defecto en XAMPP

/**
 * Retorna una instancia de PDO para conectarse a la base de datos.
 * Configura el manejo de excepciones y la codificación UTF-8.
 * 
 * @return PDO
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensureDespachosSchema($pdo);
        } catch (PDOException $e) {
            // Si hay un error, respondemos en formato JSON de forma segura y detenemos la ejecución
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Función auxiliar para enviar respuestas JSON estandarizadas.
 * 
 * @param bool $success Indica si la operación fue exitosa
 * @param string $message Mensaje informativo
 * @param array $data Datos adicionales
 * @param int $httpCode Código de estado HTTP
 */
function sendJsonResponse($success, $message, $data = [], $httpCode = 200) {
    // Si los headers no han sido enviados, establecemos el Content-Type y código de estado
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
 * Asegura que despachos acepte filas sin cliente catalogado.
 * Aplica ALTER idempotente sobre bases ya importadas.
 */
function ensureDespachosSchema(PDO $pdo) {
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

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

    if (isset($columns['cliente_id']) && strtoupper($columns['cliente_id']['Null']) === 'NO') {
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
        $pdo->exec('ALTER TABLE despachos MODIFY cliente_id INT NULL');
        $pdo->exec('
            ALTER TABLE despachos
            ADD CONSTRAINT fk_despachos_cliente
            FOREIGN KEY (cliente_id) REFERENCES clientes(id)
            ON DELETE RESTRICT ON UPDATE CASCADE
        ');
    }

    $indexes = $pdo->query('SHOW INDEX FROM despachos')->fetchAll();
    $hasFechaDespachadorIndex = false;
    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'idx_despachos_fecha_despachador') {
            $hasFechaDespachadorIndex = true;
            break;
        }
    }
    if (!$hasFechaDespachadorIndex) {
        $pdo->exec('ALTER TABLE despachos ADD INDEX idx_despachos_fecha_despachador (fecha, despachador)');
    }
}
