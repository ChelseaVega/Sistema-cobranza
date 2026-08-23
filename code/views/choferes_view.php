<?php
// -------------------------------------------------------------
// VISTA: GESTIÓN DE CHOFERES (views/choferes_view.php)
// -------------------------------------------------------------
?>
<div id="choferes-view">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); letter-spacing: -0.5px;">Gestión de Choferes y Despachadores</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Administre el equipo de repartidores y despachadores de la distribuidora de agua mineral.</p>
        </div>
        
        <div>
            <button id="btn-nuevo-chofer" class="btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                Registrar Nuevo Chofer
            </button>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA -->
    <div style="background-color: var(--white); padding: 1rem 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
        <div style="flex-grow: 1; max-width: 450px;">
            <input type="text" id="choferes-search" class="input-text" placeholder="Buscar chofer por nombre o teléfono..." style="width: 100%;">
        </div>

        <div style="font-size: 0.85rem; color: var(--text-muted);">
            Total registrados: <strong id="total-choferes-badge" style="color: var(--dark);">0</strong>
        </div>
    </div>

    <!-- TABLA DE CHOFERES -->
    <div class="table-responsive">
        <table class="premium-table">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Nombre Completo</th>
                    <th style="width: 180px;">Teléfono de Contacto</th>
                    <th style="width: 160px;" class="text-center">Despachos Registrados</th>
                    <th style="width: 120px;">Estatus</th>
                    <th style="width: 120px;" class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-choferes">
                <tr>
                    <td colspan="6" class="text-center text-muted">Cargando lista de choferes...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- MODAL DE CREACIÓN / EDICIÓN DE CHOFER -->
    <div id="modal-chofer" class="modal-backdrop">
        <div class="modal-window">
            <div class="modal-header">
                <h2 id="modal-chofer-titulo">Registrar Nuevo Chofer</h2>
                <button class="modal-close">&times;</button>
            </div>

            <form id="form-chofer">
                <input type="hidden" id="chofer-form-id" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="chofer-nombre">Nombre Completo del Chofer / Despachador *</label>
                        <input type="text" id="chofer-nombre" class="input-text" placeholder="Ej. Gabriel Farias" style="width: 100%;" required>
                    </div>

                    <div class="form-group">
                        <label for="chofer-telefono">Teléfono de Contacto</label>
                        <input type="text" id="chofer-telefono" class="input-text" placeholder="Ej. +584141234567" style="width: 100%;">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" id="btn-cerrar-modal-chofer" class="btn-secondary" style="background-color: var(--white); color: var(--dark); border-color: var(--border-color);">Cancelar</button>
                    <button type="submit" id="btn-guardar-chofer" class="btn-primary">Guardar Chofer</button>
                </div>
            </form>
        </div>
    </div>
</div>
