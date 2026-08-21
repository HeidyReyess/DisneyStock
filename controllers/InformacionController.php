<?php
// ============================================================
//  DisneyStock — Controlador de Información del Sistema
//  Archivo: controllers/InformacionController.php
//  Admin y empleado pueden ver esta pantalla.
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';

// Cualquier usuario autenticado puede acceder
requireAuth();

// Configuración guardada con defaults
$cfg = $_SESSION['config'] ?? [];
$defaults = [
    'nombre_negocio'      => 'Variedades Disney',
    'descripcion_negocio' => 'Tienda de accesorios, manillas y ropa',
    'telefono_negocio'    => '',
    'direccion_negocio'   => 'Huila, Colombia',
];
$cfg = array_merge($defaults, $cfg);

$titulo = "Información del Sistema";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/informacion.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
