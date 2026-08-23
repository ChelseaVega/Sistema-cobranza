<?php
// -------------------------------------------------------------
// VISTA: GESTIÓN DE CLIENTES (views/clientes_view.php)
// -------------------------------------------------------------
?>
<div id="clientes-view">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--dark); letter-spacing: -0.5px;">Gestión y Catálogo de Clientes</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Administre el directorio oficial de clientes, alias de entrega y teléfonos de contacto para cobranza.</p>
        </div>
        
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <button id="btn-nuevo-cliente" class="btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                Registrar Nuevo Cliente
            </button>
        </div>
    </div>

    <!-- BARRA DE FILTROS Y BÚSQUEDA -->
    <div style="background-color: var(--white); padding: 1rem 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
        <div style="display: flex; gap: 1rem; flex-grow: 1; max-width: 600px; flex-wrap: wrap;">
            <div style="flex-grow: 1; min-width: 240px;">
                <input type="text" id="clientes-search" class="input-text" placeholder="Buscar por nombre oficial, alias o teléfono..." style="width: 100%;">
            </div>
            <div>
                <select id="clientes-filtro-categoria" class="select-input">
                    <option value="">Todas las Categorías</option>
                    <option value="local">Local Comercial</option>
                    <option value="domicilio">Domicilio</option>
                    <option value="facturacion_legal">Facturación Legal</option>
                </select>
            </div>
        </div>

        <div style="font-size: 0.85rem; color: var(--text-muted);">
            Total registrados: <strong id="total-clientes-badge" style="color: var(--dark);">0</strong>
        </div>
    </div>

    <!-- TABLA DE CLIENTES -->
    <div class="table-responsive">
        <table class="premium-table">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Nombre Oficial</th>
                    <th>Alias de Despacho (Chofer)</th>
                    <th style="width: 150px;">Teléfono WhatsApp</th>
                    <th style="width: 130px;">Categoría</th>
                    <th style="width: 130px;">Deuda Activa</th>
                    <th style="width: 100px;">Estatus</th>
                    <th style="width: 120px;" class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-clientes">
                <tr>
                    <td colspan="8" class="text-center text-muted">Cargando directorio de clientes...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- MODAL DE CREACIÓN / EDICIÓN DE CLIENTE -->
    <div id="modal-cliente" class="modal-backdrop">
        <div class="modal-window">
            <div class="modal-header">
                <h2 id="modal-cliente-titulo">Registrar Nuevo Cliente</h2>
                <button class="modal-close">&times;</button>
            </div>

            <form id="form-cliente">
                <input type="hidden" id="cliente-form-id" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="cliente-nombre-oficial">Nombre Oficial de Facturación / Titular *</label>
                        <input type="text" id="cliente-nombre-oficial" class="input-text" placeholder="Ej. Pastelería Chacao C.A." style="width: 100%;" required>
                    </div>

                    <div class="form-group">
                        <label for="cliente-alias-despacho">Alias de Despacho (Cómo lo anota el chofer) *</label>
                        <input type="text" id="cliente-alias-despacho" class="input-text" placeholder="Ej. PASTELERIA CHACAO" style="width: 100%;" required>
                        <small class="text-muted">El alias se utiliza para el reconocimiento automático (Fuzzy Matching) en la ingesta.</small>
                    </div>

                    <div class="form-group">
                        <label for="cliente-telefono">Número de WhatsApp (Notificaciones) *</label>
                        <input type="text" id="cliente-telefono" class="input-text" placeholder="Ej. +584121234567" style="width: 100%;" required>
                    </div>

                    <div class="form-group">
                        <label for="cliente-categoria">Categoría Comercial *</label>
                        <select id="cliente-categoria" class="select-input" style="width: 100%;" required>
                            <option value="local">Local Comercial / Negocio</option>
                            <option value="domicilio">Domicilio / Familiar</option>
                            <option value="facturacion_legal">Facturación Legal (Excluido de Cobranza WA)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" id="btn-cerrar-modal-cliente" class="btn-secondary" style="background-color: var(--white); color: var(--dark); border-color: var(--border-color);">Cancelar</button>
                    <button type="submit" id="btn-guardar-cliente" class="btn-primary">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>
