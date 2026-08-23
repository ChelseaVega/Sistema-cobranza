<?php
// -------------------------------------------------------------
// VISTA: LOGIN (views/login_view.php)
// -------------------------------------------------------------
?>
<div class="login-container">
    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-icon">
                <!-- Ícono de agua mineral / gota -->
                <svg viewBox="0 0 24 24">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                </svg>
            </div>
            <div class="login-logo-title">DISTRIBUIDORA AGUA</div>
        </div>
        
        <p class="login-header-desc">Ingrese sus credenciales de operador administrativo para acceder al panel de control.</p>
        
        <div id="login-alert" class="alert-message error" style="display: none;"></div>
        
        <form id="form-login">
            <div class="form-group">
                <label for="login-usuario">Usuario</label>
                <input type="text" id="login-usuario" class="input-text" placeholder="Ej. admin" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="login-password">Contraseña</label>
                <input type="password" id="login-password" class="input-text" placeholder="••••••••" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn-primary mt-4" style="width: 100%; justify-content: center; padding: 0.85rem;">
                Iniciar Sesión
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-login');
    const alertBox = document.getElementById('login-alert');
    
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const usuario = document.getElementById('login-usuario').value.trim();
            const password = document.getElementById('login-password').value;
            
            if (usuario.length === 0 || password.length === 0) {
                alertBox.textContent = 'Por favor complete todos los campos.';
                alertBox.style.display = 'block';
                return;
            }
            
            const btnSubmit = form.querySelector('button[type="submit"]');
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Autenticando...';
            alertBox.style.display = 'none';
            
            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ usuario, password })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alertBox.className = 'alert-message success';
                    alertBox.textContent = 'Acceso concedido. Redirigiendo...';
                    alertBox.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = 'index.php?view=dashboard';
                    }, 1000);
                } else {
                    alertBox.className = 'alert-message error';
                    alertBox.textContent = result.message || 'Usuario o contraseña incorrectos.';
                    alertBox.style.display = 'block';
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = 'Iniciar Sesión';
                }
            } catch (err) {
                console.error(err);
                alertBox.className = 'alert-message error';
                alertBox.textContent = 'Error al conectar con el servidor.';
                alertBox.style.display = 'block';
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Iniciar Sesión';
            }
        });
    }
});
</script>
