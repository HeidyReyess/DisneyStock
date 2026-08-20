<?php
$rol    = $_SESSION['usuario']['rol']    ?? 'usuario';
$nombre = $_SESSION['usuario']['nombre'] ?? 'Usuario';
$page   = basename($_SERVER['PHP_SELF']);

// Helper: clase activa
if (!function_exists('navLink')) {
    function navLink(string $file, string $current): string {
        return $file === $current ? 'sidebar-link active' : 'sidebar-link';
    }
}
?>

<aside style="width:260px; background:linear-gradient(180deg, #3B0764 0%, #4A1D96 100%); flex-shrink:0; display:flex; flex-direction:column; min-height:100vh; position:sticky; top:0;">

    <!-- Logo -->
    <div style="height:80px; display:flex; align-items:center; padding:0 16px; border-bottom:1px solid rgba(255,255,255,0.1); gap:4px;">
        <img src="/DisneyStock/img/BlancoSolo.png" alt="GP" style="height:58px; width:58px; object-fit:contain; flex-shrink:0;">
        <div>
            <div style="font-family:'Outfit',sans-serif; font-size:1.15rem; font-weight:800; color:#fff; line-height:1.1; letter-spacing:-0.3px;">DisneyStock</div>
            <div style="font-size:0.65rem; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:1.5px; margin-top:2px;"><?= ucfirst($rol) ?></div>
        </div>
    </div>

    <!-- Navegación -->
    <nav style="flex:1; padding:20px 12px; overflow-y:auto;">

        <!-- Solo Admin ve Dashboard -->
        <?php if ($rol === 'admin'): ?>
        <a href="/DisneyStock/controllers/DashboardController.php" class="<?= navLink('DashboardController.php', $page) ?>">
            <i class="fas fa-border-all"></i><span>Dashboard</span>
        </a>
        <?php endif; ?>

        <!-- Admin y Empleado -->
        <?php if (in_array($rol, ['admin', 'empleado'], true)): ?>
        <div class="nav-section">Ventas</div>
        <a href="/DisneyStock/controllers/VentaController.php" class="<?= navLink('VentaController.php', $page) ?>">
            <i class="fas fa-cash-register"></i><span>Ventas</span>
        </a>
        <?php endif; ?>

        <!-- Admin y Empleado -->
        <?php if (in_array($rol, ['admin', 'empleado'], true)): ?>
        <div class="nav-section">Inventario</div>
        <a href="/DisneyStock/controllers/ProductoController.php" class="<?= navLink('ProductoController.php', $page) ?>">
            <i class="fas fa-box"></i><span>Productos</span>
        </a>
        <a href="/DisneyStock/controllers/InventarioController.php" class="<?= navLink('InventarioController.php', $page) ?>">
            <i class="fas fa-boxes-stacked"></i><span>Inventario</span>
        </a>
        <?php endif; ?>

        <!-- Admin y Empleado — Reportes -->
        <?php if (in_array($rol, ['admin', 'empleado'], true)): ?>
        <div class="nav-section"><?= $rol === 'admin' ? 'Administracion' : 'Reportes' ?></div>
        <a href="/DisneyStock/controllers/ReporteController.php" class="<?= navLink('ReporteController.php', $page) ?>">
            <i class="fas fa-chart-bar"></i><span>Reportes</span>
        </a>
        <?php endif; ?>

        <!-- Solo Admin -->
        <?php if ($rol === 'admin'): ?>
        <a href="/DisneyStock/controllers/UsuarioController.php" class="<?= navLink('UsuarioController.php', $page) ?>">
            <i class="fas fa-user-shield"></i><span>Usuarios</span>
        </a>
        <a href="/DisneyStock/views/dashboard/configuracion.php" class="<?= navLink('configuracion.php', $page) ?>">
            <i class="fas fa-gear"></i><span>Configuración</span>
        </a>
        <?php endif; ?>

        <!-- Todos los roles -->
        <div class="nav-section">General</div>
        <a href="/DisneyStock/views/dashboard/informacion.php" class="<?= navLink('informacion.php', $page) ?>">
            <i class="fas fa-circle-info"></i><span>Información</span>
        </a>

    </nav>

    <!-- Usuario y logout -->
    <div style="padding:12px; border-top:1px solid rgba(255,255,255,0.1);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;">
            <div style="font-size:0.82rem; color:rgba(255,255,255,0.7); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <i class="fas fa-circle-user" style="margin-right:8px; color:#A78BFA;"></i>
                <?= htmlspecialchars($nombre) ?>
            </div>
            <button id="darkToggle" onclick="toggleDark()">
                <i id="darkIcon" class="fas fa-moon"></i>
            </button>
        </div>
        <a href="/DisneyStock/controllers/AuthController.php?accion=logout" class="sidebar-link" style="color:rgba(255,130,130,0.85);">
            <i class="fas fa-right-from-bracket"></i><span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<script>
function toggleDark() {
    const isDark = document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', isDark ? '1' : '0');
    actualizarIcono(isDark);
}
function actualizarIcono(isDark) {
    document.getElementById('darkIcon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}
// Inicializar ícono según estado guardado
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.body.classList.contains('dark');
    actualizarIcono(isDark);
});
</script>

<!-- Contenido principal -->
<main style="flex:1; display:flex; flex-direction:column; min-height:100vh; background:#F5F3FF; min-width:0; overflow-y:auto;">
<section style="padding:32px; flex:1;">

