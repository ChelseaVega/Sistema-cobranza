// -------------------------------------------------------------
// APP.JS: LOGICA GLOBAL, CONFIGURACIÓN Y DASHBOARD CONTROLLER
// -------------------------------------------------------------

// URL base del backend
const API_BASE = 'api';

// Inicialización global
document.addEventListener('DOMContentLoaded', () => {
    initGlobalEvents();
    
    // Si estamos en el Dashboard, inicializar su controlador
    if (document.getElementById('dashboard-view')) {
        initDashboard();
    }
});

/**
 * Eventos globales (Logout, Modales, etc.)
 */
function initGlobalEvents() {
    // Manejo de Logout
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', async (e) => {
            e.preventDefault();
            if (confirm('¿Está seguro de que desea cerrar la sesión?')) {
                try {
                    const res = await fetchAPI(`${API_BASE}/logout.php`);
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert('Error al cerrar sesión: ' + res.message);
                    }
                } catch (err) {
                    console.error('Logout error:', err);
                }
            }
        });
    }

    // Modal de Catálogos y Tarifas
    const btnConfig = document.getElementById('btn-config-catalogos');
    const modalConfig = document.getElementById('modal-config');
    
    if (btnConfig && modalConfig) {
        btnConfig.addEventListener('click', (e) => {
            e.preventDefault();
            openConfigModal();
        });
        
        // Cerrar modal
        const closeBtn = modalConfig.querySelector('.modal-close');
        const cancelBtn = document.getElementById('btn-cerrar-modal-config');
        
        const closeModal = () => modalConfig.classList.remove('active');
        
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        
        // Guardar precios
        const formConfig = document.getElementById('form-config-precios');
        if (formConfig) {
            formConfig.addEventListener('submit', async (e) => {
                e.preventDefault();
                const precioZenda = parseFloat(document.getElementById('config-precio-zenda').value);
                const precioAlpes = parseFloat(document.getElementById('config-precio-alpes').value);
                
                if (isNaN(precioZenda) || isNaN(precioAlpes) || precioZenda < 0 || precioAlpes < 0) {
                    alert('Por favor ingrese precios válidos mayores o iguales a 0.');
                    return;
                }
                
                try {
                    const res = await fetchAPI(`${API_BASE}/configuracion.php?action=guardar_precios`, {
                        method: 'POST',
                        body: JSON.stringify({
                            precios: [
                                { id: 1, precio_usd: precioZenda }, // La Zenda
                                { id: 2, precio_usd: precioAlpes }  // Los Alpes
                            ]
                        })
                    });
                    
                    if (res.success) {
                        alert('Precios actualizados con éxito.');
                        closeModal();
                        // Si estamos en el dashboard, refrescar datos
                        if (document.getElementById('dashboard-view')) {
                            loadDashboardData();
                        }
                    } else {
                        alert('Error al guardar: ' + res.message);
                    }
                } catch (err) {
                    console.error('Error guardando precios:', err);
                }
            });
        }
    }
}

/**
 * Carga de datos del modal de configuración
 */
async function openConfigModal() {
    const modalConfig = document.getElementById('modal-config');
    try {
        const res = await fetchAPI(`${API_BASE}/configuracion.php?action=listar`);
        if (res.success) {
            const marcas = res.marcas_agua;
            
            // Buscar marcas en el resultado
            const zenda = marcas.find(m => m.codigo_identificador === 'zenda');
            const alpes = marcas.find(m => m.codigo_identificador === 'alpes');
            
            if (zenda) document.getElementById('config-precio-zenda').value = zenda.precio_usd;
            if (alpes) document.getElementById('config-precio-alpes').value = alpes.precio_usd;
            
            modalConfig.classList.add('active');
        } else {
            alert('Error al obtener catálogos: ' + res.message);
        }
    } catch (err) {
        console.error('Error cargando configuración:', err);
    }
}

/**
 * ==========================================================================
 * CONTROLLER DEL DASHBOARD GENERAL
 * ==========================================================================
 */

let currentSelectedDate = new Date().toISOString().split('T')[0];
let currentDispatcherFilter = '';

/**
 * Función universal para copiar al portapapeles compatible con todos los navegadores
 */
async function copyTextToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            console.warn('Fallo navigator.clipboard, usando fallback textarea...', err);
        }
    }
    
    // Fallback universal con textarea invisible
    return new Promise((resolve, reject) => {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '-9999px';
        textArea.style.left = '-9999px';
        textArea.setAttribute('readonly', '');
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            if (successful) {
                resolve(true);
            } else {
                reject(new Error('No se pudo copiar el texto.'));
            }
        } catch (err) {
            document.body.removeChild(textArea);
            reject(err);
        }
    });
}

function initDashboard() {
    // Inputs de filtros y botones
    const dateInput = document.getElementById('dashboard-date-filter');
    const dispatcherInput = document.getElementById('dashboard-dispatcher-filter');
    const btnBuscar = document.getElementById('btn-dashboard-buscar');
    const btnLimpiar = document.getElementById('btn-dashboard-limpiar');
    
    // Establecer fecha de hoy en el input
    if (dateInput) {
        dateInput.value = currentSelectedDate;
        dateInput.addEventListener('change', (e) => {
            currentSelectedDate = e.target.value;
            loadDashboardData();
        });
        dateInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                currentSelectedDate = dateInput.value;
                loadDashboardData();
            }
        });
    }
    
    if (dispatcherInput) {
        let dispatcherTimer = null;
        dispatcherInput.addEventListener('input', (e) => {
            currentDispatcherFilter = e.target.value.trim();
            clearTimeout(dispatcherTimer);
            dispatcherTimer = setTimeout(() => {
                loadDashboardData();
            }, 300);
        });

        dispatcherInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                currentDispatcherFilter = dispatcherInput.value.trim();
                loadDashboardData();
            }
        });
    }

    if (btnBuscar) {
        btnBuscar.addEventListener('click', () => {
            if (dateInput) currentSelectedDate = dateInput.value;
            if (dispatcherInput) currentDispatcherFilter = dispatcherInput.value.trim();
            loadDashboardData();
        });
    }

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', () => {
            if (dispatcherInput) {
                dispatcherInput.value = '';
                currentDispatcherFilter = '';
            }
            loadDashboardData();
        });
    }
    
    // Cargar datos por primera vez
    loadDashboardData();
}

/**
 * Cargar datos de KPIs y tablas en el Dashboard desde las APIs
 */
async function loadDashboardData() {
    try {
        // 1. Consultar estatus de la fecha seleccionada para obtener alertas pendientes
        const statusRes = await fetchAPI(`${API_BASE}/conciliacion.php?action=status&fecha=${currentSelectedDate}`);
        
        if (statusRes.success) {
            // Actualizar KPI Card de Alertas
            const alertasKPI = document.getElementById('kpi-alertas-pendientes');
            const alertasCount = document.getElementById('val-alertas-pendientes');
            if (alertasCount) alertasCount.textContent = statusRes.alertas_pendientes;
            
            if (alertasKPI) {
                if (statusRes.alertas_pendientes > 0) {
                    alertasKPI.classList.add('kpi-warning-pulse');
                } else {
                    alertasKPI.classList.remove('kpi-warning-pulse');
                }
            }
        }
        
        // 2. Cargar Resumen de Despachos del día
        const dispatcherParam = currentDispatcherFilter
            ? `&despachador=${encodeURIComponent(currentDispatcherFilter)}`
            : '';
        const resumenRes = await fetchAPI(`${API_BASE}/conciliacion.php?action=resumen&fecha=${currentSelectedDate}${dispatcherParam}`);
        renderDespachosTable(resumenRes.despachos || []);
        
        // 3. Cargar Cola de Cobranza WhatsApp
        const cobranzaRes = await fetchAPI(`${API_BASE}/cobranza.php?action=cola&fecha=${currentSelectedDate}${dispatcherParam}`);
        renderColaCobranzaTable(cobranzaRes.cola || []);
        
        // 4. Actualizar KPIs de totales despachados, deuda total, y cobranza
        updateKpis(resumenRes.despachos || [], cobranzaRes.cola || []);
        
    } catch (err) {
        console.error('Error cargando datos del dashboard:', err);
    }
}

/**
 * Renderiza la tabla de despachos del día
 */
function renderDespachosTable(despachos) {
    const tbody = document.getElementById('tbody-resumen-despachos');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (despachos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No existen despachos para la fecha o el chofer seleccionado.</td></tr>`;
        return;
    }
    
    despachos.forEach(d => {
        const tr = document.createElement('tr');
        tr.className = 'despacho-row';
        tr.setAttribute('data-despachador', (d.despachador || '').toLowerCase());
        
        let badgeClass = 'badge-pendiente';
        if (d.estado_pago === 'notificado') badgeClass = 'badge-notificado';
        if (d.estado_pago === 'pagado_parcial') badgeClass = 'badge-parcial';
        if (d.estado_pago === 'pagado') badgeClass = 'badge-pagado';

        const nombreCliente = d.cliente || d.nombre_cliente_raw || d.alias_despacho_consolidado || 'Sin nombre';
        
        tr.innerHTML = `
            <td class="bold">#${d.id}</td>
            <td>${formatDate(d.fecha)}</td>
            <td class="bold">${nombreCliente}</td>
            <td>${d.despachador}</td>
            <td>${d.botellas_zenda} Zenda / ${d.botellas_alpes} Alpes</td>
            <td class="bold">${formatUSD(d.monto_despacho_usd)}</td>
            <td>${d.forma_pago || '—'}</td>
            <td><span class="badge ${badgeClass}">${(d.estado_pago || 'pendiente').toUpperCase()}</span></td>
        `;
        tbody.appendChild(tr);
    });
}

/**
 * Renderiza la tabla de Cola de Cobranza WhatsApp con botón de copiado
 */
function renderColaCobranzaTable(cola) {
    const tbody = document.getElementById('tbody-cola-cobranza');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (cola.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No hay clientes pendientes en la cola de cobranza.</td></tr>`;
        return;
    }
    
    cola.forEach(item => {
        const tr = document.createElement('tr');
        tr.className = 'cola-row';
        
        // Estilos e íconos de estado
        const recibioHoyIcon = item.despacho_hoy.recibio_hoy 
            ? `<span class="badge badge-pagado">SÍ</span>` 
            : `<span class="badge badge-pendiente">NO (Deuda)</span>`;
            
        // Formatear estado de notificación
        const esNotificado = (item.estado_pago_hoy === 'notificado');
        const btnText = esNotificado ? 'Copiado (Volver a copiar)' : 'Copiar Mensaje';
        const btnClass = esNotificado ? 'btn-secondary' : 'btn-primary';
        
        tr.innerHTML = `
            <td class="bold">${item.nombre_oficial}</td>
            <td>${item.telefono_whatsapp}</td>
            <td class="text-center">${recibioHoyIcon}</td>
            <td>
                <span class="bold text-dark">${item.totales_consolidados.total_botellas_global} bot.</span><br>
                <small class="text-muted">(${item.totales_consolidados.total_botellas_alpes} Alpes / ${item.totales_consolidados.total_botellas_zenda} Zenda)</small>
            </td>
            <td class="bold">${formatUSD(item.totales_consolidados.monto_deuda_total_usd)}</td>
            <td>
                <span class="badge ${item.estado_pago_hoy === 'notificado' ? 'badge-notificado' : 'badge-pendiente'}">
                    ${item.estado_pago_hoy.toUpperCase()}
                </span>
            </td>
            <td class="text-right">
                <button class="btn-action-copiar ${btnClass}" data-id="${item.cliente_id}">
                    ${btnText}
                </button>
            </td>
        `;
        
        // Hook de botón copiar con fallback universal
        const btnCopiar = tr.querySelector('.btn-action-copiar');
        btnCopiar.addEventListener('click', async () => {
            try {
                await copyTextToClipboard(item.mensaje_texto);
                btnCopiar.textContent = '¡Copiado con Éxito!';
                btnCopiar.style.backgroundColor = 'var(--secondary)';
                btnCopiar.style.borderColor = 'var(--secondary)';

                // Notificar al backend para que actualice a 'notificado' si estaba pendiente
                if (item.estado_pago_hoy === 'pendiente') {
                    try {
                        const res = await fetchAPI(`${API_BASE}/cobranza.php?action=notificar`, {
                            method: 'POST',
                            body: JSON.stringify({
                                cliente_id: item.cliente_id,
                                fecha: currentSelectedDate
                            })
                        });
                        if (res.success) {
                            setTimeout(() => {
                                loadDashboardData();
                            }, 500);
                        }
                    } catch (err) {
                        console.error('Error al actualizar estatus:', err);
                    }
                }
            } catch (err) {
                console.error('Error copiando:', err);
                alert('No se pudo copiar automáticamente. Por favor seleccione y copie el texto manualmente.');
            }
        });
        
        tbody.appendChild(tr);
    });
}

/**
 * Actualiza las tarjetas KPI principales en base a las listas recibidas
 */
function updateKpis(despachos, cola) {
    // 1. Total Botellas Despachadas Hoy
    const valTotalBotellas = document.getElementById('val-total-botellas-hoy');
    const valDesgloseBotellas = document.getElementById('desc-total-botellas-hoy');
    
    let zendaHoy = 0;
    let alpesHoy = 0;
    despachos.forEach(d => {
        zendaHoy += parseInt(d.botellas_zenda);
        alpesHoy += parseInt(d.botellas_alpes);
    });
    
    if (valTotalBotellas) valTotalBotellas.textContent = zendaHoy + alpesHoy;
    if (valDesgloseBotellas) {
        valDesgloseBotellas.textContent = `${zendaHoy} La Zenda | ${alpesHoy} Los Alpes`;
    }
    
    // 2. Deuda Total Recuperable
    const valDeudaTotal = document.getElementById('val-deuda-recuperable');
    let sumaDeuda = 0.0;
    
    // Sumar de la cola (que incluye saldos de hoy + acumulados de inactivos)
    cola.forEach(c => {
        sumaDeuda += parseFloat(c.totales_consolidados.monto_deuda_total_usd);
    });
    
    if (valDeudaTotal) valDeudaTotal.textContent = formatUSD(sumaDeuda);
    
    // 3. Estatus de Cobranza WhatsApp (Mensajes Enviados vs Pendientes)
    const valCobranzaStatus = document.getElementById('val-cobranza-status');
    const descCobranzaStatus = document.getElementById('desc-cobranza-status');
    
    let totalMensajes = cola.length;
    let enviados = 0;
    
    cola.forEach(c => {
        if (c.estado_pago_hoy === 'notificado' || c.estado_pago_hoy === 'pagado') {
            enviados++;
        }
    });
    
    if (valCobranzaStatus) {
        valCobranzaStatus.textContent = `${enviados} / ${totalMensajes}`;
    }
    if (descCobranzaStatus) {
        const pendientes = totalMensajes - enviados;
        descCobranzaStatus.textContent = `${pendientes} pendientes por enviar`;
    }
}

/**
 * Filtra localmente la tabla de despachos y la cola de cobranza según despachador
 */
function filterTablesLocally() {
    const rowsDespachos = document.querySelectorAll('.despacho-row');
    rowsDespachos.forEach(row => {
        const despachador = row.getAttribute('data-despachador') || '';
        if (despachador.includes(currentDispatcherFilter)) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });
}

/**
 * ==========================================================================
 * UTILERÍAS COMUNES
 * ==========================================================================
 */

/**
 * Helper para peticiones Fetch
 */
async function fetchAPI(url, options = {}) {
    const defaultHeaders = {
        'Content-Type': 'application/json'
    };
    
    options.headers = Object.assign(defaultHeaders, options.headers || {});
    
    const response = await fetch(url, options);
    
    if (response.status === 401) {
        // Redirigir a login si expira la sesión o no está logueado
        window.location.reload();
        return;
    }
    
    if (!response.ok) {
        const errData = await response.json().catch(() => ({}));
        throw new Error(errData.message || `Error HTTP ${response.status}`);
    }
    
    return await response.json();
}

/**
 * Formatear montos a USD ($ X.XX)
 */
function formatUSD(value) {
    const num = parseFloat(value);
    if (isNaN(num)) return '$0.00';
    return '$' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Formatear fecha YYYY-MM-DD a DD/MM/YYYY
 */
function formatDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}
