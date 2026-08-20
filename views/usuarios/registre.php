<?php
session_start();

// Solo el admin puede acceder a esta página
if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    $_SESSION['alert'] = [
        'icon'  => 'error',
        'title' => 'Acceso denegado',
        'text'  => 'El registro de usuarios está reservado al administrador.',
    ];
    header("Location: login.php");
    exit;
}

$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DisneyStock | Crear Cuenta</title>
    <link rel="shortcut icon" type="image/png" href="../../img/LogoSolo.png">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS de Registro -->
    <link rel="stylesheet" href="../../public/registro.css?v=<?= time() ?>">
</head>
<body>

    <div class="register-container">

        <!-- ── Panel Imagen (Hero) — lado derecho ── -->
        <div class="register-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <img src="../../img/Logo.png" alt="DisneyStock" style="height:18px; width:auto; filter:brightness(0) saturate(100%) invert(72%) sepia(60%) saturate(500%) hue-rotate(5deg);"> DisneyStock
                </div>
                <h2>Digitaliza tu <br><span>Empresa</span> Hoy</h2>
                <p>Regístrate en DisneyStock y empieza a controlar tu inventario, registrar ventas y gestionar clientes desde el primer día.</p>

                <ul class="hero-features">
                    <li><i class="fas fa-check-circle"></i> Acceso por roles (Admin, Vendedor, Bodeguero)</li>
                    <li><i class="fas fa-check-circle"></i> Dashboard con métricas en tiempo real</li>
                    <li><i class="fas fa-check-circle"></i> Datos seguros con PDO y sesiones protegidas</li>
                </ul>
            </div>
        </div>

        <!-- ── Panel Formulario ── -->
        <div class="register-form-wrapper">
            <div class="register-card">

                <!-- Encabezado -->
                <div class="register-header">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <img src="../../img/LogoSolo.png" alt="GP" style="height:56px;width:auto;object-fit:contain;">
                        <span style="font-family:'Outfit',sans-serif;font-size:1.8rem;font-weight:800;color:#4A1D96;letter-spacing:-0.5px;">DisneyStock</span>
                    </div>
                    <h1>Crear cuenta</h1>
                    <p>Completa tus datos para solicitar acceso al sistema.</p>
                </div>

                <!-- Formulario de registro -->
                <form action="/DisneyStock/controllers/UsuarioController.php" method="POST" id="formRegistro">
                    <?php
                    require_once __DIR__ . '/../../helpers/auth.php';
                    csrfField();
                    ?>

                    <!-- Nombre completo -->
                    <div class="form-group">
                        <label for="nombre">Nombre completo</label>
                        <div class="input-wrap">
                            <i class="fas fa-user"></i>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Ej: Juan Pérez"
                                required
                                maxlength="150"
                                autocomplete="name"
                            >
                        </div>
                    </div>

                    <!-- Nombre de usuario -->
                    <div class="form-group">
                        <label for="usuario">Nombre de usuario</label>
                        <div class="input-wrap">
                            <i class="fas fa-at"></i>
                            <input
                                type="text"
                                id="usuario"
                                name="usuario"
                                placeholder="Ej: juanperez"
                                required
                                minlength="4"
                                maxlength="80"
                                autocomplete="username"
                            >
                        </div>
                    </div>

                    <!-- Contraseña -->
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Mínimo 8 caracteres"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <!-- Barra de fortaleza -->
                        <div class="password-strength" id="strengthBar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <span class="strength-label" id="strengthLabel"></span>
                    </div>

                    <!-- Confirmar contraseña -->
                    <div class="form-group">
                        <label for="password_confirm">Confirmar contraseña</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input
                                type="password"
                                id="password_confirm"
                                name="password_confirm"
                                placeholder="Repite tu contraseña"
                                required
                                autocomplete="new-password"
                            >
                        </div>
                        <span class="match-msg" id="matchMsg"></span>
                    </div>

                    <!-- Botón registrarse -->
                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i> Crear cuenta
                    </button>

                    <!-- Enlace al login -->
                    <div class="login-link">
                        ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <!-- SweetAlert de sesión -->
    <?php if ($alert): ?>
    <script>
        Swal.fire({
            icon:  <?= json_encode($alert['icon']) ?>,
            title: <?= json_encode($alert['title']) ?>,
            text:  <?= json_encode($alert['text']) ?>,
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#4A1D96'
        }).then(() => {
            <?php if (!empty($alert['redirect'])): ?>
                window.location.href = '<?= htmlspecialchars($alert['redirect']) ?>';
            <?php endif; ?>
        });
    </script>
    <?php endif; ?>

    <script>
        // Mostrar/ocultar contraseña
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Barra de fortaleza de contraseña
        const pwInput    = document.getElementById('password');
        const fillBar    = document.getElementById('strengthFill');
        const labelBar   = document.getElementById('strengthLabel');
        const confirmPw  = document.getElementById('password_confirm');
        const matchMsg   = document.getElementById('matchMsg');

        pwInput.addEventListener('input', () => {
            const val = pwInput.value;
            let score = 0;
            if (val.length >= 8)              score++;
            if (/[A-Z]/.test(val))            score++;
            if (/[0-9]/.test(val))            score++;
            if (/[^A-Za-z0-9]/.test(val))     score++;

            const levels = ['', 'Débil', 'Regular', 'Buena', 'Fuerte'];
            const colors = ['', '#EF4444', '#F59E0B', '#3B82F6', '#10B981'];
            const widths = ['0%', '25%', '50%', '75%', '100%'];

            fillBar.style.width     = widths[score];
            fillBar.style.background = colors[score];
            labelBar.textContent    = levels[score];
            labelBar.style.color    = colors[score];
        });

        // Validar coincidencia de contraseñas
        confirmPw.addEventListener('input', () => {
            if (confirmPw.value === '') {
                matchMsg.textContent = '';
            } else if (confirmPw.value === pwInput.value) {
                matchMsg.textContent = '✓ Las contraseñas coinciden';
                matchMsg.style.color = '#10B981';
            } else {
                matchMsg.textContent = '✗ Las contraseñas no coinciden';
                matchMsg.style.color = '#EF4444';
            }
        });

        // Bloquear envío si no coinciden
        document.getElementById('formRegistro').addEventListener('submit', (e) => {
            if (pwInput.value !== confirmPw.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden.',
                    confirmButtonColor: '#4A1D96'
                });
            }
        });
    </script>

</body>
</html>


