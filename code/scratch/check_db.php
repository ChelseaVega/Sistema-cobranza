<?php
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDatabaseConnection();
    echo "CONEXIÓN A BD: OK\n\n";
    
    // Contar usuarios
    $u = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    echo "Usuarios: $u\n";
    
    // Contar clientes y listarlos
    $c = $pdo->query("SELECT id, nombre_oficial, nombre_despacho_alias FROM clientes")->fetchAll();
    echo "Clientes (" . count($c) . "):\n";
    foreach ($c as $cli) {
        echo "  - ID {$cli['id']}: {$cli['nombre_oficial']} (Alias: {$cli['nombre_despacho_alias']})\n";
    }
    
    // Contar despachos
    $d = $pdo->query("SELECT id, fecha, cliente_id, despachador FROM despachos")->fetchAll();
    echo "Despachos en BD (" . count($d) . "):\n";
    foreach ($d as $desp) {
        $cExists = $pdo->query("SELECT COUNT(*) FROM clientes WHERE id = {$desp['cliente_id']}")->fetchColumn();
        echo "  - ID {$desp['id']}: Fecha {$desp['fecha']}, Cliente ID {$desp['cliente_id']} (¿Existe Cliente?: " . ($cExists ? "SÍ" : "NO") . "), Despachador: {$desp['despachador']}\n";
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
