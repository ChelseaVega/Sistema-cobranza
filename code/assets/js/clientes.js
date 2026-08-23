// -------------------------------------------------------------
// CLIENTES.JS: CONTROLADOR DE GESTIÓN Y CATÁLOGO DE CLIENTES
// -------------------------------------------------------------

let clientsList = [];
let searchTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('clientes-view')) {
        initClientesPage();
    }
});

function initClientesPage() {
    loadClients();

    // Filtros de búsqueda
    const searchInput = document.getElementById('clientes-search');
    const categoryFilter = document.getElementById('clientes-filtro-categoria');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                loadClients(e.target.value.trim(), categoryFilter ? categoryFilter.value : '');
            }, 300);
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', (e) => {
            const query = searchInput ? searchInput.value.trim() : '';
            loadClients(query, e.target.value);
        });
    }

    // Modal de Cliente
    initClienteModal();
}

async function loadClients(query = '', categoria = '') {
    const tbody = document.getElementById('tbody-clientes');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">Cargando directorio de clientes...</td></tr>`;

    try {
        let url = `api/clientes.php?action=listar`;
        if (query) url += `&q=${encodeURIComponent(query)}`;
        if (categoria) url += `&categoria=${encodeURIComponent(categoria)}`;

        const res = await fetchAPI(url);
        if (res.success) {
            clientsList = res.clientes || [];
            renderClientsTable(clientsList);

            const badge = document.getElementById('total-clientes-badge');
            if (badge) badge.textContent = res.total ?? clientsList.length;
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error: ${res.message}</td></tr>`;
        }
    } catch (err) {
        console.error('Error cargando clientes:', err);
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error al conectar con el servidor.</td></tr>`;
    }
}

function renderClientsTable(list) {
    const tbody = document.getElementById('tbody-clientes');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No se encontraron clientes registrados.</td></tr>`;
        return;
    }

    list.forEach(c => {
        const tr = document.createElement('tr');

        const catMap = {
            'local': '<span class="badge badge-notificado">Local</span>',
            'domicilio': '<span class="badge badge-pagado">Domicilio</span>',
            'facturacion_legal': '<span class="badge badge-parcial">Legal</span>'
        };
        const categoriaBadge = catMap[c.categoria] || `<span class="badge">${c.categoria}</span>`;

        const statusBadge = (parseInt(c.activo) === 1)
            ? `<span class="badge badge-pagado">ACTIVO</span>`
            : `<span class="badge badge-pendiente">INACTIVO</span>`;

        const deudaMonto = parseFloat(c.monto_deuda_usd || 0.0);
        let deudaDisplay = `<span class="text-muted">$0.00</span>`;
        if (deudaMonto > 0) {
            deudaDisplay = `<span class="bold text-dark">${formatUSD(deudaMonto)}</span><br><small class="text-muted">${c.botellas_zenda} Z / ${c.botellas_alpes} A</small>`;
        }

        tr.innerHTML = `
            <td class="bold">#${c.id}</td>
            <td class="bold text-dark">${escapeHTML(c.nombre_oficial)}</td>
            <td><code>${escapeHTML(c.nombre_despacho_alias)}</code></td>
            <td>${escapeHTML(c.telefono_whatsapp)}</td>
            <td>${categoriaBadge}</td>
            <td>${deudaDisplay}</td>
            <td>${statusBadge}</td>
            <td class="text-right">
                <button class="btn-edit-cliente btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; background-color: var(--light-bg); border-color: var(--border-color);" data-id="${c.id}" title="Editar Cliente">
                    Editar
                </button>
            </td>
        `;

        const btnEdit = tr.querySelector('.btn-edit-cliente');
        if (btnEdit) {
            btnEdit.addEventListener('click', () => openClienteModal(c));
        }

        tbody.appendChild(tr);
    });
}

function initClienteModal() {
    const modal = document.getElementById('modal-cliente');
    if (!modal) return;

    const btnNuevo = document.getElementById('btn-nuevo-cliente');
    const closeBtn = modal.querySelector('.modal-close');
    const cancelBtn = document.getElementById('btn-cerrar-modal-cliente');
    const form = document.getElementById('form-cliente');

    const closeModal = () => {
        modal.classList.remove('active');
        form.reset();
        document.getElementById('cliente-form-id').value = '';
    };

    if (btnNuevo) {
        btnNuevo.addEventListener('click', () => {
            document.getElementById('modal-cliente-titulo').textContent = 'Registrar Nuevo Cliente';
            document.getElementById('cliente-form-id').value = '';
            form.reset();
            modal.classList.add('active');
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('cliente-form-id').value.trim();
            const nombreOficial = document.getElementById('cliente-nombre-oficial').value.trim();
            const aliasDespacho = document.getElementById('cliente-alias-despacho').value.trim();
            const telefono = document.getElementById('cliente-telefono').value.trim();
            const categoria = document.getElementById('cliente-categoria').value;

            if (!nombreOficial || !aliasDespacho || !telefono) {
                alert('Por favor complete todos los campos obligatorios.');
                return;
            }

            const btnSubmit = document.getElementById('btn-guardar-cliente');
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Guardando...';

            try {
                const isEdit = id !== '';
                const url = isEdit ? 'api/clientes.php?action=actualizar' : 'api/clientes.php?action=crear';

                const payload = {
                    id: isEdit ? parseInt(id) : undefined,
                    nombre_oficial: nombreOficial,
                    nombre_despacho_alias: aliasDespacho,
                    telefono_whatsapp: telefono,
                    categoria: categoria
                };

                const res = await fetchAPI(url, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                if (res.success) {
                    alert(isEdit ? 'Cliente actualizado correctamente.' : 'Cliente registrado exitosamente.');
                    closeModal();
                    loadClients();
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (err) {
                console.error(err);
                alert('Error en la operación: ' + err.message);
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Guardar Cliente';
            }
        });
    }
}

function openClienteModal(cliente) {
    const modal = document.getElementById('modal-cliente');
    if (!modal) return;

    document.getElementById('modal-cliente-titulo').textContent = `Editar Cliente #${cliente.id}`;
    document.getElementById('cliente-form-id').value = cliente.id;
    document.getElementById('cliente-nombre-oficial').value = cliente.nombre_oficial;
    document.getElementById('cliente-alias-despacho').value = cliente.nombre_despacho_alias;
    document.getElementById('cliente-telefono').value = cliente.telefono_whatsapp;
    document.getElementById('cliente-categoria').value = cliente.categoria;

    modal.classList.add('active');
}

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}
