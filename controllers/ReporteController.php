<?php
// ============================================================
//  DisneyStock — Controlador de Reportes
//  Archivo: controllers/ReporteController.php
//  Acceso: admin y empleado.
// ============================================================

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Inventario.php';

// Verificar sesion activa
if (!isset($_SESSION['usuario'])) {
    header("Location: /DisneyStock/views/usuarios/login.php");
    exit;
}
// Solo admin y empleado tienen acceso a reportes
if (!in_array($_SESSION['usuario']['rol'], ['admin', 'empleado'], true)) {
    header("Location: /DisneyStock/controllers/VentaController.php");
    exit;
}

$db = (new Database())->conectar();

$modelVenta      = new Venta($db);
$modelProducto   = new Producto($db);
$modelInventario = new Inventario($db);

// Tipo de reporte: ventas por defecto si no se paso nada
$tipo  = $_GET['tipo']  ?? 'ventas';

// Rango de fechas: por defecto el mes actual completo
$desde = $_GET['desde'] ?? date('Y-m-01'); // primer dia del mes
$hasta = $_GET['hasta'] ?? date('Y-m-d');  // hoy

// Variables que recibe la vista para armar la tabla dinamicamente
$datos    = [];
$columnas = [];

// Segun el tipo seleccionado, llamar al modelo correspondiente
switch ($tipo) {

    // Ventas del periodo con datos de factura, vendedor y totales
    case 'ventas':
        $datos    = $modelVenta->reporteVentas($desde, $hasta);
        $columnas = ['Factura', 'Vendedor', 'Subtotal', 'Descuento', 'Total', 'Estado', 'Fecha'];
        break;

    // Todos los productos activos con su valor de stock a costo y venta
    case 'inventario':
        $datos    = $modelProducto->reporteInventario();
        $columnas = ['ID', 'Nombre', 'Categoria', 'Stock', 'Minimo', 'Valor Costo', 'Valor Venta', 'Fecha Ingreso'];
        break;

    // Solo productos cuyo stock esta en o por debajo del minimo
    case 'stock_bajo':
        $datos    = $modelProducto->reporteStockBajo();
        $columnas = ['ID', 'Nombre', 'Categoria', 'Stock Actual', 'Stock Minimo', 'Faltante'];
        break;

    // Movimientos de inventario del periodo (entradas, salidas, ajustes)
    case 'movimientos':
        $datos    = $modelInventario->reporteMovimientos($desde, $hasta);
        $columnas = ['Producto', 'Tipo', 'Cantidad', 'Descripcion', 'Usuario', 'Fecha'];
        break;
}

$titulo = "Reportes";

// Renderizar la pagina completa con la tabla de resultados
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/reportes.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
