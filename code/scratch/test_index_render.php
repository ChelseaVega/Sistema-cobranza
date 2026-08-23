<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Admin';

$_GET['view'] = 'dashboard';

ob_start();
include __DIR__ . '/../index.php';
$output = ob_get_clean();

echo "=== VERIFICANDO CARGA DE INDEX.PHP ===\n\n";

if (strpos($output, 'Fatal error') !== false || strpos($output, 'Uncaught Error') !== false) {
    echo "ERROR: Se detectó un error fatal al renderizar index.php:\n";
    echo substr($output, 0, 500) . "\n";
} else {
    echo "¡ÉXITO TOTAL! index.php se renderizó limpiamente sin errores.\n";
    echo "Longitud HTML generado: " . strlen($output) . " bytes.\n";
    if (strpos($output, '<select id="dashboard-dispatcher-filter"') !== false) {
        echo "Selector de choferes presente en el HTML: SÍ.\n";
    }
}
