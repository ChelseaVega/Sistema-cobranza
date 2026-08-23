<?php
// -------------------------------------------------------------
// ENDPOINT: GESTIÓN DE CLIENTES (api/clientes.php)
// -------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

// Iniciar sesión y validar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    sendJsonResponse(false, 'Acceso denegado. Sesión no iniciada.', [], 401);
}

$action = isset($_GET['action']) ? trim($_GET['action']) : 'listar';

try {
    $pdo = getDatabaseConnection();

    // ACCIÓN 1: Listar Clientes con saldos actuales (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'listar') {
        $busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
        $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

        $query = '
            SELECT c.id, c.nombre_oficial, c.nombre_despacho_alias, c.telefono_whatsapp,
                   c.categoria, c.activo, c.fecha_registro,
                   COALESCE(s.botellas_pendientes_zenda, 0) as botellas_zenda,
                   COALESCE(s.botellas_pendientes_alpes, 0) as botellas_alpes,
                   COALESCE(s.monto_deuda_total_usd, 0.00) as monto_deuda_usd,
                   s.ultimo_despacho_fecha
            FROM clientes c
            LEFT JOIN saldos_pendientes s ON c.id = s.cliente_id
            WHERE 1=1
        ';
        $params = [];

        if ($busqueda !== '') {
            $query .= ' AND (c.nombre_oficial LIKE :q OR c.nombre_despacho_alias LIKE :q OR c.telefono_whatsapp LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        if ($categoria !== '' && in_array($categoria, ['domicilio', 'local', 'facturacion_legal'])) {
            $query .= ' AND c.categoria = :categoria';
            $params['categoria'] = $categoria;
        }

        $query .= ' ORDER BY c.id DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll();

        sendJsonResponse(true, 'Clientes obtenidos con éxito.', [
            'clientes' => $clientes,
            'total' => count($clientes)
        ]);
    }

    // ACCIÓN 2: Obtener un Cliente por ID (GET)
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'obtener') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            sendJsonResponse(false, 'ID de cliente inválido.', [], 400);
        }

        $stmt = $pdo->prepare('
            SELECT c.id, c.nombre_oficial, c.nombre_despacho_alias, c.telefono_whatsapp,
                   c.categoria, c.activo, c.fecha_registro,
                   COALESCE(s.botellas_pendientes_zenda, 0) as botellas_zenda,
                   COALESCE(s.botellas_pendientes_alpes, 0) as botellas_alpes,
                   COALESCE(s.monto_deuda_total_usd, 0.00) as monto_deuda_usd,
                   s.ultimo_despacho_fecha
            FROM clientes c
            LEFT JOIN saldos_pendientes s ON c.id = s.cliente_id
            WHERE c.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            sendJsonResponse(false, 'Cliente no encontrado.', [], 404);
        }

        sendJsonResponse(true, 'Cliente encontrado.', ['cliente' => $cliente]);
    }

    // ACCIÓN 3: Crear Nuevo Cliente (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'crear' || empty($action))) {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        if (!$dataInput) {
            $dataInput = $_POST;
        }

        $nombreOficial = isset($dataInput['nombre_oficial']) ? trim($dataInput['nombre_oficial']) : '';
        $aliasDespacho = isset($dataInput['nombre_despacho_alias']) ? trim($dataInput['nombre_despacho_alias']) : '';
        $telefono = isset($dataInput['telefono_whatsapp']) ? trim($dataInput['telefono_whatsapp']) : '';
        $categoria = isset($dataInput['categoria']) ? trim($dataInput['categoria']) : 'local';

        if (empty($nombreOficial) || empty($aliasDespacho) || empty($telefono)) {
            sendJsonResponse(false, 'Todos los campos requeridos deben ser completados.', [], 400);
        }

        if (!in_array($categoria, ['domicilio', 'local', 'facturacion_legal'])) {
            sendJsonResponse(false, 'Categoría no válida.', [], 400);
        }

        // Validar alias único
        $stmtCheck = $pdo->prepare('SELECT COUNT(*) as total FROM clientes WHERE nombre_despacho_alias = :alias');
        $stmtCheck->execute(['alias' => $aliasDespacho]);
        if ((int)$stmtCheck->fetch()['total'] > 0) {
            sendJsonResponse(false, "El alias de despacho '{$aliasDespacho}' ya está asignado a otro cliente.", [], 400);
        }

        $pdo->beginTransaction();

        $stmtInsert = $pdo->prepare('
            INSERT INTO clientes (nombre_oficial, nombre_despacho_alias, telefono_whatsapp, categoria, activo)
            VALUES (:oficial, :alias, :tel, :cat, 1)
        ');
        $stmtInsert->execute([
            'oficial' => $nombreOficial,
            'alias' => $aliasDespacho,
            'tel' => $telefono,
            'cat' => $categoria
        ]);
        $clienteId = (int)$pdo->lastInsertId();

        // Inicializar fila en saldos_pendientes
        $stmtSaldo = $pdo->prepare('
            INSERT INTO saldos_pendientes (cliente_id, botellas_pendientes_zenda, botellas_pendientes_alpes, monto_deuda_total_usd, ultimo_despacho_fecha)
            VALUES (:cliente_id, 0, 0, 0.00, NULL)
        ');
        $stmtSaldo->execute(['cliente_id' => $clienteId]);

        $pdo->commit();

        sendJsonResponse(true, 'Cliente creado exitosamente en la base de datos.', [
            'cliente_id' => $clienteId
        ]);
    }

    // ACCIÓN 4: Actualizar Cliente Existente (POST / PUT)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'actualizar') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        if (!$dataInput) {
            $dataInput = $_POST;
        }

        $id = isset($dataInput['id']) ? (int)$dataInput['id'] : 0;
        $nombreOficial = isset($dataInput['nombre_oficial']) ? trim($dataInput['nombre_oficial']) : '';
        $aliasDespacho = isset($dataInput['nombre_despacho_alias']) ? trim($dataInput['nombre_despacho_alias']) : '';
        $telefono = isset($dataInput['telefono_whatsapp']) ? trim($dataInput['telefono_whatsapp']) : '';
        $categoria = isset($dataInput['categoria']) ? trim($dataInput['categoria']) : 'local';
        $activo = isset($dataInput['activo']) ? (int)$dataInput['activo'] : 1;

        if ($id <= 0 || empty($nombreOficial) || empty($aliasDespacho) || empty($telefono)) {
            sendJsonResponse(false, 'Parámetros incompletos para actualizar el cliente.', [], 400);
        }

        // Validar alias único ignorando el cliente actual
        $stmtCheck = $pdo->prepare('SELECT COUNT(*) as total FROM clientes WHERE nombre_despacho_alias = :alias AND id != :id');
        $stmtCheck->execute(['alias' => $aliasDespacho, 'id' => $id]);
        if ((int)$stmtCheck->fetch()['total'] > 0) {
            sendJsonResponse(false, "El alias '{$aliasDespacho}' ya está siendo usado por otro cliente.", [], 400);
        }

        $stmtUpdate = $pdo->prepare('
            UPDATE clientes
            SET nombre_oficial = :oficial,
                nombre_despacho_alias = :alias,
                telefono_whatsapp = :tel,
                categoria = :cat,
                activo = :act
            WHERE id = :id
        ');
        $stmtUpdate->execute([
            'oficial' => $nombreOficial,
            'alias' => $aliasDespacho,
            'tel' => $telefono,
            'cat' => $categoria,
            'act' => $activo,
            'id' => $id
        ]);

        sendJsonResponse(true, 'Cliente actualizado correctamente.');
    }

    // ACCIÓN 5: Alternar Estatus Activo/Inactivo (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_activo') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);

        $id = isset($dataInput['id']) ? (int)$dataInput['id'] : 0;
        $activo = isset($dataInput['activo']) ? ((int)$dataInput['activo'] ? 1 : 0) : 1;

        if ($id <= 0) {
            sendJsonResponse(false, 'ID inválido.', [], 400);
        }

        $stmt = $pdo->prepare('UPDATE clientes SET activo = :activo WHERE id = :id');
        $stmt->execute(['activo' => $activo, 'id' => $id]);

        sendJsonResponse(true, 'Estado del cliente actualizado.');
    }

    else {
        sendJsonResponse(false, 'Acción o método HTTP no soportado.', [], 400);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(false, 'Error en gestión de clientes: ' . $e->getMessage(), [], 500);
}
