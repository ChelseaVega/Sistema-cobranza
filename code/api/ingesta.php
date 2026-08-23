<?php
// -------------------------------------------------------------
// ENDPOINT: PROCESAR E IMPORTAR INGESTA (api/ingesta.php)
// -------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

// Iniciar sesión y validar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    sendJsonResponse(false, 'Acceso denegado. Sesión no iniciada.', [], 401);
}

// Permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Método HTTP no permitido. Use POST.', [], 405);
}

// Directorio para guardar las ingestas procesadas (copia de respaldo)
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Obtener el JSON de la petición
$inputRaw = '';
if (isset($_FILES['json_file'])) {
    $inputRaw = file_get_contents($_FILES['json_file']['tmp_name']);
} else {
    $inputRaw = file_get_contents('php://input');
}

$data = json_decode($inputRaw, true);

if (!$data) {
    sendJsonResponse(false, 'Archivo JSON inválido o vacío.', [], 400);
}

// Validar estructura básica
if (!isset($data['metadata_procesamiento']) || !isset($data['despachos']) || !is_array($data['despachos'])) {
    sendJsonResponse(false, 'La estructura del JSON no cumple con las especificaciones del sistema.', [], 400);
}

$metadata = $data['metadata_procesamiento'];
$fecha = isset($metadata['fecha_procesamiento']) ? trim($metadata['fecha_procesamiento']) : '';
$despachador = isset($metadata['despachador']) ? trim($metadata['despachador']) : '';

if (empty($fecha) || empty($despachador)) {
    sendJsonResponse(false, 'La fecha y el despachador son requeridos en el JSON.', [], 400);
}

// Función auxiliar para normalizar textos para comparación difusa
function normalizeText($str) {
    $str = mb_strtoupper($str, 'UTF-8');
    $unwanted = [
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C',
        'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a',
        'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
        'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y',
        'ÿ'=>'y', '.'=>' ', '_'=>' ', '-'=>' ', ','=>' '
    ];
    $str = strtr($str, $unwanted);
    $str = preg_replace('/[^A-Z0-9 ]/', ' ', $str);
    $str = preg_replace('/\s+/', ' ', $str);
    return trim($str);
}

try {
    $pdo = getDatabaseConnection();
    
    // -------------------------------------------------------------
    // CONTROL DE DUPLICADOS (Punto 5)
    // -------------------------------------------------------------
    // Comprobar si ya existen registros para esta fecha y chofer
    $stmtCheck = $pdo->prepare('SELECT COUNT(*) as total FROM despachos WHERE fecha = :fecha AND despachador = :despachador');
    $stmtCheck->execute([
        'fecha' => $fecha,
        'despachador' => $despachador
    ]);
    $totalExistentes = (int)$stmtCheck->fetch()['total'];
    
    if ($totalExistentes > 0) {
        sendJsonResponse(false, "Los despachos para la fecha {$fecha} y el despachador '{$despachador}' ya fueron importados. No se permiten registros duplicados.", [], 400);
    }
    
    // Obtener precios vigentes de la tabla marcas_agua
    $stmtPrecios = $pdo->query('SELECT codigo_identificador, precio_usd FROM marcas_agua WHERE activo = 1');
    $preciosRaw = $stmtPrecios->fetchAll();
    
    $precios = [];
    foreach ($preciosRaw as $row) {
        $precios[$row['codigo_identificador']] = (float)$row['precio_usd'];
    }
    
    $precioZenda = isset($precios['zenda']) ? $precios['zenda'] : 7.00;
    $precioAlpes = isset($precios['alpes']) ? $precios['alpes'] : 3.00;
    
    // Obtener catálogo de clientes activos para Fuzzy Matching
    $stmtClientes = $pdo->query('SELECT id, nombre_oficial, nombre_despacho_alias, categoria FROM clientes WHERE activo = 1');
    $clientes = $stmtClientes->fetchAll();
    
    // Preparar sentencias preparadas para inserción en BD
    $stmtInsertDespacho = $pdo->prepare('
        INSERT INTO despachos (
            fecha, cliente_id, nombre_cliente_raw, alias_despacho_consolidado, despachador,
            botellas_zenda, botellas_alpes, monto_despacho_usd, estado_pago, observaciones, referencia_pago
        )
        VALUES (
            :fecha, :cliente_id, :nombre_cliente_raw, :alias_despacho_consolidado, :despachador,
            :botellas_zenda, :botellas_alpes, :monto_despacho_usd, :estado_pago, :observaciones, :referencia_pago
        )
    ');
    
    $stmtInsertAlerta = $pdo->prepare('
        INSERT INTO alertas_revision (fecha, nombre_raw, motivo, datos_item)
        VALUES (:fecha, :nombre_raw, :motivo, :datos_item)
    ');
    
    $stmtCheckSaldo = $pdo->prepare('SELECT COUNT(*) as total FROM saldos_pendientes WHERE cliente_id = :cliente_id');
    $stmtInsertSaldo = $pdo->prepare('
        INSERT INTO saldos_pendientes (cliente_id, botellas_pendientes_zenda, botellas_pendientes_alpes, monto_deuda_total_usd, ultimo_despacho_fecha)
        VALUES (:cliente_id, :zenda, :alpes, :monto, :fecha)
    ');
    $stmtUpdateSaldo = $pdo->prepare('
        UPDATE saldos_pendientes 
        SET botellas_pendientes_zenda = botellas_pendientes_zenda + :zenda,
            botellas_pendientes_alpes = botellas_pendientes_alpes + :alpes,
            monto_deuda_total_usd = monto_deuda_total_usd + :monto,
            ultimo_despacho_fecha = :fecha
        WHERE cliente_id = :cliente_id
    ');
    
    // Iniciar transacción SQL atómica
    $pdo->beginTransaction();
    
    $totalZenda = 0;
    $totalAlpes = 0;
    $totalLiquidos = 0;
    $montoBrutoCalculado = 0.0;
    
    $despachosProcesados = [];
    $insertados = 0;
    $alertados = 0;
    
    foreach ($data['despachos'] as $index => $item) {
        $idItem = isset($item['id_item']) ? (int)$item['id_item'] : ($index + 1);
        $nombreRaw = isset($item['nombre_cliente_raw']) ? trim($item['nombre_cliente_raw']) : '';
        $aliasConsolidado = isset($item['alias_despacho_consolidado']) ? trim($item['alias_despacho_consolidado']) : '';
        
        // Asignación por defecto: si no se especifica marca, va a Los Alpes
        $botellasZenda = isset($item['botellas_zenda']) ? (int)$item['botellas_zenda'] : 0;
        $botellasAlpes = 0;
        if (isset($item['botellas_alpes'])) {
            $botellasAlpes = (int)$item['botellas_alpes'];
        } elseif (isset($item['botellas'])) {
            $botellasAlpes = (int)$item['botellas'];
        }
        
        // Recalcular monto
        $montoCalculado = ($botellasZenda * $precioZenda) + ($botellasAlpes * $precioAlpes);
        
        $estadoPago = isset($item['estado_pago_declarado']) ? trim($item['estado_pago_declarado']) : 'pendiente';
        
        $totalZenda += $botellasZenda;
        $totalAlpes += $botellasAlpes;
        $totalLiquidos += ($botellasZenda + $botellasAlpes);
        $montoBrutoCalculado += $montoCalculado;
        
        $itemProcesado = [
            'id_item' => $idItem,
            'zona_edificio' => isset($item['zona_edificio']) ? $item['zona_edificio'] : null,
            'unidad_sublocal' => isset($item['unidad_sublocal']) ? $item['unidad_sublocal'] : null,
            'nombre_cliente_raw' => $nombreRaw,
            'alias_despacho_consolidado' => $aliasConsolidado,
            'botellas_zenda' => $botellasZenda,
            'botellas_alpes' => $botellasAlpes,
            'monto_calculado_usd' => $montoCalculado,
            'estado_pago_declarado' => $estadoPago,
            'monto_pagado_declarado_bs' => isset($item['monto_pagado_declarado_bs']) ? (float)$item['monto_pagado_declarado_bs'] : null,
            'monto_pagado_declarado_usd' => isset($item['monto_pagado_declarado_usd']) ? (float)$item['monto_pagado_declarado_usd'] : null,
            'referencia_pago' => isset($item['referencia_pago']) ? $item['referencia_pago'] : null,
            'observaciones_chofer' => isset($item['observaciones_chofer']) ? $item['observaciones_chofer'] : null,
            'requiere_revision_humana' => isset($item['requiere_revision_humana']) ? (bool)$item['requiere_revision_humana'] : false,
            'motivo_revision' => isset($item['motivo_revision']) ? $item['motivo_revision'] : null,
            'despachador' => $despachador
        ];
        
        // -------------------------------------------------------------
        // FUZZY MATCHING (EMPAREJAMIENTO DIRECTO AL IMPORTAR - Punto 1 y 4)
        // -------------------------------------------------------------
        $textoComparar = !empty($aliasConsolidado) ? $aliasConsolidado : $nombreRaw;
        $textoCompararNorm = normalizeText($textoComparar);
        
        $maxSim = 0.0;
        $matchedCliente = null;
        
        foreach ($clientes as $cliente) {
            $aliasNorm = normalizeText($cliente['nombre_despacho_alias']);
            $oficialNorm = normalizeText($cliente['nombre_oficial']);
            
            similar_text($textoCompararNorm, $aliasNorm, $sim1);
            similar_text($textoCompararNorm, $oficialNorm, $sim2);
            
            $sim = max($sim1, $sim2);
            if ($sim > $maxSim) {
                $maxSim = $sim;
                $matchedCliente = $cliente;
            }
        }
        
        $matchedId = ($maxSim >= 85.0 && $matchedCliente !== null) ? (int)$matchedCliente['id'] : null;

        $stmtInsertDespacho->execute([
            'fecha' => $fecha,
            'cliente_id' => $matchedId,
            'nombre_cliente_raw' => $nombreRaw !== '' ? $nombreRaw : $textoComparar,
            'alias_despacho_consolidado' => $aliasConsolidado !== '' ? $aliasConsolidado : $nombreRaw,
            'despachador' => $despachador,
            'botellas_zenda' => $botellasZenda,
            'botellas_alpes' => $botellasAlpes,
            'monto_despacho_usd' => $montoCalculado,
            'estado_pago' => 'pendiente',
            'observaciones' => $itemProcesado['observaciones_chofer'],
            'referencia_pago' => $itemProcesado['referencia_pago']
        ]);
        $insertados++;
        $itemProcesado['despacho_id'] = (int)$pdo->lastInsertId();

        if ($matchedId !== null) {
            $stmtCheckSaldo->execute(['cliente_id' => $matchedId]);
            $existeSaldo = ((int)$stmtCheckSaldo->fetch()['total'] > 0);

            if ($existeSaldo) {
                $stmtUpdateSaldo->execute([
                    'zenda' => $botellasZenda,
                    'alpes' => $botellasAlpes,
                    'monto' => $montoCalculado,
                    'fecha' => $fecha,
                    'cliente_id' => $matchedId
                ]);
            } else {
                $stmtInsertSaldo->execute([
                    'cliente_id' => $matchedId,
                    'zenda' => $botellasZenda,
                    'alpes' => $botellasAlpes,
                    'monto' => $montoCalculado,
                    'fecha' => $fecha
                ]);
            }
        } else {
            $stmtInsertAlerta->execute([
                'fecha' => $fecha,
                'nombre_raw' => $textoComparar,
                'motivo' => 'MATCH_AMBIGUO_O_NO_ENCONTRADO',
                'datos_item' => json_encode($itemProcesado, JSON_UNESCAPED_UNICODE)
            ]);

            $itemProcesado['requiere_revision_humana'] = true;
            $itemProcesado['motivo_revision'] = 'Despacho guardado. Nombre no catalogado (similitud < 85%).';
            $alertados++;
        }
        
        $despachosProcesados[] = $itemProcesado;
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // Guardar una copia local del JSON (respaldo)
    $jsonFinal = [
        'metadata_procesamiento' => [
            'fecha_procesamiento' => $fecha,
            'dia_semana' => isset($metadata['dia_semana']) ? $metadata['dia_semana'] : 'Lunes',
            'despachador' => $despachador,
            'origen_fuente' => isset($metadata['origen_fuente']) ? $metadata['origen_fuente'] : 'mixto',
            'total_listas_esperadas' => isset($metadata['total_listas_esperadas']) ? (int)$metadata['total_listas_esperadas'] : 1,
            'total_listas_procesadas' => isset($metadata['total_listas_procesadas']) ? (int)$metadata['total_listas_procesadas'] : 1
        ],
        'resumen_diario' => [
            'total_botellas_zenda' => $totalZenda,
            'total_botellas_alpes' => $totalAlpes,
            'total_liquidos' => $totalLiquidos,
            'monto_bruto_calculado_usd' => $montoBrutoCalculado,
            'total_registros' => count($despachosProcesados),
            'observaciones_pie_pagina' => isset($data['resumen_diario']['observaciones_pie_pagina']) ? $data['resumen_diario']['observaciones_pie_pagina'] : ''
        ],
        'despachos' => $despachosProcesados
    ];
    
    $fileName = $dataDir . '/ingesta_' . $fecha . '.json';
    file_put_contents($fileName, json_encode($jsonFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    sendJsonResponse(true, 'Ingesta importada: todos los despachos se guardaron en la tabla despachos.', [
        'despachos_guardados' => $insertados,
        'alertas_generadas' => $alertados,
        'json_procesado' => $jsonFinal
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(false, 'Error al importar ingesta: ' . $e->getMessage(), [], 500);
}
