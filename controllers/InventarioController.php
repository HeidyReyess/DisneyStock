<?php
// ============================================================
//  DisneyStock — Controlador de Inventario
//  Archivo: controllers/InventarioController.php
//
//  GET  → Prepara datos y renderiza la vista de inventario
//  POST → Registra un movimiento de stock (solo admin)
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Inventario.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$rol    = $_SESSION['usuario']['rol'];
$id_adm = $_SESSION['usuario']['id_administrador'] ?? null;

// ── POST: registrar movimiento (solo admin) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rol !== 'admin') {
        header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
    }
    validateCsrf();

    if ($accion === 'movimiento') {
        $id_producto = (int)($_POST['id_producto']    ?? 0);
        $tipo        = trim($_POST['tipo_movimiento'] ?? '');
        $cantidad    = (int)($_POST['cantidad']       ?? 0);
        $descripcion = trim($_POST['descripcion']     ?? '') ?: null;

        if (!$id_producto || empty($tipo) || $cantidad <= 0) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Datos incompletos','text'=>'Selecciona producto, tipo y cantidad mayor a 0.'];
            header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
        }
        if (!in_array($tipo, ['entrada','salida','ajuste'])) {
            $_SESSION['alert'] = ['icon'=>'error','title'=>'Tipo inválido','text'=>'El tipo debe ser entrada, salida o ajuste.'];
            header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
        }

        $model     = new Inventario($db);
        $resultado = $model->registrar($id_producto, $tipo, $cantidad, $descripcion, $id_adm);

        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success','title'=>'Movimiento registrado','text'=>ucfirst($tipo) . " de $cantidad unidades registrada."]
            : ['icon'=>'error','title'=>'Error','text'=>$resultado['error']];
    }

    header("Location: /DisneyStock/controllers/InventarioController.php"); exit;
}

// ── GET: mostrar vista de inventario ─────────────────────
$modelProducto   = new Producto($db);
$modelInventario = new Inventario($db);

$filtro      = $_GET['filtro'] ?? 'todos';
$inventario  = $modelProducto->obtenerInventario($filtro);
$totalBajo   = $modelProducto->contarStockBajo();
$movimientos = $modelInventario->ultimosMovimientos(10);
$productos   = $modelProducto->obtenerActivos();

$titulo = "Inventario";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/inventario.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
