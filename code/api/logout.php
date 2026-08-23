<?php
// -------------------------------------------------------------
// ENDPOINT: CERRAR SESIÓN (api/logout.php)
// -------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

// Iniciamos la sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpiar todas las variables de sesión
$_SESSION = [];

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión físicamente
session_destroy();

sendJsonResponse(true, 'Sesión cerrada correctamente.');
