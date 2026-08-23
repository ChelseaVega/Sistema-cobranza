<?php
// -------------------------------------------------------------
// ENDPOINT: INICIO DE SESIÓN (api/login.php)
// -------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

// Iniciamos la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Permitir solicitudes POST únicamente
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Método HTTP no permitido. Use POST.', [], 405);
}

// Obtener datos del cuerpo de la petición (JSON)
$inputRaw = file_get_contents('php://input');
$data = json_decode($inputRaw, true);

// Si no viene en formato JSON, intentar obtener desde $_POST
if (!$data) {
    $data = $_POST;
}

$usuario = isset($data['usuario']) ? trim($data['usuario']) : '';
$password = isset($data['password']) ? $data['password'] : '';

if (empty($usuario) || empty($password)) {
    sendJsonResponse(false, 'Por favor, ingrese el usuario y la contraseña.', [], 400);
}

try {
    $pdo = getDatabaseConnection();
    
    // Consultar el usuario en la base de datos
    $stmt = $pdo->prepare('SELECT id, usuario, password, nombre FROM usuarios WHERE usuario = :usuario LIMIT 1');
    $stmt->execute(['usuario' => $usuario]);
    $user = $stmt->fetch();
    
    // Verificar si existe y si la contraseña coincide
    if ($user && password_verify($password, $user['password'])) {
        // Guardar datos en la sesión
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_login'] = $user['usuario'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        
        sendJsonResponse(true, 'Inicio de sesión exitoso.', [
            'user' => [
                'id' => $user['id'],
                'usuario' => $user['usuario'],
                'nombre' => $user['nombre']
            ]
        ]);
    } else {
        sendJsonResponse(false, 'Usuario o contraseña incorrectos.', [], 401);
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Error en el servidor: ' . $e->getMessage(), [], 500);
}
