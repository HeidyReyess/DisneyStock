<?php
// ============================================================
//  DisneyStock — Controlador de Configuración
//  Archivo: controllers/ConfiguracionController.php
//  Solo admin. Gestiona las opciones del sistema.
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';

// Solo admin puede acceder a configuración
requireAuth('admin', '/DisneyStock/controllers/DashboardController.php');

// Procesar guardado de configuración
$guardado = false;
$seccion  = $_GET['seccion'] ?? 'notificaciones';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    // Persistir en sesión (en un sistema real iría a BD)
    foreach ($_POST as $k => $v) {
        if ($k !== 'seccion' && $k !== 'csrf_token') {
            $_SESSION['config'][$k] = $v;
        }
    }
    $seccion  = $_POST['seccion'] ?? $seccion;
    $guardado = true;
}

// Valores actuales con defaults
$cfg = $_SESSION['config'] ?? [];
$defaults = [
    'alerta_stock_bajo'     => '1',
    'umbral_stock'          => '5',
    'notif_nueva_venta'     => '0',
    'timeout_sesion'        => '60',
    'intentos_login'        => '3',
    'nombre_negocio'        => 'Variedades Disney',
    'descripcion_negocio'   => 'Tienda de accesorios, manillas y ropa',
    'telefono_negocio'      => '',
    'direccion_negocio'     => 'Huila, Colombia',
    'moneda'                => 'COP',
    'formato_fecha'         => 'd/m/Y',
    'version_sistema'       => '1.0.0',
    'idioma'                => 'es',
];
$cfg = array_merge($defaults, $cfg);

$titulo = "Configuración";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/configuracion.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
