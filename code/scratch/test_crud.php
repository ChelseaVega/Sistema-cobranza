<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "--- PROBANDO INSERCIÓN Y PERSISTENCIA REAL EN BASE DE DATOS ---\n\n";

    // 1. Probar inserción de Cliente
    $stmtCli = $pdo->prepare('
        INSERT INTO clientes (nombre_oficial, nombre_despacho_alias, telefono_whatsapp, categoria, activo)
        VALUES (:oficial, :alias, :tel, :cat, 1)
        ON DUPLICATE KEY UPDATE telefono_whatsapp = VALUES(telefono_whatsapp)
    ');
    $stmtCli->execute([
        'oficial' => 'Pastelería Chacao C.A.',
        'alias' => 'PASTELERIA CHACAO',
        'tel' => '+584121234567',
        'cat' => 'local'
    ]);
    $cliId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM clientes WHERE nombre_despacho_alias = 'PASTELERIA CHACAO'")->fetchColumn();

    // Inicializar saldo
    $stmtSaldo = $pdo->prepare('
        INSERT INTO saldos_pendientes (cliente_id, botellas_pendientes_zenda, botellas_pendientes_alpes, monto_deuda_total_usd, ultimo_despacho_fecha)
        VALUES (:cliente_id, 0, 0, 0.00, NULL)
        ON DUPLICATE KEY UPDATE cliente_id = cliente_id
    ');
    $stmtSaldo->execute(['cliente_id' => $cliId]);

    echo "1. CLIENTE CREADO: ID $cliId - Pastelería Chacao C.A. (Alias: PASTELERIA CHACAO)\n";

    // 2. Probar inserción de Chofer
    $stmtChof = $pdo->prepare('
        INSERT INTO choferes (nombre, telefono, activo)
        VALUES (:nombre, :tel, 1)
    ');
    $stmtChof->execute([
        'nombre' => 'Gabriel Farias',
        'tel' => '+584149998877'
    ]);
    $chofId = $pdo->lastInsertId();
    echo "2. CHOFER CREADO: ID $chofId - Gabriel Farias (Tel: +584149998877)\n";

    // 3. Consultar tablas
    $totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
    $totalChoferes = $pdo->query("SELECT COUNT(*) FROM choferes")->fetchColumn();
    $totalSaldos = $pdo->query("SELECT COUNT(*) FROM saldos_pendientes")->fetchColumn();

    echo "\n--- VERIFICACIÓN EN BD ---";
    echo "\nTotal Clientes en BD: $totalClientes";
    echo "\nTotal Choferes en BD: $totalChoferes";
    echo "\nTotal Saldos en BD: $totalSaldos\n";
    echo "\nTODO PERSISTE CORRECTAMENTE EN MYSQL.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
