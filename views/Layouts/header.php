<?php
// ── Iniciar sesión si no está activa ──────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Verificar sesión activa ───────────────────────────────
if (!isset($_SESSION['usuario'])) {
    header("Location: /DisneyStock/views/usuarios/login.php");
    exit;
}

// ── Token CSRF (generar si no existe) ────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Capturar y limpiar alert de sesión ────────────────────
$_dsAlert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

$titulo = $titulo ?? 'Dashboard';
$rol    = $_SESSION['usuario']['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DisneyStock | <?= htmlspecialchars($titulo) ?></title>
    <link rel="shortcut icon" type="image/png" href="/DisneyStock/img/LogoSolo.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { corePlugins: { preflight: false } }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS del dashboard -->
    <link rel="stylesheet" href="/DisneyStock/Styles/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/DisneyStock/Styles/sidebar.css?v=<?= time() ?>">

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; background: #F5F3FF; }
        body { display: flex; font-family: 'Inter', sans-serif; }

        /* ── Variables modo claro (default) ── */
        :root {
            --bg-main:       #F5F3FF;
            --bg-card:       #ffffff;
            --bg-table-head: #F3F0FF;
            --bg-row-hover:  #F5F3FF;
            --border-color:  #DDD6FE;
            --text-primary:  #1E1B4B;
            --text-secondary:#6B7280;
            --text-muted:    #94A3B8;
            --input-bg:      #ffffff;
            --modal-bg:      #ffffff;
            --modal-footer:  #FAF9FF;
            --scrollbar-track:#F5F3FF;
        }

        /* ── Variables modo oscuro ── */
        body.dark {
            --bg-main:        #0F1117;
            --bg-card:        #1E2235;
            --bg-table-head:  #181B2A;
            --bg-row-hover:   #242840;
            --border-color:   #2A2F45;
            --text-primary:   #F1F3F9;
            --text-secondary: #A8B0C8;
            --text-muted:     #6B7394;
            --input-bg:       #1E2235;
            --modal-bg:       #1E2235;
            --modal-footer:   #181B2A;
            --scrollbar-track:#0F1117;
        }

        body.dark { background: var(--bg-main); color: var(--text-primary); }
        body.dark > div { background: var(--bg-main) !important; }

        /* main content area */
        body.dark main { background: var(--bg-main) !important; }
        body.dark section { background: var(--bg-main) !important; }

        /* cards y tablas */
        body.dark .card,
        body.dark [style*="background:#fff"],
        body.dark [style*="background: #fff"],
        body.dark [style*="background:#ffffff"],
        body.dark [style*="background: #ffffff"] {
            background: var(--bg-card) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        /* thead */
        body.dark [style*="background:#F5F3FF"],
        body.dark [style*="background: #F5F3FF"],
        body.dark [style*="background:#F3F0FF"],
        body.dark [style*="background: #F3F0FF"] {
            background: var(--bg-table-head) !important;
        }

        /* textos — forzar legibilidad */
        body.dark td, body.dark th,
        body.dark span:not(.badge):not([style*="background"]),
        body.dark div:not([style*="background:#4A"]):not([style*="background:#7C"]):not([style*="background:#059"]):not([style*="background:#D1FA"]):not([style*="background:#FEF"]):not([style*="background:#EDE"]):not([style*="background:#FEE"]) {
            color: var(--text-primary);
        }

        body.dark [style*="color:#334155"],
        body.dark [style*="color: #334155"],
        body.dark [style*="color:#1E1B4B"],
        body.dark [style*="color: #1E1B4B"],
        body.dark [style*="color:#4A1D96"],
        body.dark [style*="color: #4A1D96"] {
            color: var(--text-primary) !important;
        }
        body.dark [style*="color:#6B7280"],
        body.dark [style*="color: #6B7280"],
        body.dark [style*="color:#94A3B8"],
        body.dark [style*="color: #94A3B8"] {
            color: var(--text-secondary) !important;
        }
        /* Precios verdes — mantener */
        body.dark [style*="color:#059669"] { color: #34D399 !important; }
        /* IDs morados — aclarar */
        body.dark [style*="color:#7C3AED"] { color: #A78BFA !important; }

        /* inputs y selects */
        body.dark input:not([type="file"]):not([type="checkbox"]):not([type="radio"]),
        body.dark select,
        body.dark textarea {
            background: var(--input-bg) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        /* modales */
        body.dark .modal-box,
        body.dark [id*="modal"] > div {
            background: var(--modal-bg) !important;
            border-color: var(--border-color) !important;
            color: var(--text-primary) !important;
        }
        body.dark [id*="modal"] label { color: var(--text-primary) !important; }

        /* headings */
        body.dark h1, body.dark h2, body.dark h3, body.dark h4 {
            color: var(--text-primary) !important;
        }
        body.dark p { color: var(--text-secondary) !important; }

        /* Bordes de tabla */
        body.dark [style*="border-bottom:1px solid #F3F0FF"],
        body.dark [style*="border-bottom: 1px solid #F3F0FF"],
        body.dark [style*="border:1px solid #DDD6FE"],
        body.dark [style*="border: 1px solid #DDD6FE"] {
            border-color: var(--border-color) !important;
        }

        /* scrollbar oscuro */
        body.dark ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
        body.dark ::-webkit-scrollbar-thumb { background: #4B5563; }
        body.dark ::-webkit-scrollbar-thumb:hover { background: #6B7280; }

        /* Cards con borde sutil en modo oscuro */
        body.dark .card,
        body.dark [style*="background:#fff"],
        body.dark [style*="background: #fff"],
        body.dark [style*="background:#ffffff"],
        body.dark [style*="background: #ffffff"] {
            box-shadow: none !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
        }

        /* botón dark mode — funciona en topbar */
        #darkToggle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: 8px;
            border: 1.5px solid #DDD6FE;
            cursor: pointer;
            font-size: 0.9rem;
            background: #F5F3FF;
            color: #4A1D96;
            transition: all 0.2s ease;
        }
        #darkToggle:hover {
            background: #EDE9FE;
            border-color: #7C3AED;
            transform: scale(1.05);
        }
        /* ── Modo oscuro: textos inline en tablas y filas ── */
        body.dark td,
        body.dark td * {
            color: var(--text-primary) !important;
        }
        body.dark tr:hover td {
            background: var(--bg-row-hover) !important;
        }
        /* Mantener colores de estado (badges) */
        body.dark [style*="background:#D1FAE5"] { background:#064e3b !important; color:#6ee7b7 !important; }
        body.dark [style*="background:#FEE2E2"] { background:#450a0a !important; color:#fca5a5 !important; }
        body.dark [style*="background:#FEF3C7"] { background:#431407 !important; color:#fcd34d !important; }
        body.dark [style*="background:#EDE9FE"] { background:#2e1065 !important; color:#c4b5fd !important; }

        /* ── Topbar modo oscuro ── */
        body.dark #topbar {
            background: var(--bg-card) !important;
            border-bottom-color: var(--border-color) !important;
        }
        body.dark #topbar .topbar-nombre {
            color: var(--text-primary) !important;
        }
        body.dark #topbar .topbar-rol {
            color: #A78BFA !important;
        }
        body.dark #topbar .topbar-avatar {
            background: #2A2F45 !important;
            color: #A78BFA !important;
        }
        body.dark #topbar .topbar-sep {
            background: var(--border-color) !important;
        }
        body.dark #topbar .topbar-logout {
            background: rgba(220,38,38,0.15) !important;
            color: #FCA5A5 !important;
        }
        body.dark #topbar .topbar-logout:hover {
            background: rgba(220,38,38,0.25) !important;
        }

        /* Modo oscuro: darkToggle */
        body.dark #darkToggle {
            background: #1E2235 !important;
            border-color: #2A2F45 !important;
            color: #A78BFA !important;
        }
        body.dark #darkToggle:hover {
            background: #242840 !important;
        }

        /* Filas de tabla en modo oscuro */
        body.dark tbody tr:hover {
            background: var(--bg-row-hover) !important;
        }
    </style>
</head>
<body>
<script>
    // Aplicar dark mode ANTES de renderizar para evitar flash
    if (localStorage.getItem('darkMode') === '1') {
        document.body.classList.add('dark');
    }
</script>
<div style="display:flex; min-height:100vh; width:100%; background:var(--bg-main)">

<?php if ($_dsAlert): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon:              <?= json_encode($_dsAlert['icon']) ?>,
            title:             <?= json_encode($_dsAlert['title']) ?>,
            text:              <?= json_encode($_dsAlert['text']) ?>,
            confirmButtonText: 'Aceptar',
            confirmButtonColor:'#4A1D96'
        });
    });
</script>
<?php endif; ?>

