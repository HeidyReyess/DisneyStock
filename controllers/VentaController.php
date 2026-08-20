<?php
// ============================================================
//  DisneyStock — Controlador de Ventas
//  Archivo: controllers/VentaController.php
//
//  GET  → Prepara datos y renderiza la vista de ventas
//  POST → Procesa crear o anular una venta
//  GET ?accion=detalle → Respuesta AJAX para el modal
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/Producto.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$id_adm = $_SESSION['usuario']['id_administrador'] ?? null;
$id_emp = $_SESSION['usuario']['id_empleado']      ?? null;

// ── AJAX: detalle de venta para el modal ─────────────────
if ($accion === 'detalle') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo '<p>ID no válido.</p>'; exit; }
    $model    = new Venta($db);
    $venta    = $model->obtenerDetalle($id);
    $detalles = $model->obtenerItems($id);
    if (!$venta) { echo '<p>Venta no encontrada.</p>'; exit; }
    require_once __DIR__ . '/../views/partials/detalle_venta.php';
    exit;
}

// ── POST: procesar acciones ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $model = new Venta($db);

    if ($accion === 'crear') {
        $descuento = (float)($_POST['descuento'] ?? 0);
        $items     = json_decode($_POST['items'] ?? '[]', true);

        if (empty($items)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Sin productos','text'=>'Agrega al menos un producto a la venta.'];
            header("Location: /DisneyStock/controllers/VentaController.php"); exit;
        }

        $resultado = $model->crear($items, $descuento, $id_adm, $id_emp);
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success','title'=>'Venta registrada','text'=>"Factura {$resultado['factura']} por \$" . number_format($resultado['total'], 0, ',', '.')]
            : ['icon'=>'error','title'=>'Error al guardar','text'=>$resultado['error']];
        header("Location: /DisneyStock/controllers/VentaController.php"); exit;
    }

    if ($accion === 'anular') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id || $_SESSION['usuario']['rol'] !== 'admin') {
            header("Location: /DisneyStock/controllers/VentaController.php"); exit;
        }
        $resultado = $model->anular($id, $id_adm);
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success','title'=>'Venta anulada','text'=>'La venta fue anulada y el stock fue restaurado.']
            : ['icon'=>'error','title'=>'Error','text'=>$resultado['error']];
        header("Location: /DisneyStock/controllers/VentaController.php"); exit;
    }

    header("Location: /DisneyStock/controllers/VentaController.php"); exit;
}

// ── GET ?accion=anular (desde botón en tabla) ─────────────
if ($accion === 'anular') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id && $_SESSION['usuario']['rol'] === 'admin') {
        $model     = new Venta($db);
        $resultado = $model->anular($id, $id_adm);
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success','title'=>'Venta anulada','text'=>'La venta fue anulada y el stock fue restaurado.']
            : ['icon'=>'error','title'=>'Error','text'=>$resultado['error']];
    }
    header("Location: /DisneyStock/controllers/VentaController.php"); exit;
}

// ── GET: mostrar vista de ventas ──────────────────────────
$modelVenta    = new Venta($db);
$modelProducto = new Producto($db);

$desde  = $_GET['desde']  ?? date('Y-m-01');
$hasta  = $_GET['hasta']  ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';

$ventas    = $modelVenta->listar($desde, $hasta, $estado);
$productos = $modelProducto->obtenerActivos();

$titulo = "Ventas";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/ventas.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
