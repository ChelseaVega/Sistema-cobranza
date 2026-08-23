<?php
// -------------------------------------------------------------
// VISTA: RESOLUCIÓN DE ALERTAS (views/alertas_view.php)
// -------------------------------------------------------------
?>
<div id="alertas-view">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); letter-spacing: -0.5px;">Bandeja de Resolución de Alertas y Ambigüedades</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Asocie manualmente los despachos cuyos nombres no coincidan con la base de datos oficial (similitud < 85%).</p>
        </div>
        
        <div>
            <select id="alerta-status-filter" class="select-input">
                <option value="pendientes">Mostrar Pendientes</option>
                <option value="resueltas">Mostrar Resueltas</option>
                <option value="todas">Mostrar Todas</option>
            </select>
        </div>
    </div>
    
    <!-- TABLA DE ALERTAS -->
    <div class="table-responsive">
        <div class="table-section-title">
            <span>Alertas Registradas en el Sistema</span>
        </div>
        <table class="premium-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID Alerta</th>
                    <th style="width: 120px;">Fecha</th>
                    <th>Nombre Raw / Escrito</th>
                    <th>Cantidad Botellas</th>
                    <th>Comentarios Chofer</th>
                    <th>Candidato Sugerido (BD)</th>
                    <th style="width: 130px;">Estatus</th>
                    <th style="width: 130px;" class="text-right">Acción</th>
                </tr>
            </thead>
            <tbody id="tbody-alertas">
                <tr>
                    <td colspan="8" class="text-center text-muted">Cargando alertas de conciliación...</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- MODAL INTERACTIVO DE RESOLUCIÓN -->
    <div id="modal-alerta" class="modal-backdrop">
        <div class="modal-window">
            <div class="modal-header">
                <h2>Resolver Ambigüedad de Cliente</h2>
                <button class="modal-close">&times;</button>
            </div>
            
            <div class="modal-body" style="padding-bottom: 0.5rem;">
                <div style="margin-bottom: 1.25rem; background-color: var(--light-bg); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted);">Nombre escrito por chofer (Raw):</span>
                    <h3 id="lbl-alerta-nombre-raw" style="color: var(--dark); font-weight: 700; margin-top: 0.25rem; font-size: 1.1rem;">—</h3>
                    <p id="lbl-alerta-detalles" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">—</p>
                </div>

                <!-- Pestañas del Modal -->
                <div class="modal-tabs">
                    <button class="modal-tab-btn active" data-tab="tab-vincular">Vincular Existente</button>
                    <button class="modal-tab-btn" data-tab="tab-crear">Registrar y Vincular Nuevo</button>
                </div>

                <!-- CONTENIDO PESTAÑA 1: VINCULAR EXISTENTE -->
                <div id="tab-vincular" class="modal-tab-content active">
                    <div class="form-group" style="position: relative;">
                        <label for="alerta-search-cliente">Vincular con Cliente Oficial de Base de Datos:</label>
                        <div class="autocomplete-container">
                            <input type="text" id="alerta-search-cliente" class="input-text" placeholder="Escriba nombre o alias del cliente..." style="width: 100%;" autocomplete="off">
                            <input type="hidden" id="alerta-selected-cliente-id">
                            <!-- Dropdown de autocompletado -->
                            <div id="alerta-autocomplete-dropdown" class="autocomplete-dropdown d-none"></div>
                        </div>
                        <small class="text-muted" style="margin-top: 0.25rem;">Busque por el nombre formal de facturación o el alias residencial de despacho.</small>
                    </div>
                </div>

                <!-- CONTENIDO PESTAÑA 2: CREAR Y VINCULAR NUEVO -->
                <div id="tab-crear" class="modal-tab-content">
                    <div class="form-group">
                        <label for="alerta-new-nombre-oficial">Nombre Oficial de Facturación o Cliente</label>
                        <input type="text" id="alerta-new-nombre-oficial" class="input-text" placeholder="Ej. Repuestos El Triunfo C.A." style="width: 100%;">
                    </div>
                    
                    <div class="form-group">
                        <label for="alerta-new-alias">Alias de Despacho (Debe coincidir con la lista)</label>
                        <input type="text" id="alerta-new-alias" class="input-text" placeholder="Ej. EL TRIUNFO" style="width: 100%;">
                        <small class="text-muted">Un alias de despacho pertenece única y exclusivamente a un solo cliente.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="alerta-new-telefono">Número de WhatsApp (Notificaciones)</label>
                        <input type="text" id="alerta-new-telefono" class="input-text" placeholder="Ej. +584121234567" style="width: 100%;">
                    </div>
                    
                    <div class="form-group">
                        <label for="alerta-new-categoria">Categoría Comercial</label>
                        <select id="alerta-new-categoria" class="select-input" style="width: 100%;">
                            <option value="local" selected>Local Comercial / Negocio</option>
                            <option value="domicilio">Domicilio / Familiar</option>
                            <option value="facturacion_legal">Facturación Legal (Excluido de WA)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button id="btn-cerrar-modal-alerta" class="btn-secondary" style="background-color: var(--white); color: var(--dark); border-color: var(--border-color);">Cancelar</button>
                <button id="btn-confirmar-vinculo" class="btn-primary">Vincular y Confirmar</button>
                <button id="btn-confirmar-crear" class="btn-primary d-none">Registrar y Vincular</button>
            </div>
        </div>
    </div>
</div>
