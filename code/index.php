<?php
// -------------------------------------------------------------
// ENRUTADOR PRINCIPAL Y LAYOUT GENERAL (index.php)
// -------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determinar vista activa
$view = isset($_GET['view']) ? trim($_GET['view']) : 'dashboard';

// Lista de vistas válidas
$vistasValidas = ['dashboard', 'ingesta', 'alertas', 'pagos', 'clientes'];
if (!in_array($view, $vistasValidas)) {
    $view = 'dashboard';
}

// Verificar si el usuario está logueado
$isLogged = isset($_SESSION['usuario_id']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribuidora Agua Mineral — Panel de Cobranza</title>
    <!-- Paleta de Estilos Premium -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <?php if (!$isLogged): ?>
        <!-- Si no está autenticado, renderizar la vista de login únicamente -->
        <?php include __DIR__ . '/views/login_view.php'; ?>
    <?php else: ?>
        <!-- Layout Principal para usuarios autenticados -->
        <div class="app-container">
            
            <!-- BARRA LATERAL (SIDEBAR) -->
            <aside class="sidebar">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">
                        <!-- Icono gota -->
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                        </svg>
                    </div>
                    <span class="sidebar-logo-text">Distribuidora Agua</span>
                </div>
                
                <nav style="flex-grow: 1;">
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item <?php echo ($view === 'dashboard') ? 'active' : ''; ?>">
                            <a href="index.php?view=dashboard">
                                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                                Panel de Control
                            </a>
                        </li>
                        <li class="sidebar-menu-item <?php echo ($view === 'clientes') ? 'active' : ''; ?>">
                            <a href="index.php?view=clientes">
                                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                Directorio Clientes
                            </a>
                        </li>
                        <li class="sidebar-menu-item <?php echo ($view === 'ingesta') ? 'active' : ''; ?>">
                            <a href="index.php?view=ingesta">
                                <svg viewBox="0 0 24 24"><path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                                Ingesta OCR (Carga)
                            </a>
                        </li>
                        <li class="sidebar-menu-item <?php echo ($view === 'alertas') ? 'active' : ''; ?>">
                            <a href="index.php?view=alertas">
                                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                Alertas de Nombres
                            </a>
                        </li>
                        <li class="sidebar-menu-item <?php echo ($view === 'pagos') ? 'active' : ''; ?>">
                            <a href="index.php?view=pagos">
                                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/></svg>
                                Registro de Pagos
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <!-- FOOTER DE USUARIO EN SIDEBAR -->
                <div class="sidebar-user">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                        <span class="user-role">Operador Administrativo</span>
                    </div>
                    <button id="btn-logout" class="btn-logout">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="fill:none; stroke:currentColor; stroke-width:2; stroke-linecap:round; stroke-linejoin:round;">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                        </svg>
                        Cerrar Sesión
                    </button>
                </div>
            </aside>
            
            <!-- ENVOLTURA CONTENIDO PRINCIPAL -->
            <div class="main-wrapper">
                
                <!-- CABECERA OPERATIVA GLOBAL -->
                <header class="header">
                    <div class="header-title-section">
                        <h1>Sistema de Control y Cobranza</h1>
                    </div>
                    
                    <div class="header-controls">
                        <!-- Botón para abrir el panel de configuración de catálogos -->
                        <button id="btn-config-catalogos" class="btn-secondary" style="background-color: var(--dark); border-color: var(--primary);">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.5.5 0 0 0 .12-.61l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.5.5 0 0 0-.5-.42h-3.84a.5.5 0 0 0-.5.42L9.1 5.3c-.59.24-1.13.57-1.62.94l-2.39-.96a.5.5 0 0 0-.6.22L3.17 8.87a.5.5 0 0 0 .12.61l2.03 1.58c-.05.3-.07.63-.07.94s.02.64.07.94l-2.03 1.58a.5.5 0 0 0-.12.61l1.92 3.32c.12.22.37.29.6.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.42.5.42h3.84c.24 0 .44-.18.47-.42l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.61l-2.03-1.58zM12 15.6a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z"/>
                            </svg>
                            Ajustar Tarifas y Catálogos
                        </button>
                    </div>
                </header>
                
                <!-- CONTENEDOR DE LA VISTA INCLUIDA -->
                <main class="content-container">
                    <?php 
                        $viewFiles = [
                            'dashboard' => 'dashboard.php',
                            'clientes' => 'clientes_view.php',
                            'ingesta' => 'ingesta_view.php',
                            'alertas' => 'alertas_view.php',
                            'pagos' => 'pagos_view.php',
                        ];
                        include __DIR__ . '/views/' . $viewFiles[$view];
                    ?>
                </main>
                
            </div>
        </div>
        
        <!-- MODAL DE CONFIGURACIÓN DE TARIFAS (GLOBAL) -->
        <div id="modal-config" class="modal-backdrop">
            <div class="modal-window">
                <div class="modal-header">
                    <h2>Gestionar Catálogos y Precios</h2>
                    <button class="modal-close">&times;</button>
                </div>
                
                <form id="form-config-precios">
                    <div class="modal-body">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                            Modifique las tarifas en dólares. Los cálculos de las nuevas listas de ingesta se realizarán con los nuevos precios de forma inmediata.
                        </p>
                        
                        <div class="form-group">
                            <label for="config-precio-zenda">Precio La Zenda (Agua de Manantial - USD)</label>
                            <input type="number" id="config-precio-zenda" class="input-text" step="0.01" style="width: 100%;" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="config-precio-alpes">Precio Los Alpes (Agua de Pozo - USD)</label>
                            <input type="number" id="config-precio-alpes" class="input-text" step="0.01" style="width: 100%;" required>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" id="btn-cerrar-modal-config" class="btn-secondary" style="background-color: var(--white); color: var(--dark); border-color: var(--border-color);">Cerrar</button>
                        <button type="submit" class="btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SCRIPTS JS -->
        <script src="assets/js/app.js"></script>
        
        <?php if ($view === 'clientes'): ?>
            <script src="assets/js/clientes.js"></script>
        <?php elseif ($view === 'ingesta' || $view === 'alertas'): ?>
            <script src="assets/js/conciliacion.js"></script>
        <?php elseif ($view === 'pagos'): ?>
            <script src="assets/js/pagos.js"></script>
        <?php endif; ?>

    <?php endif; ?>

</body>
</html>
