<?php
// -------------------------------------------------------------
// ENDPOINT: COLA Y GENERACIÓN DE COBRANZA (api/cobranza.php)
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
    
    // ACCIÓN 1: Obtener la cola de notificaciones para una fecha (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'cola') {
        $fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
        if (empty($fecha)) {
            sendJsonResponse(false, 'La fecha es requerida.', [], 400);
        }
        
        $despachadorFiltro = isset($_GET['despachador']) ? trim($_GET['despachador']) : '';

        // Obtener el día de la semana en español
        $timestamp = strtotime($fecha);
        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $diaSemanaNombre = $diasSemana[date('w', $timestamp)];
        $fechaFormateada = date('d/m/Y', $timestamp);
        
        // 1. Clientes con Despacho HOY (Excluir facturacion_legal)
        $queryHoy = '
            SELECT c.id as cliente_id, c.nombre_oficial, c.telefono_whatsapp, c.categoria,
                   d.botellas_zenda as zenda_hoy, d.botellas_alpes as alpes_hoy, d.estado_pago,
                   COALESCE(ch.nombre, d.despachador) as despachador,
                   s.botellas_pendientes_zenda as zenda_total, s.botellas_pendientes_alpes as alpes_total,
                   s.monto_deuda_total_usd as deuda_total
            FROM despachos d
            JOIN clientes c ON d.cliente_id = c.id
            LEFT JOIN choferes ch ON d.chofer_id = ch.id
            LEFT JOIN saldos_pendientes s ON c.id = s.cliente_id
            WHERE d.fecha = :fecha AND c.categoria != "facturacion_legal"
        ';
        $paramsHoy = ['fecha' => $fecha];

        if ($despachadorFiltro !== '') {
            $queryHoy .= ' AND (d.despachador LIKE :despachador1 OR ch.nombre LIKE :despachador2)';
            $paramsHoy['despachador1'] = '%' . $despachadorFiltro . '%';
            $paramsHoy['despachador2'] = '%' . $despachadorFiltro . '%';
        }

        $stmtHoy = $pdo->prepare($queryHoy);
        $stmtHoy->execute($paramsHoy);
        $despachosHoy = $stmtHoy->fetchAll();
        
        // Sentencia preparada para buscar entregas anteriores con deuda
        $stmtHist = $pdo->prepare('
            SELECT fecha, botellas_zenda, botellas_alpes
            FROM despachos
            WHERE cliente_id = :cliente_id AND fecha < :fecha AND estado_pago != "pagado"
            ORDER BY fecha ASC
        ');

        // Mantener registro de IDs que ya salieron hoy para no duplicarlos en la cola de inactivos
        $idsExcluidos = [];
        $cola = [];
        
        foreach ($despachosHoy as $row) {
            $idsExcluidos[] = (int)$row['cliente_id'];
            
            $zendaHoy = (int)$row['zenda_hoy'];
            $alpesHoy = (int)$row['alpes_hoy'];
            $totalHoy = $zendaHoy + $alpesHoy;
            
            $zendaTotal = isset($row['zenda_total']) ? (int)$row['zenda_total'] : 0;
            $alpesTotal = isset($row['alpes_total']) ? (int)$row['alpes_total'] : 0;
            
            // Los saldos anteriores (antes del despacho de hoy) se obtienen restando lo entregado hoy
            $zendaAnterior = max(0, $zendaTotal - $zendaHoy);
            $alpesAnterior = max(0, $alpesTotal - $alpesHoy);
            $totalAnterior = $zendaAnterior + $alpesAnterior;
            
            $deudaTotal = isset($row['deuda_total']) ? (float)$row['deuda_total'] : 0.0;
            
            // Consultar despachos históricos anteriores pendientes de este cliente
            $stmtHist->execute(['cliente_id' => $row['cliente_id'], 'fecha' => $fecha]);
            $anterioresDetalle = $stmtHist->fetchAll();

            // Construir el mensaje de WhatsApp con desglose de fechas
            $mensaje = generarMensajeCobranza(
                $row['nombre_oficial'],
                true,
                $zendaHoy,
                $alpesHoy,
                $zendaAnterior,
                $alpesAnterior,
                $diaSemanaNombre,
                $fechaFormateada,
                $anterioresDetalle
            );
            
            $cola[] = [
                'cliente_id' => (int)$row['cliente_id'],
                'nombre_oficial' => $row['nombre_oficial'],
                'telefono_whatsapp' => $row['telefono_whatsapp'],
                'categoria' => $row['categoria'],
                'despachador' => $row['despachador'],
                'estado_pago_hoy' => $row['estado_pago'],
                'despacho_hoy' => [
                    'recibio_hoy' => true,
                    'botellas_zenda_hoy' => $zendaHoy,
                    'botellas_alpes_hoy' => $alpesHoy,
                    'total_hoy' => $totalHoy
                ],
                'saldos_anteriores' => [
                    'botellas_zenda_pendientes' => $zendaAnterior,
                    'botellas_alpes_pendientes' => $alpesAnterior,
                    'total_pendientes' => $totalAnterior
                ],
                'totales_consolidados' => [
                    'total_botellas_zenda' => $zendaTotal,
                    'total_botellas_alpes' => $alpesTotal,
                    'total_botellas_global' => ($zendaTotal + $alpesTotal),
                    'monto_deuda_total_usd' => $deudaTotal
                ],
                'mensaje_texto' => $mensaje
            ];
        }
        
        // 2. Clientes INACTIVOS con deuda (saldo > 0 y que no hayan recibido hoy ni sean facturacion_legal)
        // Solo incluirlos si no hay filtro de chofer restrictivo o si coincide
        if ($despachadorFiltro === '') {
            $placeholders = '';
            if (!empty($idsExcluidos)) {
                $placeholders = ' AND c.id NOT IN (' . implode(',', $idsExcluidos) . ')';
            }
            
            $queryInactivos = '
                SELECT c.id as cliente_id, c.nombre_oficial, c.telefono_whatsapp, c.categoria,
                       s.botellas_pendientes_zenda as zenda_total, s.botellas_pendientes_alpes as alpes_total,
                       s.monto_deuda_total_usd as deuda_total
                FROM saldos_pendientes s
                JOIN clientes c ON s.cliente_id = c.id
                WHERE (s.botellas_pendientes_zenda > 0 OR s.botellas_pendientes_alpes > 0)
                  AND c.categoria != "facturacion_legal"' . $placeholders;
            
            $stmtInactivos = $pdo->prepare($queryInactivos);
            $stmtInactivos->execute();
            $inactivos = $stmtInactivos->fetchAll();
            
            foreach ($inactivos as $row) {
                $zendaTotal = (int)$row['zenda_total'];
                $alpesTotal = (int)$row['alpes_total'];
                $deudaTotal = (float)$row['deuda_total'];
                
                $stmtHist->execute(['cliente_id' => $row['cliente_id'], 'fecha' => $fecha]);
                $anterioresDetalle = $stmtHist->fetchAll();

                $mensaje = generarMensajeCobranza(
                    $row['nombre_oficial'],
                    false,
                    0,
                    0,
                    $zendaTotal,
                    $alpesTotal,
                    $diaSemanaNombre,
                    $fechaFormateada,
                    $anterioresDetalle
                );
                
                $cola[] = [
                    'cliente_id' => (int)$row['cliente_id'],
                    'nombre_oficial' => $row['nombre_oficial'],
                    'telefono_whatsapp' => $row['telefono_whatsapp'],
                    'categoria' => $row['categoria'],
                    'despachador' => 'Cartera Pendiente',
                    'estado_pago_hoy' => 'inactivo_con_deuda',
                    'despacho_hoy' => [
                        'recibio_hoy' => false,
                        'botellas_zenda_hoy' => 0,
                        'botellas_alpes_hoy' => 0,
                        'total_hoy' => 0
                    ],
                    'saldos_anteriores' => [
                        'botellas_zenda_pendientes' => $zendaTotal,
                        'botellas_alpes_pendientes' => $alpesTotal,
                        'total_pendientes' => ($zendaTotal + $alpesTotal)
                    ],
                    'totales_consolidados' => [
                        'total_botellas_zenda' => $zendaTotal,
                        'total_botellas_alpes' => $alpesTotal,
                        'total_botellas_global' => ($zendaTotal + $alpesTotal),
                        'monto_deuda_total_usd' => $deudaTotal
                    ],
                    'mensaje_texto' => $mensaje
                ];
            }
        }
        
        sendJsonResponse(true, 'Cola de cobranza generada.', [
            'fecha' => $fecha,
            'total_clientes_cola' => count($cola),
            'cola' => $cola
        ]);
    }
    
    // ACCIÓN 2: Marcar como Notificado (POST)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'notificar') {
        $inputRaw = file_get_contents('php://input');
        $dataInput = json_decode($inputRaw, true);
        
        $clienteId = isset($dataInput['cliente_id']) ? (int)$dataInput['cliente_id'] : 0;
        $fecha = isset($dataInput['fecha']) ? trim($dataInput['fecha']) : '';
        
        if ($clienteId <= 0 || empty($fecha)) {
            sendJsonResponse(false, 'Parámetros inválidos.', [], 400);
        }
        
        $stmtUpdate = $pdo->prepare('
            UPDATE despachos
            SET estado_pago = "notificado",
                observaciones = CONCAT(COALESCE(observaciones, ""), " | Mensaje copiado el ", CURRENT_TIMESTAMP())
            WHERE cliente_id = :cliente_id AND fecha = :fecha AND estado_pago = "pendiente"
        ');
        $stmtUpdate->execute([
            'cliente_id' => $clienteId,
            'fecha' => $fecha
        ]);
        
        $filasModificadas = $stmtUpdate->rowCount();
        
        $logDir = __DIR__ . '/../data';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/log_envios_cobranza.json';
        
        $logs = [];
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        $logs[] = [
            'cliente_id' => $clienteId,
            'fecha_despacho' => $fecha,
            'timestamp' => date('c'),
            'accion' => 'copiado'
        ];
        
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
        
        sendJsonResponse(true, 'Estado del despacho actualizado a notificado.', [
            'despachos_actualizados' => $filasModificadas
        ]);
    }
    
    elseif (!empty($action)) {
        sendJsonResponse(false, 'Acción o método no soportado.', [], 400);
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Error en cobranza: ' . $e->getMessage(), [], 500);
}

/**
 * Función que genera la plantilla del mensaje de WhatsApp según las reglas de negocio.
 * Incluye desglose por fecha de entregas anteriores.
 * NUNCA DEBE INCLUIR MONTOS EN DÓLARES ($) NI EN BOLÍVARES (Bs).
 */
function generarMensajeCobranza($nombreCliente, $recibioHoy, $zendaHoy, $alpesHoy, $zendaAnt, $alpesAnt, $diaSemana, $fecha, $anterioresDetalle = []) {
    $saludo = "Hola buen día estimado cliente 🤗\nEspero se encuentre muy bien.\n\n";
    $cuerpo = "";
    
    // 1. Despacho del día
    if ($recibioHoy) {
        $textoHoy = "";
        $totalHoy = $zendaHoy + $alpesHoy;
        
        if ($zendaHoy > 0 && $alpesHoy > 0) {
            $textoHoy = "{$totalHoy} botellas ({$alpesHoy} Alpes / {$zendaHoy} La Zenda)";
        } elseif ($zendaHoy > 0) {
            $textoHoy = "{$zendaHoy} botellas La Zenda";
        } else {
            $textoHoy = "{$alpesHoy} botellas";
        }
        
        $cuerpo .= "Para confirmar {$textoHoy} del dia {$fecha}.\n";
    }

    // 2. Desglose de saldo pendiente y fechas anteriores
    $totalAnt = $zendaAnt + $alpesAnt;
    if ($totalAnt > 0) {
        if (!empty($anterioresDetalle)) {
            // Agrupar por fecha
            $porFecha = [];
            foreach ($anterioresDetalle as $ant) {
                $f = date('d/m/Y', strtotime($ant['fecha']));
                if (!isset($porFecha[$f])) {
                    $porFecha[$f] = ['zenda' => 0, 'alpes' => 0];
                }
                $porFecha[$f]['zenda'] += (int)$ant['botellas_zenda'];
                $porFecha[$f]['alpes'] += (int)$ant['botellas_alpes'];
            }

            if (count($porFecha) === 1) {
                $fUnica = array_key_first($porFecha);
                $z = $porFecha[$fUnica]['zenda'];
                $a = $porFecha[$fUnica]['alpes'];
                $t = $z + $a;
                $txt = formatBotellasTexto($z, $a);
                $cuerpo .= "Le escribimos para recordarle que mantiene un saldo pendiente de {$txt} del día {$fUnica}.\n";
            } else {
                $cuerpo .= "Le escribimos para recordarle que mantiene un saldo pendiente de entregas anteriores:\n";
                foreach ($porFecha as $fStr => $cantidades) {
                    $z = $cantidades['zenda'];
                    $a = $cantidades['alpes'];
                    $txt = formatBotellasTexto($z, $a);
                    $cuerpo .= "• Del día {$fStr}: {$txt}\n";
                }
            }
        } else {
            // Respaldo acumulado general si no hay desglose por fechas individuales en despachos
            $txt = formatBotellasTexto($zendaAnt, $alpesAnt);
            $cuerpo .= "Le escribimos para recordarle que mantiene un saldo pendiente de {$txt} de entregas anteriores.\n";
        }
    }
    
    $despedida = "\nSi ya fueron canceladas, por favor notificar.\n\nMuchísimas gracias por su colaboración.\nFeliz y bendecido día🙏";
    
    return $saludo . $cuerpo . $despedida;
}

/**
 * Auxiliar para formatear texto de botellas según marcas
 */
function formatBotellasTexto($zenda, $alpes) {
    $total = $zenda + $alpes;
    if ($zenda > 0 && $alpes > 0) {
        return "{$total} botella(s) ({$alpes} Alpes / {$zenda} La Zenda)";
    } elseif ($zenda > 0) {
        return "{$zenda} botella(s) La Zenda";
    } else {
        return "{$alpes} botella(s) Alpes";
    }
}
