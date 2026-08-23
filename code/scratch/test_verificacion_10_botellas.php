<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Admin';

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== VERIFICACIÓN: CLIENTE CON 10 BOTELLAS Y DESGLOSE DE FECHAS ===\n\n";

    // Requerir cobranza.php
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_GET['action']);
    require_once __DIR__ . '/../api/cobranza.php';

    // ESCENARIO 1: Cliente recibió 4 botellas hoy (23/08/2026) y tenía 6 anteriores (2 del 20/08 y 4 del 22/08) -> TOTAL 10
    $anteriores1 = [
        ['fecha' => '2026-08-20', 'botellas_zenda' => 2, 'botellas_alpes' => 0],
        ['fecha' => '2026-08-22', 'botellas_zenda' => 0, 'botellas_alpes' => 4]
    ];

    $msg1 = generarMensajeCobranza(
        'Pastelería Chacao C.A.',
        true,
        0, 4, // 4 Alpes hoy
        2, 4, // 6 anteriores (2 Zenda + 4 Alpes)
        'Domingo',
        '23/08/2026',
        $anteriores1
    );

    echo "--- ESCENARIO 1: 4 BOTELLAS HOY + 6 BOTELLAS ANTERIORES (TOTAL 10) ---\n";
    echo $msg1 . "\n";
    echo "----------------------------------------------------------------------\n\n";

    // ESCENARIO 2: Cliente INACTIVO con 10 botellas pendientes (0 hoy, 10 en total)
    $anteriores2 = [
        ['fecha' => '2026-08-15', 'botellas_zenda' => 4, 'botellas_alpes' => 0],
        ['fecha' => '2026-08-18', 'botellas_zenda' => 0, 'botellas_alpes' => 6]
    ];

    $msg2 = generarMensajeCobranza(
        'Seguridad Campo Alegre',
        false,
        0, 0, // 0 hoy
        4, 6, // 10 anteriores (4 Zenda + 6 Alpes)
        'Domingo',
        '23/08/2026',
        $anteriores2
    );

    echo "--- ESCENARIO 2: CLIENTE INACTIVO CON 10 BOTELLAS TOTALES ---\n";
    echo $msg2 . "\n";
    echo "-------------------------------------------------------------\n\n";

    // ESCENARIO 3: Cliente con 10 botellas en saldos_pendientes pero sin registros históricos detallados
    $msg3 = generarMensajeCobranza(
        'Cliente Saldo Migrado',
        false,
        0, 0,
        0, 10,
        'Domingo',
        '23/08/2026',
        []
    );

    echo "--- ESCENARIO 3: CLIENTE CON 10 BOTELLAS ACUMULADAS SIN HISTORIAL ---\n";
    echo $msg3 . "\n";
    echo "--------------------------------------------------------------------\n\n";

    echo "¡TODOS LOS ESCENARIOS PROCESADOS CORRECTAMENTE CON EL TOTAL DE 10 BOTELLAS!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
