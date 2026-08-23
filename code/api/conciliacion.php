<?php
// -------------------------------------------------------------
// ENDPOINT: CONCILIACIÓN Y ALERTAS (api/conciliacion.php)
// -------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

// Iniciar sesión y validar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    sendJsonResponse(false, 'Acceso denegado. Sesión no iniciada.', [], 401);
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

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
    
    // CASO 1: Listar/Verificar estatus de una fecha (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
        $fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
        if (empty($fecha)) {
            sendJsonResponse(false, 'La fecha es requerida.', [], 400);
        }
        
        // Verificar si ya se importó (si existen despachos guardados para esta fecha)
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM despachos WHERE fecha = :fecha');
        $stmt->execute(['fecha' => $fecha]);
        $totalDespachos = (int)$stmt->fetch()['total'];
        
        $yaConciliado = ($totalDespachos > 0);
        
        // Contar alertas pendientes para esta fecha
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM alertas_revision WHERE fecha = :fecha AND resuelto = 0');
        $stmt->execute(['fecha' => $fecha]);
        $alertasPendientes = (int)$stmt->fetch()['total'];
        
        sendJsonResponse(true, 'Consulta de estatus exitosa.', [
            'tiene_ingesta' => $yaConciliado, // Se unifica: si ya hay despachos, tiene ingesta
            'ya_conciliado' => $yaConciliado,
            'alertas_pendientes' => $alertasPendientes,
            'total_despachos' => $totalDespachos
        ]);
    }
    
    // CASO 2: Obtener resumen de despachos de la jornada (GET)
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'resumen') {
        $fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
        if (empty($fecha)) {
            sendJsonResponse(false, 'La fecha es requerida.', [], 400);
        }

        $despachador = isset($_GET['despachador']) ? trim($_GET['despachador']) : '';

        $query = '
            SELECT d.id, d.fecha, d.cliente_id, d.nombre_cliente_raw, d.alias_despacho_consolidado,
                   COALESCE(c.nombre_oficial, d.nombre_cliente_raw, d.alias_despacho_consolidado) as cliente,
                   COALESCE(ch.nombre, d.despachador) as despachador,
                   d.chofer_id, d.botellas_zenda, d.botellas_alpes, d.monto_despacho_usd,
                   d.estado_pago, fp.nombre_forma as forma_pago
            FROM despachos d
            LEFT JOIN clientes c ON d.cliente_id = c.id
            LEFT JOIN choferes ch ON d.chofer_id = ch.id
            LEFT JOIN formas_pago fp ON d.forma_pago_id = fp.id
            WHERE d.fecha = :fecha
        ';
        $params = ['fecha' => $fecha];

        if ($despachador !== '') {
            $query .= ' AND (d.despachador LIKE :despachador1 OR ch.nombre LIKE :despachador2)';
            $params['despachador1'] = '%' . $despachador . '%';
            $params['despachador2'] = '%' . $despachador . '%';
        }

        $query .= ' ORDER BY d.id ASC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $despachos = $stmt->fetchAll();

        sendJsonResponse(true, 'Resumen obtenido con éxito.', [
            'despachos' => $despachos
        ]);
    }
    
    // CASO 3: Listar Alertas de Revisión (GET)
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'listar_alertas') {
        $estatus = isset($_GET['estatus']) ? trim($_GET['estatus']) : 'pendientes';
        
        $query = 'SELECT id, fecha, nombre_raw, motivo, datos_item, resuelto, fecha_creacion FROM alertas_revision';
        $params = [];
        
        if ($estatus === 'pendientes') {
            $query .= ' WHERE resuelto = 0';
        } elseif ($estatus === 'resueltas') {
            $query .= ' WHERE resuelto = 1';
        }
        
        $query .= ' ORDER BY id DESC';
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $alertas = $stmt->fetchAll();
        
        $stmtClientes = $pdo->query('SELECT id, nombre_oficial, nombre_despacho_alias FROM clientes WHERE activo = 1');
        $clientes = $stmtClientes->fetchAll();
        
        $alertasConSugerencia = [];
        foreach ($alertas as $alerta) {
            $alerta['datos_item'] = json_decode($alerta['datos_item'], true);
            
            $nombreRawNorm = normalizeText($alerta['nombre_raw']);
            $maxSim = 0;
            $bestCandidate = null;
            
            foreach ($clientes as $cliente) {
                $aliasNorm = normalizeText($cliente['nombre_despacho_alias']);
                $oficialNorm = normalizeText($cliente['nombre_oficial']);
                
                similar_text($nombreRawNorm, $aliasNorm, $sim1);
                similar_text($nombreRawNorm, $oficialNorm, $sim2);
                
                $sim = max($sim1, $sim2);
                if ($sim > $maxSim) {
                    $maxSim = $sim;
                    $bestCandidate = $cliente;
                }
            }
            
            $alerta['porcentaje_coincidencia'] = round($maxSim, 2);
            $alerta['cliente_sugerido'] = $bestCandidate ? [
                'id' => $bestCandidate['id'],
                'nombre_oficial' => $bestCandidate['nombre_oficial'],
                'nombre_despacho_alias' => $bestCandidate['nombre_despacho_alias']
            ] : null;
            
            $alertasConSugerencia[] = $alerta;
        }
        
        sendJsonResponse(true, 'Alertas obtenidas con éxito.', [
            'alertas' => $alertasConSugerencia
        ]);
    }
    
    // CASO 4: Resolver Alerta Manualmente vinculando un cliente existente (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'resolver_alerta') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        
        $alertaId = isset($dataInput['alerta_id']) ? (int)$dataInput['alerta_id'] : 0;
        $clienteId = isset($dataInput['cliente_id']) ? (int)$dataInput['cliente_id'] : 0;
        
        if ($alertaId <= 0 || $clienteId <= 0) {
            sendJsonResponse(false, 'Parámetros inválidos.', [], 400);
        }
        
        $pdo->beginTransaction();
        
        $stmtAl = $pdo->prepare('SELECT * FROM alertas_revision WHERE id = :id AND resuelto = 0 LIMIT 1');
        $stmtAl->execute(['id' => $alertaId]);
        $alerta = $stmtAl->fetch();
        
        if (!$alerta) {
            $pdo->rollBack();
            sendJsonResponse(false, 'Alerta no encontrada o ya resuelta.', [], 404);
        }
        
        $stmtCl = $pdo->prepare('SELECT id, nombre_oficial FROM clientes WHERE id = :id LIMIT 1');
        $stmtCl->execute(['id' => $clienteId]);
        if (!$stmtCl->fetch()) {
            $pdo->rollBack();
            sendJsonResponse(false, 'El cliente seleccionado no existe.', [], 404);
        }
        
        $item = json_decode($alerta['datos_item'], true);

        $despachoId = isset($item['despacho_id']) ? (int)$item['despacho_id'] : 0;
        if ($despachoId > 0) {
            $stmtLink = $pdo->prepare('UPDATE despachos SET cliente_id = :cliente_id WHERE id = :id AND cliente_id IS NULL');
            $stmtLink->execute([
                'cliente_id' => $clienteId,
                'id' => $despachoId
            ]);
        }
        
        // Actualizar saldos
        $stmtCheckSaldo = $pdo->prepare('SELECT COUNT(*) as total FROM saldos_pendientes WHERE cliente_id = :cliente_id');
        $stmtCheckSaldo->execute(['cliente_id' => $clienteId]);
        $existeSaldo = ((int)$stmtCheckSaldo->fetch()['total'] > 0);
        
        if ($existeSaldo) {
            $stmtUpdateSaldo = $pdo->prepare('
                UPDATE saldos_pendientes 
                SET botellas_pendientes_zenda = botellas_pendientes_zenda + :zenda,
                    botellas_pendientes_alpes = botellas_pendientes_alpes + :alpes,
                    monto_deuda_total_usd = monto_deuda_total_usd + :monto,
                    ultimo_despacho_fecha = :fecha
                WHERE cliente_id = :cliente_id
            ');
            $stmtUpdateSaldo->execute([
                'zenda' => $item['botellas_zenda'],
                'alpes' => $item['botellas_alpes'],
                'monto' => $item['monto_calculado_usd'],
                'fecha' => $alerta['fecha'],
                'cliente_id' => $clienteId
            ]);
        } else {
            $stmtInsertSaldo = $pdo->prepare('
                INSERT INTO saldos_pendientes (cliente_id, botellas_pendientes_zenda, botellas_pendientes_alpes, monto_deuda_total_usd, ultimo_despacho_fecha)
                VALUES (:cliente_id, :zenda, :alpes, :monto, :fecha)
            ');
            $stmtInsertSaldo->execute([
                'cliente_id' => $clienteId,
                'zenda' => $item['botellas_zenda'],
                'alpes' => $item['botellas_alpes'],
                'monto' => $item['monto_calculado_usd'],
                'fecha' => $alerta['fecha']
            ]);
        }
        
        $stmtResolve = $pdo->prepare('UPDATE alertas_revision SET resuelto = 1 WHERE id = :id');
        $stmtResolve->execute(['id' => $alertaId]);
        
        $pdo->commit();
        
        sendJsonResponse(true, 'Alerta resuelta y asociada correctamente al cliente.');
    }
    
    // CASO 5: Crear Cliente Oficial y Resolver Alerta (POST - Puntos 2 y 6)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear_cliente_y_resolver') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        
        $alertaId = isset($dataInput['alerta_id']) ? (int)$dataInput['alerta_id'] : 0;
        $nombreOficial = isset($dataInput['nombre_oficial']) ? trim($dataInput['nombre_oficial']) : '';
        $aliasDespacho = isset($dataInput['nombre_despacho_alias']) ? trim($dataInput['nombre_despacho_alias']) : '';
        $telefono = isset($dataInput['telefono_whatsapp']) ? trim($dataInput['telefono_whatsapp']) : '';
        $categoria = isset($dataInput['categoria']) ? trim($dataInput['categoria']) : 'local';
        
        if ($alertaId <= 0 || empty($nombreOficial) || empty($aliasDespacho) || empty($telefono)) {
            sendJsonResponse(false, 'Por favor complete todos los datos requeridos del cliente.', [], 400);
        }
        
        if (!in_array($categoria, ['domicilio', 'local', 'facturacion_legal'])) {
            sendJsonResponse(false, 'Categoría de cliente inválida.', [], 400);
        }
        
        // -------------------------------------------------------------
        // VALIDACIÓN DE ALIAS ÚNICO (Punto 6)
        // -------------------------------------------------------------
        $stmtCheckAlias = $pdo->prepare('SELECT COUNT(*) as total FROM clientes WHERE nombre_despacho_alias = :alias');
        $stmtCheckAlias->execute(['alias' => $aliasDespacho]);
        $totalAlias = (int)$stmtCheckAlias->fetch()['total'];
        
        if ($totalAlias > 0) {
            sendJsonResponse(false, "El alias de despacho '{$aliasDespacho}' ya está registrado y asociado a otro cliente. Cada alias debe ser único.", [], 400);
        }
        
        // Iniciar transacción de creación e hilado
        $pdo->beginTransaction();
        
        // 1. Consultar la alerta
        $stmtAl = $pdo->prepare('SELECT * FROM alertas_revision WHERE id = :id AND resuelto = 0 LIMIT 1');
        $stmtAl->execute(['id' => $alertaId]);
        $alerta = $stmtAl->fetch();
        
        if (!$alerta) {
            $pdo->rollBack();
            sendJsonResponse(false, 'La alerta no existe o ya fue resuelta.', [], 404);
        }
        
        $item = json_decode($alerta['datos_item'], true);
        
        // 2. Insertar nuevo cliente
        $stmtInsertCliente = $pdo->prepare('
            INSERT INTO clientes (nombre_oficial, nombre_despacho_alias, telefono_whatsapp, categoria, activo)
            VALUES (:oficial, :alias, :tel, :cat, 1)
        ');
        $stmtInsertCliente->execute([
            'oficial' => $nombreOficial,
            'alias' => $aliasDespacho,
            'tel' => $telefono,
            'cat' => $categoria
        ]);
        
        $clienteId = $pdo->lastInsertId();
        
        // 3. Crear registro inicial vacío en saldos_pendientes
        $stmtInsertSaldoInit = $pdo->prepare('
            INSERT INTO saldos_pendientes (cliente_id, botellas_pendientes_zenda, botellas_pendientes_alpes, monto_deuda_total_usd, ultimo_despacho_fecha)
            VALUES (:cliente_id, 0, 0, 0.00, NULL)
        ');
        $stmtInsertSaldoInit->execute(['cliente_id' => $clienteId]);
        
        $despachoId = isset($item['despacho_id']) ? (int)$item['despacho_id'] : 0;
        if ($despachoId > 0) {
            $stmtLink = $pdo->prepare('UPDATE despachos SET cliente_id = :cliente_id WHERE id = :id');
            $stmtLink->execute([
                'cliente_id' => $clienteId,
                'id' => $despachoId
            ]);
        }
        
        // 5. Cargar las botellas y monto en saldos_pendientes del cliente
        $stmtUpdateSaldo = $pdo->prepare('
            UPDATE saldos_pendientes 
            SET botellas_pendientes_zenda = :zenda,
                botellas_pendientes_alpes = :alpes,
                monto_deuda_total_usd = :monto,
                ultimo_despacho_fecha = :fecha
            WHERE cliente_id = :cliente_id
        ');
        $stmtUpdateSaldo->execute([
            'zenda' => $item['botellas_zenda'],
            'alpes' => $item['botellas_alpes'],
            'monto' => $item['monto_calculado_usd'],
            'fecha' => $alerta['fecha'],
            'cliente_id' => $clienteId
        ]);
        
        // 6. Marcar alerta como resuelta
        $stmtResolve = $pdo->prepare('UPDATE alertas_revision SET resuelto = 1 WHERE id = :id');
        $stmtResolve->execute(['id' => $alertaId]);
        
        $pdo->commit();
        
        sendJsonResponse(true, 'Cliente creado con éxito y alerta resuelta de forma unificada.', [
            'cliente_id' => $clienteId
        ]);
    }
    
    else {
        sendJsonResponse(false, 'Acción o método no soportado.', [], 400);
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(false, 'Error en conciliación: ' . $e->getMessage(), [], 500);
}
