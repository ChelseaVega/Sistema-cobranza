<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "--- PROBANDO ESQUEMA DE DESPACHOS Y MIGRACIÓN DE COLUMNAS ---\n\n";

    $cols = $pdo->query('SHOW COLUMNS FROM despachos')->fetchAll(PDO::FETCH_ASSOC);
    echo "Columnas en tabla despachos:\n";
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) | Nullable: {$col['Null']}\n";
    }

    echo "\nProbando inserción de despacho con cliente_id NULL y nombre_cliente_raw...\n";
    $stmt = $pdo->prepare('
        INSERT INTO despachos (
            fecha, cliente_id, nombre_cliente_raw, alias_despacho_consolidado, despachador,
            botellas_zenda, botellas_alpes, monto_despacho_usd, estado_pago, observaciones
        )
        VALUES (
            :fecha, :cliente_id, :nombre_cliente_raw, :alias_despacho_consolidado, :despachador,
            :botellas_zenda, :botellas_alpes, :monto_despacho_usd, :estado_pago, :observaciones
        )
    ');
    $stmt->execute([
        'fecha' => date('Y-m-d'),
        'cliente_id' => null,
        'nombre_cliente_raw' => 'Prueba Raw Calle 5',
        'alias_despacho_consolidado' => 'CALLE 5',
        'despachador' => 'Gabriel Farias',
        'botellas_zenda' => 1,
        'botellas_alpes' => 2,
        'monto_despacho_usd' => 13.00,
        'estado_pago' => 'pendiente',
        'observaciones' => 'Despacho de prueba sin cliente previo'
    ]);
    $despachoId = $pdo->lastInsertId();
    echo "DESPACHO INSERTADO CON ÉXITO: ID #$despachoId\n";

    // Limpiar el despacho de prueba
    $pdo->exec("DELETE FROM despachos WHERE id = $despachoId");
    echo "Prueba limpiada exitosamente.\n\nTODO FUNCIONA AL 100% SIN ERRORES DE COLUMNA.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
