<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Admin';

require_once __DIR__ . '/../config/database.php';

ob_start();
include __DIR__ . '/../views/dashboard.php';
$html = ob_get_clean();

echo "=== VERIFICANDO RENDERIZADO DEL SELECT DE CHOFERES ===\n\n";

if (strpos($html, '<option value="Gabriel Farias">') !== false && strpos($html, '<option value="Despachador Chacao">') !== false) {
    echo "¡ÉXITO! Las opciones de 'Gabriel Farias' y 'Despachador Chacao' se renderizan directamente en el HTML del servidor.\n";
} else {
    echo "ADVERTENCIA: No se encontraron los choferes en el HTML.\n";
}

// Extraer el bloque del select
if (preg_match('/<select id="dashboard-dispatcher-filter"[^>]*>(.*?)<\/select>/s', $html, $matches)) {
    echo "\nHTML generado para el select:\n";
    echo $matches[0] . "\n";
}
