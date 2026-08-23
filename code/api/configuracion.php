<?php
// -------------------------------------------------------------
// ENDPOINT: CONFIGURACIÓN DE CATÁLOGOS (api/configuracion.php)
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

try {
    $pdo = getDatabaseConnection();
    
    // ACCIÓN 1: Obtener catálogos completos (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($action === 'listar' || empty($action))) {
        // Marcas de Agua
        $stmtMarcas = $pdo->query('SELECT id, nombre_marca, codigo_identificador, precio_usd, activo FROM marcas_agua');
        $marcas = $stmtMarcas->fetchAll();
        
        // Formas de Pago
        $stmtFormas = $pdo->query('SELECT id, nombre_forma, codigo_identificador, requiere_referencia, moneda_defecto, activo FROM formas_pago');
        $formas = $stmtFormas->fetchAll();
        
        sendJsonResponse(true, 'Catálogos obtenidos con éxito.', [
            'marcas_agua' => $marcas,
            'formas_pago' => $formas
        ]);
    }
    
    // ACCIÓN 2: Actualizar precios de marcas de agua (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'guardar_precios') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        
        if (!$dataInput || !isset($dataInput['precios']) || !is_array($dataInput['precios'])) {
            sendJsonResponse(false, 'Estructura de precios inválida.', [], 400);
        }
        
        $pdo->beginTransaction();
        
        $stmtUpdate = $pdo->prepare('UPDATE marcas_agua SET precio_usd = :precio WHERE id = :id');
        
        foreach ($dataInput['precios'] as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $precio = isset($item['precio_usd']) ? (float)$item['precio_usd'] : -1.0;
            
            if ($id <= 0 || $precio < 0.0) {
                $pdo->rollBack();
                sendJsonResponse(false, 'Parámetros de precio inválidos.', [], 400);
            }
            
            $stmtUpdate->execute([
                'precio' => $precio,
                'id' => $id
            ]);
        }
        
        $pdo->commit();
        sendJsonResponse(true, 'Precios actualizados correctamente.');
    }
    
    // ACCIÓN 3: Alternar estatus de métodos de pago (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_forma_pago') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        
        $id = isset($dataInput['id']) ? (int)$dataInput['id'] : 0;
        $activo = isset($dataInput['activo']) ? (bool)$dataInput['activo'] : false;
        
        if ($id <= 0) {
            sendJsonResponse(false, 'Parámetro ID inválido.', [], 400);
        }
        
        $stmtUpdate = $pdo->prepare('UPDATE formas_pago SET activo = :activo WHERE id = :id');
        $stmtUpdate->execute([
            'activo' => $activo ? 1 : 0,
            'id' => $id
        ]);
        
        sendJsonResponse(true, 'Estado del método de pago actualizado.');
    }
    
    else {
        sendJsonResponse(false, 'Acción o método no soportado.', [], 400);
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(false, 'Error en configuración: ' . $e->getMessage(), [], 500);
}
