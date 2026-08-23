<?php
// -------------------------------------------------------------
// ENDPOINT: GESTIÓN DE CHOFERES (api/choferes.php)
// -------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

// Iniciar sesión y validar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    sendJsonResponse(false, 'Acceso denegado. Sesión no iniciada.', [], 401);
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
    $action = 'listar';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $action = 'crear';
}

try {
    $pdo = getDatabaseConnection();

    // ACCIÓN 1: Listar Choferes (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'listar') {
        $busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

        $query = '
            SELECT c.id, c.nombre, c.telefono, c.activo, c.fecha_registro,
                   (SELECT COUNT(*) FROM despachos d WHERE d.despachador LIKE CONCAT("%", c.nombre, "%") OR d.chofer_id = c.id) as total_despachos
            FROM choferes c
            WHERE 1=1
        ';
        $params = [];

        if ($busqueda !== '') {
            $query .= ' AND (c.nombre LIKE :q OR c.telefono LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        $query .= ' ORDER BY c.id DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $choferes = $stmt->fetchAll();

        sendJsonResponse(true, 'Choferes obtenidos con éxito.', [
            'choferes' => $choferes,
            'total' => count($choferes)
        ]);
    }

    // ACCIÓN 2: Obtener Chofer por ID (GET)
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'obtener') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            sendJsonResponse(false, 'ID de chofer inválido.', [], 400);
        }

        $stmt = $pdo->prepare('SELECT id, nombre, telefono, activo, fecha_registro FROM choferes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $chofer = $stmt->fetch();

        if (!$chofer) {
            sendJsonResponse(false, 'Chofer no encontrado.', [], 404);
        }

        sendJsonResponse(true, 'Chofer encontrado.', ['chofer' => $chofer]);
    }

    // ACCIÓN 3: Registrar Nuevo Chofer (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        if (!$dataInput) {
            $dataInput = $_POST;
        }

        $nombre = isset($dataInput['nombre']) ? trim($dataInput['nombre']) : '';
        $telefono = isset($dataInput['telefono']) ? trim($dataInput['telefono']) : '';

        if (empty($nombre)) {
            sendJsonResponse(false, 'El nombre del chofer es obligatorio.', [], 400);
        }

        $stmtInsert = $pdo->prepare('
            INSERT INTO choferes (nombre, telefono, activo)
            VALUES (:nombre, :telefono, 1)
        ');
        $stmtInsert->execute([
            'nombre' => $nombre,
            'telefono' => $telefono
        ]);
        $choferId = (int)$pdo->lastInsertId();

        sendJsonResponse(true, 'Chofer registrado exitosamente.', [
            'chofer_id' => $choferId
        ]);
    }

    // ACCIÓN 4: Actualizar Chofer (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'actualizar') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        if (!$dataInput) {
            $dataInput = $_POST;
        }

        $id = isset($dataInput['id']) ? (int)$dataInput['id'] : 0;
        $nombre = isset($dataInput['nombre']) ? trim($dataInput['nombre']) : '';
        $telefono = isset($dataInput['telefono']) ? trim($dataInput['telefono']) : '';
        $activo = isset($dataInput['activo']) ? (int)$dataInput['activo'] : 1;

        if ($id <= 0 || empty($nombre)) {
            sendJsonResponse(false, 'Parámetros incompletos para actualizar el chofer.', [], 400);
        }

        $stmtUpdate = $pdo->prepare('
            UPDATE choferes
            SET nombre = :nombre,
                telefono = :telefono,
                activo = :activo
            WHERE id = :id
        ');
        $stmtUpdate->execute([
            'nombre' => $nombre,
            'telefono' => $telefono,
            'activo' => $activo,
            'id' => $id
        ]);

        sendJsonResponse(true, 'Chofer actualizado correctamente.');
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

        $stmt = $pdo->prepare('UPDATE choferes SET activo = :activo WHERE id = :id');
        $stmt->execute(['activo' => $activo, 'id' => $id]);

        sendJsonResponse(true, 'Estado del chofer actualizado.');
    }

    else {
        sendJsonResponse(false, 'Acción o método HTTP no soportado.', [], 400);
    }

} catch (Exception $e) {
    sendJsonResponse(false, 'Error en gestión de choferes: ' . $e->getMessage(), [], 500);
}
