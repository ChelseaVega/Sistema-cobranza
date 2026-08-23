<?php
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
    echo "CONEXIÓN A BD: OK\n\n";
    
    // Contar usuarios
    $u = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    echo "Usuarios: $u\n";
    
    // Contar choferes y listarlos
    $ch = $pdo->query("SELECT id, nombre, telefono, activo FROM choferes")->fetchAll();
    echo "Choferes (" . count($ch) . "):\n";
    foreach ($ch as $chofer) {
        echo "  - ID {$chofer['id']}: {$chofer['nombre']} (Tel: {$chofer['telefono']}, Activo: {$chofer['activo']})\n";
    }
    
    // Contar despachos
    $d = $pdo->query("SELECT id, fecha, cliente_id, despachador FROM despachos")->fetchAll();
    echo "Despachos en BD (" . count($d) . "):\n";
    foreach ($d as $desp) {
        $cid = $desp['cliente_id'] ? (int)$desp['cliente_id'] : 0;
        $cExists = $cid > 0 ? $pdo->query("SELECT COUNT(*) FROM clientes WHERE id = {$cid}")->fetchColumn() : 0;
        echo "  - ID {$desp['id']}: Fecha {$desp['fecha']}, Cliente ID " . ($desp['cliente_id'] ?: 'NULL') . " (¿Existe Cliente?: " . ($cExists ? "SÍ" : "NO") . "), Despachador: {$desp['despachador']}\n";
    }
    
    // Contar alertas
    $a = $pdo->query("SELECT COUNT(*) FROM alertas_revision")->fetchColumn();
    echo "Alertas en BD: $a\n";
    if ($a > 0) {
        $res = $pdo->query("SELECT fecha, resuelto, COUNT(*) as count FROM alertas_revision GROUP BY fecha, resuelto")->fetchAll();
        foreach ($res as $r) {
            echo "  - Alerta Fecha: {$r['fecha']} (Resuelto: {$r['resuelto']}) -> {$r['count']} registros\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR DE BD: " . $e->getMessage() . "\n";
}
