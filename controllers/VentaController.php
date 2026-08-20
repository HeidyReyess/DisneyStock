<?php
// ============================================================
//  DisneyStock — Controlador de Ventas
//  Archivo: controllers/VentaController.php
//  Admin y empleado. GET muestra vista, POST procesa acciones.
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';

// Cualquier usuario autenticado puede acceder (admin y empleado)
requireAuth();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/Producto.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// IDs del usuario logueado — uno de los dos sera null segun el rol
$id_adm = $_SESSION['usuario']['id_administrador'] ?? null;
$id_emp = $_SESSION['usuario']['id_empleado']      ?? null;

// ── AJAX: retorna HTML del detalle para inyectar en el modal ──
// Llamado por fetch() desde ventas.php cuando se presiona el ojo
if ($accion === 'detalle') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo '<p>ID no valido.</p>'; exit; }

    $model    = new Venta($db);
    $venta    = $model->obtenerDetalle($id);
    $detalles = $model->obtenerItems($id);

    if (!$venta) { echo '<p>Venta no encontrada.</p>'; exit; }

    // Este include genera el HTML que fetch() recibe como texto
    require_once __DIR__ . '/../views/partials/detalle_venta.php';
    exit;
}

// ── POST: crear o anular una venta ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $model = new Venta($db);

    // Crear nueva venta con los items del carrito
    if ($accion === 'crear') {
        $descuento = (float)($_POST['descuento'] ?? 0);

        // Items viene como JSON serializado por el JS del modal
        $items = json_decode($_POST['items'] ?? '[]', true);

        // No se puede registrar una venta sin productos
        if (empty($items)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Sin productos','text'=>'Agrega al menos un producto a la venta.'];
            header("Location: /DisneyStock/controllers/VentaController.php"); exit;
        }

        // El modelo ejecuta la transaccion completa y retorna ok + datos de factura
        $resultado = $model->crear($items, $descuento, $id_adm, $id_emp);
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success', 'title'=>'Venta registrada', 'text'=>"Factura {$resultado['factura']} por \$" . number_format($resultado['total'], 0, ',', '.')]
            : ['icon'=>'error', 'title'=>'Error al guardar', 'text'=>$resultado['error']];
        header("Location: /DisneyStock/controllers/VentaController.php"); exit;
    }

    // Anular una venta — solo admin puede hacerlo
    if ($accion === 'anular') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id || $_SESSION['usuario']['rol'] !== 'admin') {
            header("Location: /DisneyStock/controllers/VentaController.php"); exit;
        }
        // El modelo restaura el stock y registra movimientos de entrada
        $resultado = $model->anular($id, $id_adm);
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success', 'title'=>'Venta anulada', 'text'=>'La venta fue anulada y el stock fue restaurado.']
            : ['icon'=>'error', 'title'=>'Error', 'text'=>$resultado['error']];
        header("Location: /DisneyStock/controllers/VentaController.php"); exit;
    }

    header("Location: /DisneyStock/controllers/VentaController.php"); exit;
}

// ── GET ?accion=anular: boton de anular desde la tabla ────
// El boton de anular en la vista usa un enlace GET, no un form
if ($accion === 'anular') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id && $_SESSION['usuario']['rol'] === 'admin') {
        $model     = new Venta($db);
        $resultado = $model->anular($id, $id_adm);
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success', 'title'=>'Venta anulada', 'text'=>'La venta fue anulada y el stock fue restaurado.']
            : ['icon'=>'error', 'title'=>'Error', 'text'=>$resultado['error']];
    }
    header("Location: /DisneyStock/controllers/VentaController.php"); exit;
}

// ── GET sin accion: mostrar vista de ventas ───────────────
$modelVenta    = new Venta($db);
$modelProducto = new Producto($db);

// Filtros de fecha: por defecto el mes actual completo
$desde  = $_GET['desde']  ?? date('Y-m-01'); // primer dia del mes
$hasta  = $_GET['hasta']  ?? date('Y-m-d');  // hoy
$estado = $_GET['estado'] ?? '';             // sin filtro de estado por defecto

// Obtener ventas del periodo para la tabla
$ventas    = $modelVenta->listar($desde, $hasta, $estado);

// Productos activos para el select del modal nueva venta
$productos = $modelProducto->obtenerActivos();

$titulo = "Ventas";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/ventas.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
