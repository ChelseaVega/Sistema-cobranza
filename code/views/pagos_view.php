<?php
// -------------------------------------------------------------
// VISTA: REGISTRO DE PAGOS Y CARTERA (views/pagos_view.php)
// -------------------------------------------------------------
?>
<div id="pagos-view">
    <div style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); letter-spacing: -0.5px;">Gestión de Pagos, Liquidación de Saldos y Auditoría</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Registre abonos o cancelaciones totales de deudas de botellones y consulte la cartera de clientes.</p>
    </div>
    
    <div class="split-layout">
        <!-- PANEL IZQUIERDO: FORMULARIO DE COBRO -->
        <div class="panel-form">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--dark); margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                Registrar Confirmación de Pago
            </h3>
            
            <form id="form-registro-pago">
                <!-- Buscador de Cliente -->
                <div class="form-group" style="position: relative;">
                    <label for="pago-search-cliente">Cliente Oficial</label>
                    <div class="autocomplete-container">
                        <input type="text" id="pago-search-cliente" class="input-text" placeholder="Buscar cliente por nombre..." style="width: 100%;" required autocomplete="off">
                        <input type="hidden" id="pago-cliente-id">
                        <div id="pago-autocomplete-dropdown" class="autocomplete-dropdown d-none"></div>
                    </div>
                </div>
                
                <!-- Contenedor dinámico de información de la deuda actual -->
                <div id="pago-info-deuda-cliente" class="mb-4 d-none"></div>
                
                <!-- Forma de Pago -->
                <div class="form-group">
                    <label for="pago-forma-select">Forma y Método de Pago</label>
                    <select id="pago-forma-select" class="select-input" style="width: 100%;" required>
                        <option value="">Cargando formas de pago...</option>
                    </select>
                </div>
                
                <!-- Referencia Bancaria -->
                <div class="form-group">
                    <label for="pago-referencia">Referencia de Transacción</label>
                    <input type="text" id="pago-referencia" class="input-text" placeholder="Ej. 65638" style="width: 100%;" disabled>
                </div>
                
                <!-- Monto en Bolívares (Opcional) -->
                <div class="form-group">
                    <label for="pago-monto-bs">Monto Cancelado (Bs.) <span style="font-weight: normal; color: var(--text-muted);">(Opcional)</span></label>
                    <input type="number" id="pago-monto-bs" class="input-text" placeholder="Ej. 1650.00" step="0.01" style="width: 100%;">
                </div>
                
                <!-- Equivalente en USD (Requerido para cálculo FIFO) -->
                <div class="form-group">
                    <label for="pago-monto-usd">Equivalente / Monto Abonado (USD)</label>
                    <input type="number" id="pago-monto-usd" class="input-text" placeholder="Ej. 38.00" step="0.01" style="width: 100%;" required>
                </div>
                
                <!-- Operador Responsable -->
                <div class="form-group">
                    <label for="pago-operador">Operador Responsable</label>
                    <input type="text" id="pago-operador" class="input-text" value="admin_sistema" style="width: 100%;" readonly>
                </div>
                
                <button type="submit" class="btn-primary mt-4" style="width: 100%; justify-content: center; padding: 0.85rem;">
                    Registrar Pago
                </button>
            </form>
        </div>
        
        <!-- PANEL DERECHO: CARTERA DE CLIENTES -->
        <div class="table-responsive" style="margin-bottom: 0;">
            <div class="table-section-title" style="flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <span>Cartera Activa y Saldos Pendientes</span>
                    <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">
                        Deuda Total Acumulada: <strong id="label-deuda-total-activa" style="color: var(--primary);">$0.00</strong>
                    </span>
                </div>
                
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <label for="pagos-search-filtro" style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Buscar:</label>
                    <input type="text" id="pagos-search-filtro" class="input-text" placeholder="Filtrar por nombre o alias..." style="padding: 0.4rem 0.8rem; width: 220px; font-size: 0.85rem;">
                </div>
            </div>
            
            <table class="premium-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Cliente Oficial / Alias</th>
                        <th style="width: 110px;">Zendas Deuda</th>
                        <th style="width: 110px;">Alpes Deuda</th>
                        <th style="width: 130px;" class="text-right">Monto USD</th>
                        <th style="width: 120px;">Estado</th>
                        <th style="width: 80px;" class="text-center">Cobrar</th>
                    </tr>
                </thead>
                <tbody id="tbody-saldos-pendientes">
                    <tr>
                        <td colspan="7" class="text-center text-muted">Cargando cartera de clientes...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
