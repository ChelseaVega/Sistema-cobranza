<?php
// -------------------------------------------------------------
// VISTA: PANEL DE CONTROL GENERAL (views/dashboard.php)
// -------------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

$listaChoferes = [];
try {
    $pdoDb = getDatabaseConnection();
    $stmtChoferes = $pdoDb->query('SELECT id, nombre FROM choferes WHERE activo = 1 ORDER BY nombre ASC');
    $listaChoferes = $stmtChoferes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $listaChoferes = [];
}
?>
<div id="dashboard-view">
    <!-- Header interno de la vista con filtros y acciones globales -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); letter-spacing: -0.5px;">Panel de Control y Supervisión General</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Monitoreo de despachos del día, conciliación de deudas y cola de notificaciones.</p>
        </div>
        
        <div class="header-controls" style="flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="dashboard-date-filter" style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">Fecha:</label>
                <input type="date" id="dashboard-date-filter" class="input-date">
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="dashboard-dispatcher-filter" style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">Chofer:</label>
                <select id="dashboard-dispatcher-filter" class="input-text" style="min-width: 190px; height: 38px; cursor: pointer;">
                    <option value="">-- Todos los Choferes --</option>
                    <?php foreach ($listaChoferes as $ch): ?>
                        <option value="<?php echo htmlspecialchars($ch['nombre']); ?>">
                            <?php echo htmlspecialchars($ch['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button id="btn-dashboard-buscar" class="btn-primary" style="padding: 0.55rem 1.1rem; font-size: 0.85rem;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                Buscar
            </button>
            <button id="btn-dashboard-limpiar" class="btn-secondary" style="padding: 0.55rem 0.85rem; font-size: 0.85rem;" title="Restablecer filtros">
                Limpiar
            </button>
        </div>
    </div>
    
    <!-- GRID DE KPIs -->
    <div class="kpi-grid">
        <!-- KPI 1 -->
        <div class="kpi-card kpi-accent">
            <div class="kpi-header">
                <span class="kpi-title">Despachos del Día</span>
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                </div>
            </div>
            <div id="val-total-botellas-hoy" class="kpi-value">0</div>
            <div id="desc-total-botellas-hoy" class="kpi-desc">0 La Zenda | 0 Los Alpes</div>
        </div>
        
        <!-- KPI 2 -->
        <div class="kpi-card kpi-accent">
            <div class="kpi-header">
                <span class="kpi-title">Deuda Recuperable</span>
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/></svg>
                </div>
            </div>
            <div id="val-deuda-recuperable" class="kpi-value">$0.00</div>
            <div class="kpi-desc">Total acumulado en cartera activa</div>
        </div>
        
        <!-- KPI 3 -->
        <div class="kpi-card kpi-accent">
            <div class="kpi-header">
                <span class="kpi-title">Envíos de Cobranza</span>
                <div class="kpi-icon">
                    <!-- Ícono de chat/mensaje -->
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                </div>
            </div>
            <div id="val-cobranza-status" class="kpi-value">0 / 0</div>
            <div id="desc-cobranza-status" class="kpi-desc">0 pendientes por enviar</div>
        </div>
        
        <!-- KPI 4 -->
        <div id="kpi-alertas-pendientes" class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Alertas de Ambigüedad</span>
                <div class="kpi-icon" style="background-color: rgba(239, 68, 68, 0.08); color: #EF4444;">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
            </div>
            <div id="val-alertas-pendientes" class="kpi-value">0</div>
            <div class="kpi-desc">Nombres sin conciliación (< 85%)</div>
        </div>
    </div>
    
    <!-- SECCIÓN 1: TABLA DE RESUMEN DE DESPACHOS -->
    <div class="table-responsive">
        <div class="table-section-title">
            <span>Despachos de la Jornada</span>
        </div>
        <table class="premium-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 120px;">Fecha</th>
                    <th>Cliente</th>
                    <th>Chofer/Despachador</th>
                    <th>Cantidad Botellas</th>
                    <th>Monto USD</th>
                    <th>Forma Pago</th>
                    <th style="width: 140px;">Estatus Pago</th>
                </tr>
            </thead>
            <tbody id="tbody-resumen-despachos">
                <tr>
                    <td colspan="8" class="text-center text-muted">Cargando despachos...</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- SECCIÓN 2: COLA DE COBRANZA WHATSAPP -->
    <div class="table-responsive">
        <div class="table-section-title">
            <span>Cola de Cobranza del Día (WhatsApp)</span>
            <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">
                Excluye automáticamente a clientes con categoría <strong>facturacion_legal</strong>.
            </span>
        </div>
        <table class="premium-table">
            <thead>
                <tr>
                    <th>Cliente Oficial</th>
                    <th style="width: 150px;">Teléfono WA</th>
                    <th style="width: 120px;" class="text-center">Despacho Hoy</th>
                    <th>Deuda Total Física</th>
                    <th style="width: 120px;" class="text-right">Deuda Financiera</th>
                    <th style="width: 120px;">Estatus</th>
                    <th style="width: 200px;" class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-cola-cobranza">
                <tr>
                    <td colspan="7" class="text-center text-muted">Cargando cola de cobranza...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
