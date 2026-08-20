<?php
// ============================================================
//  DisneyStock — Controlador del Dashboard Principal
//  Archivo: controllers/DashboardController.php
//
//  RESPONSABILIDAD: Recopila métricas del negocio y renderiza
//  el panel de control. Solo accesible por admin.
// ============================================================

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Inventario.php';

// Si no hay sesión activa, redirigir al login
if (!isset($_SESSION['usuario'])) {
    header("Location: /DisneyStock/views/usuarios/login.php");
    exit;
}

// Los empleados no tienen dashboard, van directo al módulo de ventas
if ($_SESSION['usuario']['rol'] === 'empleado') {
    header("Location: /DisneyStock/controllers/VentaController.php");
    exit;
}

// Conectar a la BD y preparar fechas para los filtros
$db  = (new Database())->conectar();
$hoy = date('Y-m-d'); // formato YYYY-MM-DD para queries SQL
$mes = date('Y-m');   // formato YYYY-MM para filtro mensual

// Instanciar modelos necesarios
$modelVenta     = new Venta($db);
$modelProducto  = new Producto($db);
$modelInventario= new Inventario($db);

// ── Tarjeta 1: Ventas del día (cantidad y monto total) ────
$ventasHoy      = $modelVenta->metricasHoy($hoy);

// ── Tarjeta 2: Ingresos acumulados del mes en curso ───────
$ingresosMes    = $modelVenta->ingresosMes($mes);

// ── Tabla ventas recientes: últimas 6 ventas ──────────────
$ventasRecientes= $modelVenta->ultimas(6);

// ── Tarjeta 3: Total de productos activos en catálogo ─────
$totalProductos = $modelProducto->contarActivos();

// ── Tarjeta 4: Productos con stock por debajo del mínimo ──
$stockBajo      = $modelProducto->contarStockBajo();

// ── Panel top: 5 productos más vendidos ───────────────────
$topProductos   = $modelProducto->topVendidos(5);

// ── Panel alertas: hasta 5 alertas activas de stock bajo ──
$alertasStock   = $modelInventario->alertasActivas(5);

// Título que mostrará el header.php en la pestaña del navegador
$titulo = "Dashboard";

// Renderizar la página completa: header → sidebar → vista → footer
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/admin.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
