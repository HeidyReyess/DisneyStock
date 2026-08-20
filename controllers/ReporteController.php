<?php
// ============================================================
//  DisneyStock — Controlador de Reportes
//  Archivo: controllers/ReporteController.php
//
//  RESPONSABILIDAD:
//  Genera los datos para los reportes según el tipo solicitado
//  y los entrega a views/dashboard/reportes.php.
//
//  TIPOS DE REPORTE (GET ?tipo=):
//  - ventas      → Ventas registradas entre $desde y $hasta.
//                  Columnas: Factura, Vendedor, Subtotal, Descuento,
//                  Total, Estado, Fecha.
//  - inventario  → Todos los productos activos con valores de
//                  stock a precio de costo y precio de venta.
//                  Columnas: ID, Nombre, Categoría, Stock, Mínimo,
//                  Valor Costo, Valor Venta, Fecha Ingreso.
//  - stock_bajo  → Productos cuyo stock actual es menor o igual
//                  al mínimo definido.
//                  Columnas: ID, Nombre, Categoría, Stock Actual,
//                  Stock Mínimo, Faltante.
//  - movimientos → Movimientos de inventario (entradas/salidas/ajustes)
//                  entre $desde y $hasta.
//                  Columnas: Producto, Tipo, Cantidad, Descripción,
//                  Usuario, Fecha.
//
//  PARÁMETROS GET:
//  - ?tipo=  → Tipo de reporte (ventas por defecto).
//  - ?desde= → Fecha inicio (default: 1º del mes actual).
//  - ?hasta= → Fecha fin (default: hoy).
//
//  ACCESO:
//  - Admin y empleado. La vista incluye opción de imprimir.
//
//  VISTA QUE RENDERIZA:
//  views/dashboard/reportes.php
// ============================================================

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Inventario.php';

// ── Verificar sesión y rol ────────────────────────────────
if (!isset($_SESSION['usuario'])) {
    header("Location: /DisneyStock/views/usuarios/login.php");
    exit;
}
if (!in_array($_SESSION['usuario']['rol'], ['admin', 'empleado'], true)) {
    header("Location: /DisneyStock/controllers/VentaController.php");
    exit;
}

$db  = (new Database())->conectar();

$modelVenta     = new Venta($db);
$modelProducto  = new Producto($db);
$modelInventario= new Inventario($db);

$tipo  = $_GET['tipo']  ?? 'ventas';
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$datos    = [];
$columnas = [];

switch ($tipo) {
    case 'ventas':
        $datos    = $modelVenta->reporteVentas($desde, $hasta);
        $columnas = ['Factura','Vendedor','Subtotal','Descuento','Total','Estado','Fecha'];
        break;
    case 'inventario':
        $datos    = $modelProducto->reporteInventario();
        $columnas = ['ID','Nombre','Categoría','Stock','Mínimo','Valor Costo','Valor Venta','Fecha Ingreso'];
        break;
    case 'stock_bajo':
        $datos    = $modelProducto->reporteStockBajo();
        $columnas = ['ID','Nombre','Categoría','Stock Actual','Stock Mínimo','Faltante'];
        break;
    case 'movimientos':
        $datos    = $modelInventario->reporteMovimientos($desde, $hasta);
        $columnas = ['Producto','Tipo','Cantidad','Descripción','Usuario','Fecha'];
        break;
}

$titulo = "Reportes";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/reportes.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
