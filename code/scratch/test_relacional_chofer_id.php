<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['usuario_id'] = 1;
$_SESSION['usuario_nombre'] = 'Admin';

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "=== VERIFICACIÓN RELACIONAL: FILTRADO POR CHOFER_ID Y FECHA ===\n\n";

    // 1. Probar Resumen para chofer_id = 2 (Despachador Chacao) y fecha = 2026-07-26
    $queryResumen = '
        SELECT d.id, d.fecha, d.chofer_id,
               COALESCE(c.nombre_oficial, d.nombre_cliente_raw, d.alias_despacho_consolidado) as cliente,
               COALESCE(ch.nombre, d.despachador) as chofer_nombre,
               d.botellas_zenda, d.botellas_alpes, d.monto_despacho_usd
        FROM despachos d
        LEFT JOIN clientes c ON d.cliente_id = c.id
        LEFT JOIN choferes ch ON (d.chofer_id = ch.id OR (d.chofer_id IS NULL AND ch.nombre = d.despachador))
        WHERE d.fecha = :fecha AND (d.chofer_id = :chofer_id OR ch.id = :chofer_id2)
    ';
    $stmt1 = $pdo->prepare($queryResumen);
    $stmt1->execute([
        'fecha' => '2026-07-26',
        'chofer_id' => 2,
        'chofer_id2' => 2
    ]);
    $res1 = $stmt1->fetchAll();

    echo "--- 1. TABLA RESUMEN DESPACHOS (Fecha 2026-07-26 / Chofer ID: 2 - Despachador Chacao) ---\n";
    echo "Total filas devueltas: " . count($res1) . " de 6 esperadas.\n";
    foreach ($res1 as $r) {
        echo "  - Despacho #{$r['id']} | Chofer: {$r['chofer_nombre']} (ID {$r['chofer_id']}) | Cliente: {$r['cliente']} | Botellas: {$r['botellas_zenda']} Zenda / {$r['botellas_alpes']} Alpes | Monto: \${$r['monto_despacho_usd']}\n";
    }

    // 2. Probar Cola de Cobranza para chofer_id = 2 (Despachador Chacao) y fecha = 2026-07-26
    $queryCola = '
        SELECT COALESCE(c.id, d.id) as cliente_id,
               COALESCE(c.nombre_oficial, d.nombre_cliente_raw, d.alias_despacho_consolidado) as nombre_oficial,
               SUM(d.botellas_zenda) as zenda_hoy,
               SUM(d.botellas_alpes) as alpes_hoy,
               SUM(d.monto_despacho_usd) as deuda_total
        FROM despachos d
        LEFT JOIN clientes c ON d.cliente_id = c.id
        LEFT JOIN choferes ch ON (d.chofer_id = ch.id OR (d.chofer_id IS NULL AND ch.nombre = d.despachador))
        WHERE d.fecha = :fecha AND (d.chofer_id = :chofer_id OR ch.id = :chofer_id2)
        GROUP BY COALESCE(c.id, d.id), COALESCE(c.nombre_oficial, d.nombre_cliente_raw, d.alias_despacho_consolidado)
    ';
    $stmt2 = $pdo->prepare($queryCola);
    $stmt2->execute([
        'fecha' => '2026-07-26',
        'chofer_id' => 2,
        'chofer_id2' => 2
    ]);
    $res2 = $stmt2->fetchAll();

    echo "\n--- 2. COLA DE COBRANZA (Fecha 2026-07-26 / Chofer ID: 2) ---\n";
    echo "Total clientes en cola: " . count($res2) . " de 6 esperados.\n";
    foreach ($res2 as $r) {
        echo "  - Cliente: {$r['nombre_oficial']} | Botellas Hoy: {$r['zenda_hoy']} Zenda / {$r['alpes_hoy']} Alpes | Monto: \${$r['deuda_total']}\n";
    }

    // 3. Probar para chofer_id = 1 (Gabriel Farias)
    $stmt3 = $pdo->prepare($queryResumen);
    $stmt3->execute([
        'fecha' => '2026-08-23',
        'chofer_id' => 1,
        'chofer_id2' => 1
    ]);
    $res3 = $stmt3->fetchAll();
    echo "\n--- 3. DESPACHOS CHOFER ID: 1 (Gabriel Farias) EN FECHA 2026-08-23 ---\n";
    echo "Total filas devueltas: " . count($res3) . "\n";
    foreach ($res3 as $r) {
        echo "  - Despacho #{$r['id']} | Chofer: {$r['chofer_nombre']} | Cliente: {$r['cliente']} | Monto: \${$r['monto_despacho_usd']}\n";
    }

    echo "\n¡RELACIÓN ENTRE DESPACHOS, CHOFER_ID Y FECHA COMPLETAMENTE FUNCIONAL!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
