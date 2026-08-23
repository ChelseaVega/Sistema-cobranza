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
            $fecha = $pdo->query('SELECT MAX(fecha) FROM despachos')->fetchColumn() ?: date('Y-m-d');
        }
        
        $despachadorFiltro = isset($_GET['despachador']) ? trim($_GET['despachador']) : '';

        // Obtener el día de la semana en español
        $timestamp = strtotime($fecha);
        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $diaSemanaNombre = $diasSemana[date('w', $timestamp)];
        $fechaFormateada = date('d/m/Y', $timestamp);
        
        // 1. Clientes con Despacho en la fecha seleccionada (Agrupado por cliente)
        $queryHoy = '
            SELECT c.id as cliente_id, c.nombre_oficial, c.telefono_whatsapp, c.categoria,
                   SUM(d.botellas_zenda) as zenda_hoy,
                   SUM(d.botellas_alpes) as alpes_hoy,
                   MAX(d.estado_pago) as estado_pago,
                   MAX(COALESCE(ch.nombre, d.despachador)) as despachador,
                   COALESCE(s.botellas_pendientes_zenda, 0) as zenda_total,
                   COALESCE(s.botellas_pendientes_alpes, 0) as alpes_total,
                   COALESCE(s.monto_deuda_total_usd, 0.00) as deuda_total
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

        $queryHoy .= ' GROUP BY c.id, c.nombre_oficial, c.telefono_whatsapp, c.categoria, s.botellas_pendientes_zenda, s.botellas_pendientes_alpes, s.monto_deuda_total_usd';

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

        $idsExcluidos = [];
        $cola = [];
        
        foreach ($despachosHoy as $row) {
            $idsExcluidos[] = (int)$row['cliente_id'];
            
            $zendaHoy = (int)$row['zenda_hoy'];
            $alpesHoy = (int)$row['alpes_hoy'];
            $totalHoy = $zendaHoy + $alpesHoy;
            
            $zendaTotal = (int)$row['zenda_total'];
            $alpesTotal = (int)$row['alpes_total'];
            $totalGlobal = $zendaTotal + $alpesTotal;
            
            // Los saldos anteriores (antes del despacho de hoy)
            $zendaAnterior = max(0, $zendaTotal - $zendaHoy);
            $alpesAnterior = max(0, $alpesTotal - $alpesHoy);
            $totalAnterior = $zendaAnterior + $alpesAnterior;
            
            $deudaTotal = (float)$row['deuda_total'];
            
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
                    'total_botellas_global' => $totalGlobal,
                    'monto_deuda_total_usd' => $deudaTotal
                ],
                'mensaje_texto' => $mensaje
            ];
        }
        
        // 2. Clientes INACTIVOS con deuda (saldo > 0 y que no hayan recibido hoy ni sean facturacion_legal)
        if ($despachadorFiltro === '') {
            $placeholders = '';
            if (!empty($idsExcluidos)) {
                $placeholders = ' AND c.id NOT IN (' . implode(',', $idsExcluidos) . ')';
            }
            
            $queryInactivos = '
                SELECT c.id as cliente_id, c.nombre_oficial, c.telefono_whatsapp, c.categoria,
                       COALESCE(s.botellas_pendientes_zenda, 0) as zenda_total,
                       COALESCE(s.botellas_pendientes_alpes, 0) as alpes_total,
                       COALESCE(s.monto_deuda_total_usd, 0.00) as deuda_total
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
                $totalGlobal = $zendaTotal + $alpesTotal;
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
                        'total_pendientes' => $totalGlobal
                    ],
                    'totales_consolidados' => [
                        'total_botellas_zenda' => $zendaTotal,
                        'total_botellas_alpes' => $alpesTotal,
                        'total_botellas_global' => $totalGlobal,
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
 * Garantiza que la deuda total real de saldos_pendientes se refleje íntegramente.
 * NUNCA DEBE INCLUIR MONTOS EN DÓLARES ($) NI EN BOLÍVARES (Bs).
 */
function generarMensajeCobranza($nombreCliente, $recibioHoy, $zendaHoy, $alpesHoy, $zendaAnt, $alpesAnt, $diaSemana, $fecha, $anterioresDetalle = []) {
    $saludo = "Hola buen día estimado cliente 🤗\nEspero se encuentre muy bien.\n\n";
    $cuerpo = "";
    
    // 1. Despacho del día
    if ($recibioHoy) {
        $totalHoy = $zendaHoy + $alpesHoy;
        $txtHoy = formatBotellasTexto($zendaHoy, $alpesHoy);
        $cuerpo .= "Para confirmar {$txtHoy} del dia {$fecha}.\n";
    }

    // 2. Desglose de saldo anterior pendiente (garantizando total de saldos_pendientes)
    $totalAnt = $zendaAnt + $alpesAnt;
    if ($totalAnt > 0) {
        $txtTotalAnt = formatBotellasTexto($zendaAnt, $alpesAnt);
        
        if (!empty($anterioresDetalle)) {
            // Agrupar despachos anteriores por fecha
            $porFecha = [];
            $sumZendaHist = 0;
            $sumAlpesHist = 0;
            
            foreach ($anterioresDetalle as $ant) {
                $f = date('d/m/Y', strtotime($ant['fecha']));
                if (!isset($porFecha[$f])) {
                    $porFecha[$f] = ['zenda' => 0, 'alpes' => 0];
                }
                $z = (int)$ant['botellas_zenda'];
                $a = (int)$ant['botellas_alpes'];
                $porFecha[$f]['zenda'] += $z;
                $porFecha[$f]['alpes'] += $a;
                $sumZendaHist += $z;
                $sumAlpesHist += $a;
            }

            if (count($porFecha) === 1 && ($sumZendaHist + $sumAlpesHist) >= $totalAnt) {
                $fUnica = array_key_first($porFecha);
                $cuerpo .= "Le escribimos para recordarle que mantiene un saldo pendiente de {$txtTotalAnt} del día {$fUnica}.\n";
            } else {
                $cuerpo .= "Le escribimos para recordarle que mantiene un saldo pendiente de {$txtTotalAnt} de entregas anteriores:\n";
                foreach ($porFecha as $fStr => $cantidades) {
                    $txtFila = formatBotellasTexto($cantidades['zenda'], $cantidades['alpes']);
                    $cuerpo .= "• Del día {$fStr}: {$txtFila}\n";
                }
                
                // Si la deuda de saldos_pendientes supera los registros de la tabla despachos
                $remZenda = max(0, $zendaAnt - $sumZendaHist);
                $remAlpes = max(0, $alpesAnt - $sumAlpesHist);
                if (($remZenda + $remAlpes) > 0) {
                    $txtRem = formatBotellasTexto($remZenda, $remAlpes);
                    $cuerpo .= "• Saldo anterior acumulado: {$txtRem}\n";
                }
            }
        } else {
            // Si no hay filas históricas individuales pero existe deuda en saldos_pendientes
            $cuerpo .= "Le escribimos para recordarle que mantiene un saldo pendiente de {$txtTotalAnt} de entregas anteriores.\n";
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
