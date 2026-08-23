// -------------------------------------------------------------
// CHOFERES.JS: CONTROLADOR DE GESTIÓN DE CHOFERES
// -------------------------------------------------------------

let choferesList = [];
let choferSearchTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('choferes-view')) {
        initChoferesPage();
    }
});

function initChoferesPage() {
    loadChoferes();

    const searchInput = document.getElementById('choferes-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            clearTimeout(choferSearchTimer);
            choferSearchTimer = setTimeout(() => {
                loadChoferes(e.target.value.trim());
            }, 300);
        });
    }

    initChoferModal();
}

async function loadChoferes(query = '') {
    const tbody = document.getElementById('tbody-choferes');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Cargando lista de choferes...</td></tr>`;

    try {
        let url = `api/choferes.php?action=listar`;
        if (query) url += `&q=${encodeURIComponent(query)}`;

        const res = await fetchAPI(url);
        if (res.success) {
            choferesList = res.choferes || [];
            renderChoferesTable(choferesList);

            const badge = document.getElementById('total-choferes-badge');
            if (badge) badge.textContent = res.total ?? choferesList.length;
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error: ${res.message}</td></tr>`;
        }
    } catch (err) {
        console.error('Error cargando choferes:', err);
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error al conectar con el servidor.</td></tr>`;
    }
}

function renderChoferesTable(list) {
    const tbody = document.getElementById('tbody-choferes');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No se encontraron choferes registrados.</td></tr>`;
        return;
    }

    list.forEach(c => {
        const tr = document.createElement('tr');

        const statusBadge = (parseInt(c.activo) === 1)
            ? `<span class="badge badge-pagado">ACTIVO</span>`
            : `<span class="badge badge-pendiente">INACTIVO</span>`;

        tr.innerHTML = `
            <td class="bold">#${c.id}</td>
            <td class="bold text-dark">${escapeHTML(c.nombre)}</td>
            <td>${escapeHTML(c.telefono || '—')}</td>
            <td class="text-center"><span class="badge badge-notificado">${c.total_despachos || 0} despachos</span></td>
            <td>${statusBadge}</td>
            <td class="text-right">
                <button class="btn-edit-chofer btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; background-color: var(--light-bg); border-color: var(--border-color);" data-id="${c.id}" title="Editar Chofer">
                    Editar
                </button>
            </td>
        `;

        const btnEdit = tr.querySelector('.btn-edit-chofer');
        if (btnEdit) {
            btnEdit.addEventListener('click', () => openChoferModal(c));
        }

        tbody.appendChild(tr);
    });
}

function initChoferModal() {
    const modal = document.getElementById('modal-chofer');
    if (!modal) return;

    const btnNuevo = document.getElementById('btn-nuevo-chofer');
    const closeBtn = modal.querySelector('.modal-close');
    const cancelBtn = document.getElementById('btn-cerrar-modal-chofer');
    const form = document.getElementById('form-chofer');

    const closeModal = () => {
        modal.classList.remove('active');
        form.reset();
        document.getElementById('chofer-form-id').value = '';
    };

    if (btnNuevo) {
        btnNuevo.addEventListener('click', () => {
            document.getElementById('modal-chofer-titulo').textContent = 'Registrar Nuevo Chofer';
            document.getElementById('chofer-form-id').value = '';
            form.reset();
            modal.classList.add('active');
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('chofer-form-id').value.trim();
            const nombre = document.getElementById('chofer-nombre').value.trim();
            const telefono = document.getElementById('chofer-telefono').value.trim();

            if (!nombre) {
                alert('El nombre del chofer es obligatorio.');
                return;
            }

            const btnSubmit = document.getElementById('btn-guardar-chofer');
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Guardando...';

            try {
                const isEdit = id !== '';
                const url = isEdit ? 'api/choferes.php?action=actualizar' : 'api/choferes.php?action=crear';

                const payload = {
                    id: isEdit ? parseInt(id) : undefined,
                    nombre: nombre,
                    telefono: telefono
                };

                const res = await fetchAPI(url, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                if (res.success) {
                    alert(isEdit ? 'Chofer actualizado correctamente.' : 'Chofer registrado exitosamente.');
                    closeModal();
                    loadChoferes();
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (err) {
                console.error(err);
                alert('Error en la operación: ' + err.message);
            } finally {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Guardar Chofer';
            }
        });
    }
}

function openChoferModal(chofer) {
    const modal = document.getElementById('modal-chofer');
    if (!modal) return;

    document.getElementById('modal-chofer-titulo').textContent = `Editar Chofer #${chofer.id}`;
    document.getElementById('chofer-form-id').value = chofer.id;
    document.getElementById('chofer-nombre').value = chofer.nombre;
    document.getElementById('chofer-telefono').value = chofer.telefono || '';

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
