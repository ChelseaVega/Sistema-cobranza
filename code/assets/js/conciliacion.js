// -------------------------------------------------------------
// CONCILIACION.JS: GESTIÓN DE INGESTA OCR Y ALERTAS
// -------------------------------------------------------------

let parsedIngestaJSON = null;
let activeClientsCatalog = [];
let currentAlertIdToResolve = null;
let currentSelectedClientId = null;

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar Ingesta si estamos en esa vista
    if (document.getElementById('ingesta-view')) {
        initIngestaDropzone();
    }
    
    // Inicializar Alertas si estamos en esa vista
    if (document.getElementById('alertas-view')) {
        initAlertasPage();
    }
});

/**
 * ==========================================================================
 * MÓDULO: INGESTA OCR (views/ingesta_view.php)
 * ==========================================================================
 */

function initIngestaDropzone() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('ingesta-file-input');
    const btnImportar = document.getElementById('btn-importar-ingesta');
    
    if (!dropzone || !fileInput) return;
    
    // Evento clic en dropzone abre el file input
    dropzone.addEventListener('click', () => fileInput.click());
    
    // Eventos drag & drop
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });
    
    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            handleSelectedFile(e.dataTransfer.files[0]);
        }
    });
    
    // Carga mediante input
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleSelectedFile(e.target.files[0]);
        }
    });
    
    // Botón Importar
    if (btnImportar) {
        btnImportar.addEventListener('click', async () => {
            if (!parsedIngestaJSON) return;
            
            btnImportar.disabled = true;
            btnImportar.textContent = 'Importando...';
            
            try {
                const res = await fetchAPI('api/ingesta.php', {
                    method: 'POST',
                    body: JSON.stringify(parsedIngestaJSON)
                });
                
                if (res.success) {
                    alert('Ingesta procesada, clientes conciliados e importados a base de datos con éxito.');
                    window.location.href = 'index.php?view=dashboard';
                } else {
                    alert('Error al importar ingesta: ' + res.message);
                }
            } catch (err) {
                console.error(err);
                alert('Error al realizar la petición: ' + err.message);
            } finally {
                btnImportar.disabled = false;
                btnImportar.textContent = 'Procesar e Importar Ingesta';
            }
        });
    }
}

/**
 * Leer archivo JSON seleccionado y mostrar previsualización
 */
function handleSelectedFile(file) {
    if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
        alert('Por favor, seleccione un archivo con formato .json.');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const json = JSON.parse(e.target.result);
            
            // Validar estructura del archivo JSON
            if (!json.metadata_procesamiento || !json.despachos || !Array.isArray(json.despachos)) {
                alert('La estructura del archivo JSON no coincide con el formato oficial de despachos.');
                return;
            }
            
            parsedIngestaJSON = json;
            renderIngestaPreview(json);
            
            // Habilitar botón de importación
            document.getElementById('btn-importar-ingesta').disabled = false;
            
        } catch (err) {
            alert('Error al decodificar el archivo JSON: ' + err.message);
        }
    };
    reader.readAsText(file);
}

/**
 * Renderiza la previsualización del JSON subido
 */
function renderIngestaPreview(json) {
    const meta = json.metadata_procesamiento;
    const res = json.resumen_diario || {};
    const despachos = json.despachos || [];
    
    // Actualizar metadatos y KPIs visuales
    document.getElementById('prev-fecha').textContent = meta.fecha_procesamiento || '—';
    document.getElementById('prev-despachador').textContent = meta.despachador || '—';
    document.getElementById('prev-fuente').textContent = (meta.origen_fuente || '—').toUpperCase();
    document.getElementById('prev-listas').textContent = `${meta.total_listas_procesadas} / ${meta.total_listas_esperadas}`;
    
    // Totales calculados en el JSON (se muestran informativos)
    document.getElementById('prev-val-zenda').textContent = res.total_botellas_zenda ?? '0';
    document.getElementById('prev-val-alpes').textContent = res.total_botellas_alpes ?? '0';
    document.getElementById('prev-val-liquidos').textContent = res.total_liquidos ?? '0';
    document.getElementById('prev-val-monto').textContent = formatUSD(res.monto_bruto_calculado_usd ?? 0.0);
    
    // Llenar tabla de previsualización
    const tbody = document.getElementById('tbody-previa-ingesta');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    despachos.forEach(item => {
        const tr = document.createElement('tr');
        
        let reqRevisionBadge = '';
        if (item.requiere_revision_humana) {
            reqRevisionBadge = `<span class="badge badge-pendiente" title="${item.motivo_revision || ''}">REVISIÓN REQUERIDA</span>`;
        } else {
            reqRevisionBadge = `<span class="badge badge-pagado">OK</span>`;
        }
        
        tr.innerHTML = `
            <td class="bold">#${item.id_item}</td>
            <td>${item.zona_edificio || '—'}</td>
            <td>${item.unidad_sublocal || '—'}</td>
            <td class="bold">${item.nombre_cliente_raw}</td>
            <td>${item.alias_despacho_consolidado || '—'}</td>
            <td>${item.botellas_zenda} Zenda / ${item.botellas_alpes} Alpes</td>
            <td class="bold">${formatUSD(item.monto_calculado_usd)}</td>
            <td><span class="badge badge-pendiente">${(item.estado_pago_declarado || 'pendiente').toUpperCase()}</span></td>
            <td>${reqRevisionBadge}</td>
        `;
        tbody.appendChild(tr);
    });
    
    // Mostrar panel de resultados previos
    document.getElementById('preview-panel').classList.remove('d-none');
}

/**
 * ==========================================================================
 * MÓDULO: BANDEJA DE ALERTAS (views/alertas_view.php)
 * ==========================================================================
 */

async function initAlertasPage() {
    // 1. Cargar el catálogo completo de clientes para el autocompletado del modal
    try {
        const res = await fetchAPI('api/pagos.php?action=listar_saldos');
        if (res.success) {
            activeClientsCatalog = res.saldos || [];
        }
    } catch (err) {
        console.error('Error cargando catálogo de clientes:', err);
    }
    
    // 2. Escuchar filtros de estatus
    const filterStatus = document.getElementById('alerta-status-filter');
    if (filterStatus) {
        filterStatus.addEventListener('change', () => {
            loadAlertsList(filterStatus.value);
        });
    }
    
    // 3. Inicializar eventos del modal de resolución
    initResolutionModal();
    
    // 4. Cargar lista de alertas iniciales
    loadAlertsList('pendientes');
}

/**
 * Carga las alertas filtradas desde el endpoint PHP
 */
async function loadAlertsList(estatus) {
    const tbody = document.getElementById('tbody-alertas');
    if (!tbody) return;
    
    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">Cargando alertas...</td></tr>`;
    
    try {
        const res = await fetchAPI(`api/conciliacion.php?action=listar_alertas&estatus=${estatus}`);
        if (res.success) {
            tbody.innerHTML = '';
            const alertas = res.alertas || [];
            
            if (alertas.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No se encontraron alertas en esta sección.</td></tr>`;
                return;
            }
            
            alertas.forEach(a => {
                const tr = document.createElement('tr');
                
                // Formatear estado resuelto
                const resolvedBadge = a.resuelto 
                    ? `<span class="badge badge-pagado">RESUELTO</span>` 
                    : `<span class="badge badge-pendiente">PENDIENTE</span>`;
                
                // Botón Resolver
                const actionButton = a.resuelto 
                    ? `—` 
                    : `<button class="btn-primary btn-resolver-alerta" data-id="${a.id}">Resolver</button>`;
                
                // Sugerencias de coincidencia
                let sugerenciaText = 'Ninguna';
                if (a.cliente_sugerido) {
                    sugerenciaText = `<span class="bold text-dark">${a.cliente_sugerido.nombre_oficial}</span> <span class="badge badge-notificado">${a.porcentaje_coincidencia}%</span>`;
                }
                
                // Botellas del despacho en conflicto
                const item = a.datos_item || {};
                const botellasText = `${item.botellas_zenda || 0} Zenda / ${item.botellas_alpes || 0} Alpes`;
                
                tr.innerHTML = `
                    <td class="bold">#${a.id}</td>
                    <td>${formatDate(a.fecha)}</td>
                    <td class="bold">${a.nombre_raw}</td>
                    <td>${botellasText}</td>
                    <td><small class="text-muted">${item.observaciones_chofer || 'Ninguna'}</small></td>
                    <td>${sugerenciaText}</td>
                    <td>${resolvedBadge}</td>
                    <td class="text-right">${actionButton}</td>
                `;
                
                // Evento click al botón resolver
                const btnRes = tr.querySelector('.btn-resolver-alerta');
                if (btnRes) {
                    btnRes.addEventListener('click', () => {
                        openResolutionModal(a);
                    });
                }
                
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${res.message}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error al cargar alertas.</td></tr>`;
        console.error(err);
    }
}

/**
 * Inicializar eventos del modal de resolución
 */
function initResolutionModal() {
    const modal = document.getElementById('modal-alerta');
    if (!modal) return;
    
    const closeBtn = modal.querySelector('.modal-close');
    const cancelBtn = document.getElementById('btn-cerrar-modal-alerta');
    const confirmBtn = document.getElementById('btn-confirmar-vinculo');
    const createBtn = document.getElementById('btn-confirmar-crear');
    
    const tabButtons = modal.querySelectorAll('.modal-tab-btn');
    const tabContents = modal.querySelectorAll('.modal-tab-content');
    
    const inputSearch = document.getElementById('alerta-search-cliente');
    const dropdown = document.getElementById('alerta-autocomplete-dropdown');
    
    // Cambiar entre pestañas
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            const tabId = btn.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
            
            if (tabId === 'tab-vincular') {
                confirmBtn.classList.remove('d-none');
                createBtn.classList.add('d-none');
            } else {
                confirmBtn.classList.add('d-none');
                createBtn.classList.remove('d-none');
            }
        });
    });
    
    const closeModal = () => {
        modal.classList.remove('active');
        inputSearch.value = '';
        dropdown.classList.add('d-none');
        
        // Resetear pestañas a la primera por defecto
        tabButtons.forEach(b => b.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));
        tabButtons[0].classList.add('active');
        tabContents[0].classList.add('active');
        confirmBtn.classList.remove('d-none');
        createBtn.classList.add('d-none');
        
        // Resetear campos del formulario de creación
        document.getElementById('alerta-new-nombre-oficial').value = '';
        document.getElementById('alerta-new-alias').value = '';
        document.getElementById('alerta-new-telefono').value = '';
        document.getElementById('alerta-new-categoria').value = 'local';
        
        currentAlertIdToResolve = null;
        currentSelectedClientId = null;
    };
    
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    
    // Entrada en búsqueda de cliente
    inputSearch.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        if (query.length < 2) {
            dropdown.classList.add('d-none');
            return;
        }
        
        // Filtrar catálogo local
        const filtered = activeClientsCatalog.filter(c => 
            c.nombre_oficial.toLowerCase().includes(query) || 
            c.nombre_despacho_alias.toLowerCase().includes(query)
        );
        
        renderAutocompleteList(filtered, dropdown, inputSearch);
    });
    
    // Ocultar dropdown al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!inputSearch.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });
    
    // Confirmar vinculación de cliente existente
    confirmBtn.addEventListener('click', async () => {
        if (!currentAlertIdToResolve || !currentSelectedClientId) {
            alert('Por favor, busque y seleccione un cliente oficial del catálogo.');
            return;
        }
        
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Procesando...';
        
        try {
            const res = await fetchAPI('api/conciliacion.php?action=resolver_alerta', {
                method: 'POST',
                body: JSON.stringify({
                    alerta_id: currentAlertIdToResolve,
                    cliente_id: currentSelectedClientId
                })
            });
            
            if (res.success) {
                alert('Alerta resuelta con éxito. El despacho ha sido asignado al cliente.');
                closeModal();
                // Recargar lista
                const filterStatus = document.getElementById('alerta-status-filter');
                loadAlertsList(filterStatus ? filterStatus.value : 'pendientes');
            } else {
                alert('Error al resolver alerta: ' + res.message);
            }
        } catch (err) {
            console.error(err);
            alert('Ocurrió un error al procesar la vinculación.');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Vincular y Confirmar';
        }
    });
    
    // Registrar y vincular cliente nuevo (Puntos 2 y 6)
    createBtn.addEventListener('click', async () => {
        const nombreOficial = document.getElementById('alerta-new-nombre-oficial').value.trim();
        const aliasDespacho = document.getElementById('alerta-new-alias').value.trim();
        const telefono = document.getElementById('alerta-new-telefono').value.trim();
        const categoria = document.getElementById('alerta-new-categoria').value;
        
        if (!nombreOficial || !aliasDespacho || !telefono) {
            alert('Por favor complete todos los datos requeridos para registrar el nuevo cliente.');
            return;
        }
        
        createBtn.disabled = true;
        createBtn.textContent = 'Registrando...';
        
        try {
            const res = await fetchAPI('api/conciliacion.php?action=crear_cliente_y_resolver', {
                method: 'POST',
                body: JSON.stringify({
                    alerta_id: currentAlertIdToResolve,
                    nombre_oficial: nombreOficial,
                    nombre_despacho_alias: aliasDespacho,
                    telefono_whatsapp: telefono,
                    categoria: categoria
                })
            });
            
            if (res.success) {
                alert('Cliente creado con éxito y despacho vinculado.');
                closeModal();
                
                // Recargar catálogo local de clientes
                const resSaldos = await fetchAPI('api/pagos.php?action=listar_saldos');
                if (resSaldos.success) {
                    activeClientsCatalog = resSaldos.saldos || [];
                }
                
                // Recargar tabla de alertas
                const filterStatus = document.getElementById('alerta-status-filter');
                loadAlertsList(filterStatus ? filterStatus.value : 'pendientes');
            } else {
                alert('Error: ' + res.message);
            }
        } catch (err) {
            console.error(err);
            alert('Ocurrió un error al registrar el cliente.');
        } finally {
            createBtn.disabled = false;
            createBtn.textContent = 'Registrar y Vincular';
        }
    });
}

/**
 * Abre el modal y rellena los datos de la alerta
 */
function openResolutionModal(alerta) {
    const modal = document.getElementById('modal-alerta');
    if (!modal) return;
    
    currentAlertIdToResolve = alerta.id;
    
    // Rellenar información visual de la alerta
    document.getElementById('lbl-alerta-nombre-raw').textContent = alerta.nombre_raw;
    document.getElementById('lbl-alerta-detalles').textContent = 
        `Fecha: ${formatDate(alerta.fecha)} | Botellas: ${alerta.datos_item?.botellas_zenda || 0} Zenda / ${alerta.datos_item?.botellas_alpes || 0} Alpes`;
    
    // Rellenar por defecto el alias con el nombre raw de la alerta (Ahorro de tiempo para el operador)
    document.getElementById('alerta-new-alias').value = alerta.nombre_raw;
    document.getElementById('alerta-new-nombre-oficial').value = '';
    document.getElementById('alerta-new-telefono').value = '';
    
    const inputSearch = document.getElementById('alerta-search-cliente');
    
    // Si hay un cliente sugerido con alta coincidencia, lo rellenamos por defecto para ahorrar tiempo
    if (alerta.cliente_sugerido) {
        inputSearch.value = alerta.cliente_sugerido.nombre_oficial;
        currentSelectedClientId = alerta.cliente_sugerido.id;
    } else {
        inputSearch.value = '';
        currentSelectedClientId = null;
    }
    
    modal.classList.add('active');
}

/**
 * Renderiza la lista autocompletada en el dropdown del modal
 */
function renderAutocompleteList(list, dropdown, inputSearch) {
    dropdown.innerHTML = '';
    
    if (list.length === 0) {
        dropdown.innerHTML = `<div class="autocomplete-item text-muted">No se encontraron clientes.</div>`;
        dropdown.classList.remove('d-none');
        return;
    }
    
    list.forEach(c => {
        const div = document.createElement('div');
        div.className = 'autocomplete-item';
        div.innerHTML = `<span class="bold">${c.nombre_oficial}</span> <small class="text-muted">(Alias: ${c.nombre_despacho_alias})</small>`;
        
        div.addEventListener('click', () => {
            inputSearch.value = c.nombre_oficial;
            currentSelectedClientId = c.cliente_id || c.id;
            dropdown.classList.add('d-none');
        });
        
        dropdown.appendChild(div);
    });
    
    dropdown.classList.remove('d-none');
}
