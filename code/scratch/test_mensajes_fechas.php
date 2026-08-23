<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Admin';

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "--- PROBANDO GENERACIÓN DE MENSAJE CON DESGLOSE DE FECHAS ---\n\n";

    // 1. Asegurar un cliente para la prueba
    $stmtCli = $pdo->prepare('
        INSERT INTO clientes (id, nombre_oficial, nombre_despacho_alias, telefono_whatsapp, categoria, activo)
        VALUES (1, "Pastelería Chacao C.A.", "PASTELERIA CHACAO", "+584121234567", "local", 1)
        ON DUPLICATE KEY UPDATE nombre_oficial = VALUES(nombre_oficial)
    ');
    $stmtCli->execute();

    // 2. Insertar despachos en fechas anteriores y hoy para este cliente
    $pdo->exec("DELETE FROM despachos WHERE cliente_id = 1");

    $stmtDesp = $pdo->prepare('
        INSERT INTO despachos (fecha, cliente_id, despachador, botellas_zenda, botellas_alpes, monto_despacho_usd, estado_pago)
        VALUES (:fecha, 1, :despachador, :zenda, :alpes, :monto, :estado)
    ');

    // Despacho 1: 20/08/2026 - 2 La Zenda
    $stmtDesp->execute([
        'fecha' => '2026-08-20',
        'despachador' => 'Gabriel Farias',
        'zenda' => 2,
        'alpes' => 0,
        'monto' => 14.00,
        'estado' => 'pendiente'
    ]);

    // Despacho 2: 22/08/2026 - 4 Los Alpes
    $stmtDesp->execute([
        'fecha' => '2026-08-22',
        'despachador' => 'Gabriel Farias',
        'zenda' => 0,
        'alpes' => 4,
        'monto' => 12.00,
        'estado' => 'pendiente'
    ]);

    // Despacho 3: HOY 23/08/2026 - 4 Los Alpes
    $stmtDesp->execute([
        'fecha' => '2026-08-23',
        'despachador' => 'Gabriel Farias',
        'zenda' => 0,
        'alpes' => 4,
        'monto' => 12.00,
        'estado' => 'pendiente'
    ]);

    // Actualizar saldos_pendientes
    $pdo->exec('
        INSERT INTO saldos_pendientes (cliente_id, botellas_pendientes_zenda, botellas_pendientes_alpes, monto_deuda_total_usd, ultimo_despacho_fecha)
        VALUES (1, 2, 8, 38.00, "2026-08-23")
        ON DUPLICATE KEY UPDATE botellas_pendientes_zenda = 2, botellas_pendientes_alpes = 8, monto_deuda_total_usd = 38.00, ultimo_despacho_fecha = "2026-08-23"
    ');

    // 3. Probar generación de mensaje a fecha 2026-08-23
    $fechaHoy = '2026-08-23';
    $stmtHist = $pdo->prepare('
        SELECT fecha, botellas_zenda, botellas_alpes
        FROM despachos
        WHERE cliente_id = 1 AND fecha < :fecha AND estado_pago != "pagado"
        ORDER BY fecha ASC
    ');
    $stmtHist->execute(['fecha' => $fechaHoy]);
    $anterioresDetalle = $stmtHist->fetchAll();

    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_GET['action']);
    require_once __DIR__ . '/../api/cobranza.php';

    $msg = generarMensajeCobranza(
        'Pastelería Chacao C.A.',
        true,
        0,
        4, // Recibió hoy 4 Alpes
        2,
        4, // Saldo anterior: 2 Zenda + 4 Alpes
        'Domingo',
        '23/08/2026',
        $anterioresDetalle
    );

    echo "--- MENSAJE GENERADO RESULTANTE ---\n";
    echo $msg . "\n";
    echo "-----------------------------------\n\n";

    // 4. Probar filtrado por fecha 2026-08-23 y chofer Gabriel Farias
    $queryResumen = '
        SELECT d.id, d.fecha, COALESCE(ch.nombre, d.despachador) as chofer, c.nombre_oficial as cliente, d.botellas_alpes, d.monto_despacho_usd
        FROM despachos d
        LEFT JOIN clientes c ON d.cliente_id = c.id
        LEFT JOIN choferes ch ON d.chofer_id = ch.id
        WHERE d.fecha = :fecha AND (d.despachador LIKE :chofer1 OR ch.nombre LIKE :chofer2)
    ';
    $stmt = $pdo->prepare($queryResumen);
    $stmt->execute([
        'fecha' => '2026-08-23',
        'chofer1' => '%Gabriel Farias%',
        'chofer2' => '%Gabriel Farias%'
    ]);
    $resumen = $stmt->fetchAll();

    echo "--- FILTRADO EN DASHBOARD: FECHA 2026-08-23 Y CHOFER 'Gabriel Farias' ---\n";
    echo "Registros encontrados: " . count($resumen) . "\n";
    foreach ($resumen as $r) {
        echo "  - Despacho #{$r['id']}: Fecha {$r['fecha']} | Chofer: {$r['chofer']} | Cliente: {$r['cliente']} | Botellas: {$r['botellas_alpes']} Alpes | Monto: \${$r['monto_despacho_usd']}\n";
    }

    echo "\n¡PRUEBA COMPLETADA CON ÉXITO TOTAL!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
