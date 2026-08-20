<?php
session_start();
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DisneyStock | Iniciar Sesión</title>
    <link rel="shortcut icon" type="image/png" href="../../img/LogoSolo.png">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS de Login -->
    <link rel="stylesheet" href="../../public/login.css?v=<?= time() ?>">
</head>
<body>

    <div class="login-container">

        <!-- ── Panel Izquierdo (Imagen y Branding) ── -->
        <div class="login-hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <img src="../../img/Logo.png" alt="DisneyStock" style="height:64px; width:auto; filter:brightness(0) invert(1) drop-shadow(0 4px 12px rgba(255,255,255,0.35));"> DisneyStock
                </div>
                <h2>Gestiona tu <br><span>Negocio</span> con Inteligencia</h2>
                <p>Plataforma integral para digitalizar el inventario, ventas y clientes de tu empresa. Accede al sistema y toma decisiones basadas en datos reales.</p>

                <ul class="hero-features">
                    <li><i class="fas fa-check-circle"></i> Control de inventario en tiempo real</li>
                    <li><i class="fas fa-check-circle"></i> Registro de ventas y facturación</li>
                    <li><i class="fas fa-check-circle"></i> Reportes exportables</li>
                </ul>
            </div>
        </div>

        <!-- ── Panel Derecho (Formulario) ── -->
        <div class="login-form-wrapper">
            <div class="login-card">

                <!-- Encabezado -->
                <div class="login-header">
                    <h1>Bienvenido</h1>
                    <p>Ingresa tu usuario y contraseña para acceder al sistema.</p>
                </div>

                <!-- Formulario -->
                <form action="/DisneyStock/controllers/AuthController.php" method="POST">
                    <?php
                    require_once __DIR__ . '/../../helpers/auth.php';
                    csrfField();
                    ?>

                    <!-- Usuario -->
                    <div class="form-group">
                        <label for="usuario">Usuario</label>
                        <div class="input-wrap">
                            <i class="fas fa-user"></i>
                            <input
                                type="text"
                                id="usuario"
                                name="usuario"
                                placeholder="Tu nombre de usuario"
                                required
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
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Botón ingresar -->
                    <button type="submit" class="btn-ingresar">
                        <i class="fas fa-sign-in-alt"></i> Ingresar
                    </button>

                </form>
            </div>
        </div>

    </div>

    <!-- SweetAlert de sesión -->
    <?php if ($alert): ?>
    <script>
        Swal.fire({
            icon:              <?= json_encode($alert['icon']) ?>,
            title:             <?= json_encode($alert['title']) ?>,
            text:              <?= json_encode($alert['text']) ?>,
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#4A1D96'
        });
    </script>
    <?php endif; ?>

    <script>
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
    </script>

</body>
</html>


