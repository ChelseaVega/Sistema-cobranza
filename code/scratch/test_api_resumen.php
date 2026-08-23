<?php
session_start();
$_SESSION['usuario_id'] = 1; // Simular sesión iniciada
$_GET['action'] = 'resumen';
$_GET['fecha'] = '2026-08-08';
$_SERVER['REQUEST_METHOD'] = 'GET';

function header($str) {
    echo "HEADER: $str\n";
}

require_once __DIR__ . '/../api/conciliacion.php';
