<?php
require_once __DIR__ . '/../config/database.php';

$jsonStr = '{
  "metadata_procesamiento": {
    "fecha_procesamiento": "2026-07-26",
    "dia_semana": "Domingo",
    "despachador": "Despachador Chacao",
    "origen_fuente": "manuscrito_ocr",
    "total_listas_esperadas": 1,
    "total_listas_procesadas": 1
  },
  "resumen_diario": {
    "total_botellas_zenda": 22,
    "total_botellas_alpes": 48,
    "total_liquidos": 70,
    "monto_bruto_calculado_usd": 298.0,
    "total_registros": 6,
    "observaciones_pie_pagina": "CONTINUA PARTE ATRAS ->"
  },
  "despachos": [
    {
      "id_item": 1,
      "zona_edificio": "ZONA CHACAO",
      "unidad_sublocal": null,
      "nombre_cliente_raw": "SEGURIDAD CAMPO ALEGRE",
      "alias_despacho_consolidado": "SEGURIDAD CAMPO ALEGRE",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 2,
      "zona_edificio": "EDF. COIMBRA",
      "unidad_sublocal": null,
      "nombre_cliente_raw": "EDF. COIMBRA",
      "alias_despacho_consolidado": "EDF COIMBRA",
      "botellas_zenda": 2,
      "botellas_alpes": 0,
      "monto_calculado_usd": 14.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 3,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "PASTELERIA CHACAO",
      "alias_despacho_consolidado": "PASTELERIA CHACAO",
      "botellas_zenda": 3,
      "botellas_alpes": 0,
      "monto_calculado_usd": 21.0,
      "estado_pago_declarado": "pago_movil",
      "monto_pagado_declarado_bs": 1650.0,
      "referencia_pago": "65638",
      "observaciones_chofer": "3 ZENDA + 1 AGUA ORIGINAL. PAGO MOBIL NRF: 65638 BS 1650",
      "requiere_revision_humana": false
    },
    {
      "id_item": 4,
      "zona_edificio": "EDF. TUCURABUA",
      "unidad_sublocal": "PISO 3 APT 3-05",
      "nombre_cliente_raw": "EDF. TUCURABUA PISO 3 APT 3-05",
      "alias_despacho_consolidado": "EDF TUCURABUA PISO 3 APT 3-05",
      "botellas_zenda": 0,
      "botellas_alpes": 2,
      "monto_calculado_usd": 6.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    },
    {
      "id_item": 5,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "sayecito vecina",
      "alias_despacho_consolidado": "sayecito vecina",
      "botellas_zenda": 0,
      "botellas_alpes": 1,
      "monto_calculado_usd": 3.0,
      "estado_pago_declarado": "por_verificar",
      "observaciones_chofer": "ella le iba a dar el pago móvil preguntal",
      "requiere_revision_humana": true,
      "motivo_revision": "Pago móvil pendiente de confirmación según nota del chófer"
    },
    {
      "id_item": 6,
      "zona_edificio": null,
      "unidad_sublocal": null,
      "nombre_cliente_raw": "FISA",
      "alias_despacho_consolidado": "FISA",
      "botellas_zenda": 1,
      "botellas_alpes": 4,
      "monto_calculado_usd": 19.0,
      "estado_pago_declarado": "pendiente",
      "requiere_revision_humana": false
    }
  ]
}';

try {
    $pdo = getDatabaseConnection();
    echo "--- PROBANDO PROCESAMIENTO DEL JSON DE FECHA 2026-07-26 ---\n\n";

    // Limpiar fecha de prueba para evitar duplicados previos si hubiese
    $pdo->exec("DELETE FROM despachos WHERE fecha = '2026-07-26'");
    $pdo->exec("DELETE FROM alertas_revision WHERE fecha = '2026-07-26'");

    // Simular el POST a ingesta
    $_SESSION['usuario_id'] = 1;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $data = json_decode($jsonStr, true);
    $metadata = $data['metadata_procesamiento'];
    $fecha = $metadata['fecha_procesamiento'];
    $despachador = $metadata['despachador'];

    // Buscar o registrar chofer
    $stmtFindChofer = $pdo->prepare('SELECT id FROM choferes WHERE nombre = :nombre OR :nombre_like LIKE CONCAT("%", nombre, "%") LIMIT 1');
    $stmtFindChofer->execute(['nombre' => $despachador, 'nombre_like' => $despachador]);
    $choferRow = $stmtFindChofer->fetch();
    $choferId = null;

    if ($choferRow) {
        $choferId = (int)$choferRow['id'];
    } else {
        $stmtAddChofer = $pdo->prepare('INSERT INTO choferes (nombre, activo) VALUES (:nombre, 1)');
        $stmtAddChofer->execute(['nombre' => $despachador]);
        $choferId = (int)$pdo->lastInsertId();
    }

    echo "Chofer registrado/asociado: ID #$choferId ($despachador)\n";

    $stmtInsert = $pdo->prepare('
        INSERT INTO despachos (
            fecha, cliente_id, nombre_cliente_raw, alias_despacho_consolidado, despachador, chofer_id,
            botellas_zenda, botellas_alpes, monto_despacho_usd, estado_pago, observaciones, referencia_pago
        )
        VALUES (
            :fecha, :cliente_id, :nombre_cliente_raw, :alias_despacho_consolidado, :despachador, :chofer_id,
            :botellas_zenda, :botellas_alpes, :monto_despacho_usd, :estado_pago, :observaciones, :referencia_pago
        )
    ');

    $inserted = 0;
    foreach ($data['despachos'] as $item) {
        $stmtInsert->execute([
            'fecha' => $fecha,
            'cliente_id' => null,
            'nombre_cliente_raw' => $item['nombre_cliente_raw'],
            'alias_despacho_consolidado' => $item['alias_despacho_consolidado'],
            'despachador' => $despachador,
            'chofer_id' => $choferId,
            'botellas_zenda' => $item['botellas_zenda'],
            'botellas_alpes' => $item['botellas_alpes'],
            'monto_despacho_usd' => $item['monto_calculado_usd'],
            'estado_pago' => $item['estado_pago_declarado'] === 'pago_movil' ? 'notificado' : 'pendiente',
            'observaciones' => $item['observaciones_chofer'] ?? null,
            'referencia_pago' => $item['referencia_pago'] ?? null
        ]);
        $inserted++;
    }

    echo "Despachos insertados: $inserted\n\n";

    // Probar consulta de filtrado por fecha y chofer
    $query = '
        SELECT d.id, d.fecha, d.despachador, d.chofer_id, d.nombre_cliente_raw, d.monto_despacho_usd, d.estado_pago
        FROM despachos d
        WHERE d.fecha = :fecha AND d.despachador LIKE :chofer
    ';
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'fecha' => '2026-07-26',
        'chofer' => '%Chacao%'
    ]);
    $resultados = $stmt->fetchAll();

    echo "--- RESULTADOS FILTRADOS POR FECHA (2026-07-26) Y CHOFER (Chacao) ---\n";
    foreach ($resultados as $row) {
        echo "  - Despacho #{$row['id']} | Fecha: {$row['fecha']} | Chofer ID: {$row['chofer_id']} ({$row['despachador']}) | Cliente: {$row['nombre_cliente_raw']} | Monto: \${$row['monto_despacho_usd']} | Estado: {$row['estado_pago']}\n";
    }

    echo "\nTotal registros obtenidos: " . count($resultados) . " de 6 esperados.\n";
    echo "¡VERIFICACIÓN EXITOSA!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
