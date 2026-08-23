// -------------------------------------------------------------
// PAGOS.JS: FORMULARIO DE PAGOS Y CONTROLADOR DE CARTERA
// -------------------------------------------------------------

let clientsBalancesList = [];
let paymentMethodsList = [];
let selectedClientForPayment = null;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('pagos-view')) {
        initPagosPage();
    }
});

/**
 * Inicializar todos los componentes de la vista de pagos
 */
async function initPagosPage() {
    // 1. Cargar formas de pago en el selector
    await loadPaymentMethods();
    
    // 2. Cargar listado de cartera de clientes (Tabla derecha)
    await loadClientsBalances();
    
    // 3. Inicializar autocompletado en el formulario
    initClientSearchForm();
    
    // 4. Inicializar filtros locales en la cabecera
    initPagosFilters();
    
    // 5. Inicializar envío de formulario
    initPaymentFormSubmit();
}

/**
 * Carga los métodos de pago activos desde el backend
 */
async function loadPaymentMethods() {
    const selectForma = document.getElementById('pago-forma-select');
    if (!selectForma) return;
    
    try {
        const res = await fetchAPI('api/pagos.php?action=listar_formas_pago');
        if (res.success) {
            paymentMethodsList = res.formas_pago || [];
            
            // Vaciar select excepto la primera opción
            selectForma.innerHTML = '<option value="">Seleccione un método de pago</option>';
            
            paymentMethodsList.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id;
                opt.textContent = `${f.nombre_forma} (${f.moneda_defecto})`;
                selectForma.appendChild(opt);
            });
            
            // Evento para habilitar/deshabilitar referencia bancaria
            selectForma.addEventListener('change', (e) => {
                const selectedId = parseInt(e.target.value);
                const method = paymentMethodsList.find(m => m.id === selectedId);
                const refInput = document.getElementById('pago-referencia');
                
                if (method) {
                    refInput.disabled = !method.requiere_referencia;
                    if (!method.requiere_referencia) refInput.value = '';
                } else {
                    refInput.disabled = true;
                    refInput.value = '';
                }
            });
        }
    } catch (err) {
        console.error('Error cargando métodos de pago:', err);
    }
}

/**
 * Carga los saldos de los clientes desde el backend y renderiza la tabla
 */
async function loadClientsBalances() {
    const tbody = document.getElementById('tbody-saldos-pendientes');
    if (!tbody) return;
    
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">Cargando saldos...</td></tr>`;
    
    try {
        const res = await fetchAPI('api/pagos.php?action=listar_saldos');
        if (res.success) {
            clientsBalancesList = res.saldos || [];
            renderBalancesTable(clientsBalancesList);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error: ${res.message}</td></tr>`;
        }
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error al cargar saldos.</td></tr>`;
    }
}

/**
 * Renderiza la tabla de saldos de clientes
 */
function renderBalancesTable(list) {
    const tbody = document.getElementById('tbody-saldos-pendientes');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No se encontraron clientes con saldos.</td></tr>`;
        return;
    }
    
    list.forEach(c => {
        const tr = document.createElement('tr');
        tr.className = 'saldo-row';
        tr.setAttribute('data-name', c.nombre_oficial.toLowerCase());
        tr.setAttribute('data-alias', c.nombre_despacho_alias.toLowerCase());
        
        const deudaUsd = parseFloat(c.monto_deuda_total_usd);
        const esAlDia = (deudaUsd <= 0.0);
        
        const badgeClass = esAlDia ? 'badge-pagado' : 'badge-pendiente';
        const badgeText = esAlDia ? 'AL DÍA' : 'CON DEUDA';
        
        // Botón rápido para cobrar
        const quickCobrarBtn = esAlDia 
            ? `—` 
            : `<button class="btn-action-cobrar-rapido action-btn-circle" data-id="${c.cliente_id}" title="Cobrar Deuda">
                 <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/></svg>
               </button>`;
        
        tr.innerHTML = `
            <td class="bold">#${c.cliente_id}</td>
            <td>
                <span class="bold text-dark">${c.nombre_oficial}</span><br>
                <small class="text-muted">Alias: ${c.nombre_despacho_alias}</small>
            </td>
            <td>${c.botellas_pendientes_zenda} Zenda</td>
            <td>${c.botellas_pendientes_alpes} Alpes</td>
            <td class="bold text-right">${formatUSD(deudaUsd)}</td>
            <td><span class="badge ${badgeClass}">${badgeText}</span></td>
            <td class="text-center">${quickCobrarBtn}</td>
        `;
        
        // Enlazar botón rápido de cobro
        const btnQuick = tr.querySelector('.btn-action-cobrar-rapido');
        if (btnQuick) {
            btnQuick.addEventListener('click', () => {
                selectClientForPaymentForm(c);
            });
        }
        
        tbody.appendChild(tr);
    });
    
    // Actualizar total de la cartera activa
    const totalDeudaGlobal = list.reduce((sum, item) => sum + parseFloat(item.monto_deuda_total_usd), 0.0);
    const labelTotal = document.getElementById('label-deuda-total-activa');
    if (labelTotal) {
        labelTotal.textContent = formatUSD(totalDeudaGlobal);
    }
}

/**
 * Configurar buscador autocompletado del formulario de pagos
 */
function initClientSearchForm() {
    const inputSearch = document.getElementById('pago-search-cliente');
    const dropdown = document.getElementById('pago-autocomplete-dropdown');
    
    if (!inputSearch || !dropdown) return;
    
    inputSearch.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        if (query.length < 2) {
            dropdown.classList.add('d-none');
            return;
        }
        
        // Filtrar clientes
        const filtered = clientsBalancesList.filter(c => 
            c.nombre_oficial.toLowerCase().includes(query) ||
            c.nombre_despacho_alias.toLowerCase().includes(query)
        );
        
        renderFormAutocomplete(filtered, dropdown, inputSearch);
    });
    
    document.addEventListener('click', (e) => {
        if (!inputSearch.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });
}

/**
 * Renderiza la lista del autocompletado en el formulario
 */
function renderFormAutocomplete(list, dropdown, inputSearch) {
    dropdown.innerHTML = '';
    
    if (list.length === 0) {
        dropdown.innerHTML = `<div class="autocomplete-item text-muted">No se encontraron clientes.</div>`;
        dropdown.classList.remove('d-none');
        return;
    }
    
    list.forEach(c => {
        const div = document.createElement('div');
        div.className = 'autocomplete-item';
        div.innerHTML = `<span class="bold">${c.nombre_oficial}</span> <small class="text-muted">(Deuda: ${formatUSD(c.monto_deuda_total_usd)})</small>`;
        
        div.addEventListener('click', () => {
            selectClientForPaymentForm(c);
            dropdown.classList.add('d-none');
        });
        
        dropdown.appendChild(div);
    });
    
    dropdown.classList.remove('d-none');
}

/**
 * Selecciona un cliente del catálogo y rellena sus datos en el formulario
 */
function selectClientForPaymentForm(cliente) {
    selectedClientForPayment = cliente;
    
    document.getElementById('pago-search-cliente').value = cliente.nombre_oficial;
    document.getElementById('pago-cliente-id').value = cliente.cliente_id;
    
    // Rellenar visual de deuda en el formulario
    const infoDeuda = document.getElementById('pago-info-deuda-cliente');
    if (infoDeuda) {
        infoDeuda.innerHTML = `
            <div style="background-color: rgba(32, 119, 249, 0.05); padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.85rem; border: 1px solid rgba(32, 119, 249, 0.15);">
                <strong>Deuda Pendiente:</strong> ${formatUSD(cliente.monto_deuda_total_usd)} USD<br>
                <small class="text-muted">Desglose: ${cliente.botellas_pendientes_zenda} Zenda / ${cliente.botellas_pendientes_alpes} Alpes</small>
            </div>
        `;
        infoDeuda.classList.remove('d-none');
    }
    
    // Colocar el equivalente en el input por defecto
    const inputUsd = document.getElementById('pago-monto-usd');
    if (inputUsd) {
        inputUsd.value = parseFloat(cliente.monto_deuda_total_usd).toFixed(2);
    }
}

/**
 * Filtros locales de la tabla de pagos
 */
function initPagosFilters() {
    const inputSearch = document.getElementById('pagos-search-filtro');
    
    if (inputSearch) {
        inputSearch.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            
            const rows = document.querySelectorAll('.saldo-row');
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const alias = row.getAttribute('data-alias') || '';
                
                if (name.includes(query) || alias.includes(query)) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });
        });
    }
}

/**
 * Enviar el formulario de registro de pago
 */
function initPaymentFormSubmit() {
    const form = document.getElementById('form-registro-pago');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const clienteId = parseInt(document.getElementById('pago-cliente-id').value);
        const formaPagoId = parseInt(document.getElementById('pago-forma-select').value);
        const referencia = document.getElementById('pago-referencia').value.trim();
        const montoBs = parseFloat(document.getElementById('pago-monto-bs').value);
        const montoUsd = parseFloat(document.getElementById('pago-monto-usd').value);
        
        if (isNaN(clienteId) || clienteId <= 0) {
            alert('Por favor, seleccione un cliente válido.');
            return;
        }
        if (isNaN(formaPagoId) || formaPagoId <= 0) {
            alert('Por favor, seleccione un método de pago.');
            return;
        }
        if (isNaN(montoUsd) || montoUsd <= 0.0) {
            alert('Por favor, ingrese un monto equivalente en USD válido mayor a 0.');
            return;
        }
        
        // Validar si requiere referencia bancaria
        const selectedMethod = paymentMethodsList.find(m => m.id === formaPagoId);
        if (selectedMethod && selectedMethod.requiere_referencia && referencia.length === 0) {
            alert('Este método de pago requiere número de referencia bancaria.');
            return;
        }
        
        const btnSubmit = form.querySelector('button[type="submit"]');
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Procesando Pago...';
        
        try {
            const res = await fetchAPI('api/pagos.php', {
                method: 'POST',
                body: JSON.stringify({
                    cliente_id: clienteId,
                    forma_pago_id: formaPagoId,
                    referencia_bancaria: referencia,
                    monto_cancelado_bs: isNaN(montoBs) ? null : montoBs,
                    equivalente_aproximado_usd: montoUsd,
                    operador_responsable: 'admin_sistema'
                })
            });
            
            if (res.success) {
                alert('Pago registrado y saldo conciliado correctamente.');
                form.reset();
                selectedClientForPayment = null;
                
                // Ocultar info de deuda
                const infoDeuda = document.getElementById('pago-info-deuda-cliente');
                if (infoDeuda) infoDeuda.classList.add('d-none');
                
                // Recargar saldos y cartera
                await loadClientsBalances();
            } else {
                alert('Error al registrar pago: ' + res.message);
            }
        } catch (err) {
            console.error(err);
            alert('Error en el servidor al procesar el pago.');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Registrar Pago';
        }
    });
}
