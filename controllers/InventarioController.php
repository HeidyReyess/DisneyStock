<?php
// ============================================================
//  DisneyStock — Controlador de Inventario
//  Archivo: controllers/InventarioController.php
//  Admin y empleado ven el inventario.
//  Solo admin puede registrar movimientos de stock.
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';

// Admin y empleado pueden ver inventario
requireAuth();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Inventario.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$rol    = $_SESSION['usuario']['rol'];

// ID del admin logueado para registrar quien hizo el movimiento
$id_adm = $_SESSION['usuario']['id_administrador'] ?? null;

// ── POST: registrar movimiento manual de stock ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Los empleados no pueden registrar movimientos directos
    if ($rol !== 'admin') {
        header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
    }
    validateCsrf();

    if ($accion === 'movimiento') {
        // Leer y sanitizar los campos del modal
        $id_producto = (int)($_POST['id_producto']    ?? 0);
        $tipo        = trim($_POST['tipo_movimiento'] ?? '');
        $cantidad    = (int)($_POST['cantidad']       ?? 0);
        $descripcion = trim($_POST['descripcion']     ?? '') ?: null; // null si vacio

        // Los tres campos principales son obligatorios
        if (!$id_producto || empty($tipo) || $cantidad <= 0) {
            $_SESSION['alert'] = ['icon'=>'warning', 'title'=>'Datos incompletos', 'text'=>'Selecciona producto, tipo y cantidad mayor a 0.'];
            header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
        }

        // Validar que el tipo sea uno de los tres permitidos
        if (!in_array($tipo, ['entrada', 'salida', 'ajuste'])) {
            $_SESSION['alert'] = ['icon'=>'error', 'title'=>'Tipo invalido', 'text'=>'El tipo debe ser entrada, salida o ajuste.'];
            header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
        }

        // El modelo registra el movimiento, actualiza stock y gestiona alertas
        $model     = new Inventario($db);
        $resultado = $model->registrar($id_producto, $tipo, $cantidad, $descripcion, $id_adm);

        // Mostrar resultado al usuario
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success', 'title'=>'Movimiento registrado', 'text'=>ucfirst($tipo) . " de $cantidad unidades registrada."]
            : ['icon'=>'error', 'title'=>'Error', 'text'=>$resultado['error']];
    }

    header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
}

// ── GET: mostrar vista de inventario ─────────────────────
$modelProducto   = new Producto($db);
$modelInventario = new Inventario($db);

// Filtro de vista: 'todos' (default) o 'bajo' (solo con stock bajo minimo)
$filtro = $_GET['filtro'] ?? 'todos';

// Tabla principal: productos activos con su stock
$inventario = $modelProducto->obtenerInventario($filtro);

// Numero total de productos bajo minimo para el banner de alerta naranja
$totalBajo = $modelProducto->contarStockBajo();

// Panel lateral: ultimos 10 movimientos con fecha, tipo y usuario
$movimientos = $modelInventario->ultimosMovimientos(10);

// Productos activos para el select del modal de registrar movimiento
$productos = $modelProducto->obtenerActivos();

$titulo = "Inventario";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/inventario.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
