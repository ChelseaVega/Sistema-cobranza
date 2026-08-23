<?php
// -------------------------------------------------------------
// VISTA: INGESTA OCR DE LISTAS (views/ingesta_view.php)
// -------------------------------------------------------------
?>
<div id="ingesta-view">
    <div style="margin-bottom: 2rem;">
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); letter-spacing: -0.5px;">Módulo de Ingesta OCR y Carga de Listas Diarias</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Cargue el archivo estructurado JSON para validar y registrar la actividad de reparto de la jornada.</p>
    </div>
    
    <!-- DROPZONE DE ARRASTRE -->
    <div id="dropzone" class="dropzone-container">
        <input type="file" id="ingesta-file-input" accept=".json" style="display: none;">
        <div class="dropzone-icon">
            <!-- Icono de subir archivo / nube con flecha -->
            <svg viewBox="0 0 24 24">
                <path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
            </svg>
        </div>
        <div class="dropzone-text">
            <h3>Arrastre aquí 'despachos_diarios.json'</h3>
            <p>o haga clic en esta área para examinar los archivos locales de su computadora</p>
        </div>
    </div>
    
    <!-- PANEL DE PREVISUALIZACIÓN (Oculto inicialmente) -->
    <div id="preview-panel" class="d-none">
        <!-- Metadatos de la Ingesta -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background-color: var(--white); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <small class="text-muted" style="text-transform: uppercase; font-weight: 600;">Fecha de Ingesta</small>
                <div id="prev-fecha" class="bold" style="font-size: 1.1rem; color: var(--dark); margin-top: 0.25rem;">—</div>
            </div>
            
            <div style="background-color: var(--white); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <small class="text-muted" style="text-transform: uppercase; font-weight: 600;">Chofer Encargado</small>
                <div id="prev-despachador" class="bold" style="font-size: 1.1rem; color: var(--dark); margin-top: 0.25rem;">—</div>
            </div>
            
            <div style="background-color: var(--white); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <small class="text-muted" style="text-transform: uppercase; font-weight: 600;">Canal de Origen</small>
                <div id="prev-fuente" class="bold" style="font-size: 1.1rem; color: var(--dark); margin-top: 0.25rem;">—</div>
            </div>
            
            <div style="background-color: var(--white); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <small class="text-muted" style="text-transform: uppercase; font-weight: 600;">Listas Diarias Procesadas</small>
                <div id="prev-listas" class="bold" style="font-size: 1.1rem; color: var(--dark); margin-top: 0.25rem;">—</div>
            </div>
        </div>
        
        <!-- Tarjetas de Totales -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div style="background-color: var(--white); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); text-align: center;">
                <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">Total La Zenda</span>
                <h4 id="prev-val-zenda" style="font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-top: 0.25rem;">0</h4>
            </div>
            
            <div style="background-color: var(--white); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); text-align: center;">
                <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">Total Los Alpes</span>
                <h4 id="prev-val-alpes" style="font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-top: 0.25rem;">0</h4>
            </div>
            
            <div style="background-color: var(--white); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); text-align: center;">
                <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">Total Líquidos</span>
                <h4 id="prev-val-liquidos" style="font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-top: 0.25rem;">0</h4>
            </div>
            
            <div style="background-color: var(--white); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); text-align: center;">
                <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">Monto Bruto USD</span>
                <h4 id="prev-val-monto" style="font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;">$0.00</h4>
            </div>
        </div>
        
        <!-- Tabla Previa de Inspección -->
        <div class="table-responsive">
            <div class="table-section-title">
                <span>Inspección Previa de Despachos</span>
            </div>
            <table class="premium-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Item</th>
                        <th>Edificio/Zona</th>
                        <th style="width: 110px;">Sublocal</th>
                        <th>Cliente Raw / Alias</th>
                        <th>Nombre Consolidado</th>
                        <th>Botellas Despachadas</th>
                        <th>Monto USD</th>
                        <th>Estatus Declarado</th>
                        <th style="width: 180px;">Validación</th>
                    </tr>
                </thead>
                <tbody id="tbody-previa-ingesta"></tbody>
            </table>
        </div>
        
        <!-- Botón de acción definitivo -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 2rem;">
            <button id="btn-importar-ingesta" class="btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem;" disabled>
                Procesar e Importar Ingesta
            </button>
        </div>
    </div>
</div>
