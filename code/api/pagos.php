<?php
// -------------------------------------------------------------
// ENDPOINT: REGISTRO DE PAGOS Y CONCILIACIÓN FIFO (api/pagos.php)
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

try {
    $pdo = getDatabaseConnection();
    
    // ACCIÓN 1: Listar formas de pago activas (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'listar_formas_pago') {
        $stmt = $pdo->query('SELECT id, nombre_forma, requiere_referencia, moneda_defecto FROM formas_pago WHERE activo = 1');
        $formas = $stmt->fetchAll();
        sendJsonResponse(true, 'Métodos de pago obtenidos.', ['formas_pago' => $formas]);
    }
    
    // ACCIÓN 2: Listar saldos de clientes y cartera (GET)
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'listar_saldos') {
        $stmt = $pdo->query('
            SELECT s.cliente_id, c.nombre_oficial, c.nombre_despacho_alias, c.telefono_whatsapp, c.categoria,
                   s.botellas_pendientes_zenda, s.botellas_pendientes_alpes, s.monto_deuda_total_usd,
                   s.ultimo_despacho_fecha, s.fecha_actualizacion
            FROM saldos_pendientes s
            JOIN clientes c ON s.cliente_id = c.id
            ORDER BY s.monto_deuda_total_usd DESC, c.nombre_oficial ASC
        ');
        $saldos = $stmt->fetchAll();
        sendJsonResponse(true, 'Cartera de clientes obtenida.', ['saldos' => $saldos]);
    }
    
    // ACCIÓN 3: Registrar un Pago y Reconciliar Cartera FIFO (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        
        // Si no viene como JSON, usar $_POST
        if (!$dataInput) {
            $dataInput = $_POST;
        }
        
        $clienteId = isset($dataInput['cliente_id']) ? (int)$dataInput['cliente_id'] : 0;
        $formaPagoId = isset($dataInput['forma_pago_id']) ? (int)$dataInput['forma_pago_id'] : 0;
        $referencia = isset($dataInput['referencia_bancaria']) ? trim($dataInput['referencia_bancaria']) : '';
        $montoBs = isset($dataInput['monto_cancelado_bs']) ? (float)$dataInput['monto_cancelado_bs'] : null;
        $equivalenteUsd = isset($dataInput['equivalente_aproximado_usd']) ? (float)$dataInput['equivalente_aproximado_usd'] : 0.0;
        $operador = isset($dataInput['operador_responsable']) ? trim($dataInput['operador_responsable']) : 'admin_sistema';
        
        if ($clienteId <= 0 || $formaPagoId <= 0 || $equivalenteUsd <= 0) {
            sendJsonResponse(false, 'Parámetros inválidos. El monto en USD debe ser mayor a 0.', [], 400);
        }
        
        // Validar que el cliente tenga saldo pendiente
        $stmtSaldo = $pdo->prepare('SELECT * FROM saldos_pendientes WHERE cliente_id = :id LIMIT 1');
        $stmtSaldo->execute(['id' => $clienteId]);
        $saldoActual = $stmtSaldo->fetch();
        
        if (!$saldoActual || (float)$saldoActual['monto_deuda_total_usd'] <= 0.0) {
            sendJsonResponse(false, 'El cliente no posee deudas pendientes a registrar.', [], 400);
        }
        
        // Iniciar transacción ACID
        $pdo->beginTransaction();
        
        $deudaAnteriorZenda = (int)$saldoActual['botellas_pendientes_zenda'];
        $deudaAnteriorAlpes = (int)$saldoActual['botellas_pendientes_alpes'];
        $deudaAnteriorUsd = (float)$saldoActual['monto_deuda_total_usd'];
        
        // Obtener precios de marcas
        $stmtPrecios = $pdo->query('SELECT codigo_identificador, precio_usd FROM marcas_agua WHERE activo = 1');
        $preciosRaw = $stmtPrecios->fetchAll();
        $precios = [];
        foreach ($preciosRaw as $row) {
            $precios[$row['codigo_identificador']] = (float)$row['precio_usd'];
        }
        $precioZenda = isset($precios['zenda']) ? $precios['zenda'] : 7.00;
        $precioAlpes = isset($precios['alpes']) ? $precios['alpes'] : 3.00;
        
        // Escenario A: Pago cubre toda la deuda o más
        if ($equivalenteUsd >= $deudaAnteriorUsd) {
            // Liquidación total
            // 1. Marcar todos los despachos pendientes como pagados (Renombramos el segundo :ref a :ref_obs)
            $stmtUpdateAll = $pdo->prepare('
                UPDATE despachos
                SET estado_pago = "pagado",
                    forma_pago_id = :forma_pago_id,
                    referencia_pago = :ref,
                    observaciones = CONCAT(COALESCE(observaciones, ""), " | Pago total verificado por $", :monto, " USD. Ref: ", :ref_obs)
                WHERE cliente_id = :cliente_id AND estado_pago != "pagado"
            ');
            $stmtUpdateAll->execute([
                'forma_pago_id' => $formaPagoId,
                'ref' => $referencia,
                'ref_obs' => $referencia,
                'monto' => $equivalenteUsd,
                'cliente_id' => $clienteId
            ]);
            
            // 2. Limpiar saldos pendientes a 0
            $stmtClearSaldo = $pdo->prepare('
                UPDATE saldos_pendientes
                SET botellas_pendientes_zenda = 0,
                    botellas_pendientes_alpes = 0,
                    monto_deuda_total_usd = 0.00
                WHERE cliente_id = :cliente_id
            ');
            $stmtClearSaldo->execute(['cliente_id' => $clienteId]);
            
            $nuevoZenda = 0;
            $nuevoAlpes = 0;
            $nuevaDeuda = 0.00;
            $tipoConciliacion = 'pago_total';
            $auditoriaMsg = 'Conciliación exitosa. Saldo liquidado en su totalidad.';
        } 
        
        // Escenario B: Pago Parcial (Abono bajo criterio FIFO)
        else {
            $saldoRestantePago = $equivalenteUsd;
            
            // Buscar los despachos pendientes u ordenados por fecha más antigua
            $stmtPending = $pdo->prepare('
                SELECT id, botellas_zenda, botellas_alpes, monto_despacho_usd, estado_pago, observaciones
                FROM despachos
                WHERE cliente_id = :cliente_id AND estado_pago != "pagado"
                ORDER BY fecha ASC, id ASC
            ');
            $stmtPending->execute(['cliente_id' => $clienteId]);
            $despachosPendientes = $stmtPending->fetchAll();
            
            $descontarZenda = 0;
            $descontarAlpes = 0;
            
            foreach ($despachosPendientes as $despacho) {
                if ($saldoRestantePago <= 0) break;
                
                $despachoId = (int)$despacho['id'];
                $montoDespacho = (float)$despacho['monto_despacho_usd'];
                
                // Determinar si este pago cubre este despacho completo
                if ($saldoRestantePago >= $montoDespacho) {
                    // Pagar despacho completo
                    $saldoRestantePago -= $montoDespacho;
                    
                    $stmtPayDespacho = $pdo->prepare('
                        UPDATE despachos
                        SET estado_pago = "pagado",
                            forma_pago_id = :forma_pago_id,
                            referencia_pago = :ref,
                            observaciones = CONCAT(COALESCE(observaciones, ""), " | Conciliado FIFO. Ref: ", :ref)
                        WHERE id = :id
                    ');
                    $stmtPayDespacho->execute([
                        'forma_pago_id' => $formaPagoId,
                        'ref' => $referencia,
                        'id' => $despachoId
                    ]);
                    
                    // Sumar botellas a descontar del saldo general
                    $descontarZenda += (int)$despacho['botellas_zenda'];
                    $descontarAlpes += (int)$despacho['botellas_alpes'];
                } else {
                    // Pago parcial del despacho
                    // Intentar pagar botellas individuales de este despacho (antigüedad FIFO)
                    $zendaEnDespacho = (int)$despacho['botellas_zenda'];
                    $alpesEnDespacho = (int)$despacho['botellas_alpes'];
                    
                    $zendaPagadas = 0;
                    $alpesPagadas = 0;
                    
                    // Primero descontamos botellas Zenda ($7) si hay saldo
                    while ($zendaEnDespacho > 0 && $saldoRestantePago >= $precioZenda) {
                        $saldoRestantePago -= $precioZenda;
                        $zendaEnDespacho--;
                        $zendaPagadas++;
                    }
                    
                    // Luego botellas Alpes ($3) si hay saldo
                    while ($alpesEnDespacho > 0 && $saldoRestantePago >= $precioAlpes) {
                        $saldoRestantePago -= $precioAlpes;
                        $alpesEnDespacho--;
                        $alpesPagadas++;
                    }
                    
                    // Si pudimos pagar alguna botella, modificamos el estado del despacho
                    if ($zendaPagadas > 0 || $alpesPagadas > 0) {
                        $descontarZenda += $zendaPagadas;
                        $descontarAlpes += $alpesPagadas;
                        
                        // Renombramos el segundo :ref a :ref_obs
                        $stmtPartPay = $pdo->prepare('
                            UPDATE despachos
                            SET estado_pago = "pagado_parcial",
                                forma_pago_id = :forma_pago_id,
                                referencia_pago = :ref,
                                observaciones = CONCAT(COALESCE(observaciones, ""), " | Abono parcial FIFO (Zenda:", :zenda, ", Alpes:", :alpes, "). Ref: ", :ref_obs)
                            WHERE id = :id
                        ');
                        $stmtPartPay->execute([
                            'forma_pago_id' => $formaPagoId,
                            'ref' => $referencia,
                            'ref_obs' => $referencia,
                            'zenda' => $zendaPagadas,
                            'alpes' => $alpesPagadas,
                            'id' => $despachoId
                        ]);
                    }
                    
                    // Agotar saldo restante para centavos si hubiese
                    $saldoRestantePago = 0;
                }
            }
            
            // Recalcular saldo remanente
            $nuevoZenda = max(0, $deudaAnteriorZenda - $descontarZenda);
            $nuevoAlpes = max(0, $deudaAnteriorAlpes - $descontarAlpes);
            $nuevaDeuda = max(0.00, $deudaAnteriorUsd - $equivalenteUsd);
            
            if ($nuevaDeuda <= 0.01) {
                $nuevoZenda = 0;
                $nuevoAlpes = 0;
                $nuevaDeuda = 0.00;
            }
            
            $stmtUpdateSaldo = $pdo->prepare('
                UPDATE saldos_pendientes
                SET botellas_pendientes_zenda = :zenda,
                    botellas_pendientes_alpes = :alpes,
                    monto_deuda_total_usd = :deuda
                WHERE cliente_id = :cliente_id
            ');
            $stmtUpdateSaldo->execute([
                'zenda' => $nuevoZenda,
                'alpes' => $nuevoAlpes,
                'deuda' => $nuevaDeuda,
                'cliente_id' => $clienteId
            ]);
            
            $tipoConciliacion = 'pago_parcial';
            $auditoriaMsg = "Abono procesado con éxito. Se descontaron {$descontarZenda} botellas Zenda y {$descontarAlpes} botellas Alpes.";
        }
        
        $pdo->commit();
        
        // Crear log de historial financiero de auditoría
        $auditDir = __DIR__ . '/../data';
        if (!is_dir($auditDir)) {
            mkdir($auditDir, 0777, true);
        }
        $auditFile = $auditDir . '/historial_financiero_auditado.json';
        
        $auditLog = [];
        if (file_exists($auditFile)) {
            $auditLog = json_decode(file_get_contents($auditFile), true);
        }
        
        $txnId = 'TXN-' . date('Ymd') . '-' . rand(1000, 9999);
        $registroAuditoria = [
            'transaccion_id' => $txnId,
            'cierre_id' => 'CIERRE-' . date('Y-m-d'),
            'cliente_id' => $clienteId,
            'fecha_registro' => date('c'),
            'estado_anterior' => [
                'botellas_pendientes_zenda' => $deudaAnteriorZenda,
                'botellas_pendientes_alpes' => $deudaAnteriorAlpes,
                'deuda_total_usd' => $deudaAnteriorUsd
            ],
            'transaccion_aplicada' => [
                'monto_pagado_bs' => $montoBs,
                'equivalente_usd' => $equivalenteUsd,
                'referencia' => $referencia,
                'forma_pago_id' => $formaPagoId,
                'operador' => $operador
            ],
            'estado_nuevo' => [
                'botellas_pendientes_zenda' => $nuevoZenda,
                'botellas_pendientes_alpes' => $nuevoAlpes,
                'deuda_total_usd' => $nuevaDeuda,
                'estatus_cartera' => ($nuevaDeuda <= 0.0) ? 'al_dia' : 'pendiente'
            ],
            'auditoria_resultado' => $auditoriaMsg
        ];
        
        $auditLog[] = $registroAuditoria;
        file_put_contents($auditFile, json_encode($auditLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        sendJsonResponse(true, 'Pago registrado y cartera conciliada.', [
            'auditoria' => $registroAuditoria
        ]);
    }
    
    else {
        sendJsonResponse(false, 'Acción o método no soportado.', [], 400);
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJsonResponse(false, 'Error procesando pago: ' . $e->getMessage(), [], 500);
}
