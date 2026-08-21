<?php
// ============================================================
//  DisneyStock — Controlador de Productos
//  Archivo: controllers/ProductoController.php
//  BD: disney_stock
//
//  CAMBIOS respecto a BD anterior:
//  - imagen eliminada (ya no existe en la tabla producto)
//  - stock_actual y stock_minimo se gestionan en tabla inventario
//    al crear: se inserta tambien en inventario via Inventario::crearRegistro()
//    al editar: se actualiza stock_minimo via Inventario::actualizarMinimo()
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Inventario.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$rol    = $_SESSION['usuario']['rol'];

// ── POST: crear o editar producto (solo admin) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rol !== 'admin') {
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }
    validateCsrf();
    $model          = new Producto($db);
    $modelInventario = new Inventario($db);

    // Crear nuevo producto
    if ($accion === 'crear') {
        $nombre    = trim($_POST['nombre']         ?? '');
        $pventa    = (float)($_POST['precio_venta']  ?? 0);
        $pcompra   = (float)($_POST['precio_compra'] ?? 0);
        $stock     = (int)($_POST['stock_actual']    ?? 0);
        $minimo    = (int)($_POST['stock_minimo']    ?? 0);
        $fecha     = $_POST['fecha_ingreso'] ?? date('Y-m-d');
        $proveedor = trim($_POST['proveedor']      ?? '') ?: null;
        $cat       = trim($_POST['id_categoria']   ?? '') ?: null;

        if (empty($nombre) || $pventa <= 0) {
            $_SESSION['alert'] = ['icon'=>'warning', 'title'=>'Datos incompletos', 'text'=>'Nombre y precio de venta son obligatorios.'];
            header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
        }

        // Insertar producto (sin stock — va en inventario)
        $id = $model->crear([
            'nombre'        => $nombre,
            'precio_venta'  => $pventa,
            'precio_compra' => $pcompra,
            'fecha_ingreso' => $fecha,
            'proveedor'     => $proveedor,
            'id_categoria'  => $cat,
        ]);

        if ($id) {
            // Crear el registro de inventario para el producto nuevo
            $modelInventario->crearRegistro($id, $stock, $minimo);

            // Si el stock inicial ya esta bajo el minimo, crear alerta
            if ($minimo > 0 && $stock <= $minimo) {
                $db->prepare(
                    "INSERT INTO alerta (tipo_alerta, mensaje, fecha_alerta, estado, id_producto)
                     VALUES ('stock_bajo', :msg, CURDATE(), 'activa', :pid)"
                )->execute([
                    ':msg' => "Producto '$nombre' registrado con stock bajo (stock: $stock, minimo: $minimo)",
                    ':pid' => $id,
                ]);
            }
        }

        $_SESSION['alert'] = ['icon'=>'success', 'title'=>'Producto creado', 'text'=>"'$nombre' fue agregado al catalogo."];
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }

    // Editar producto existente
    if ($accion === 'editar') {
        $id        = (int)($_POST['id']            ?? 0);
        $nombre    = trim($_POST['nombre']         ?? '');
        $pventa    = (float)($_POST['precio_venta']  ?? 0);
        $pcompra   = (float)($_POST['precio_compra'] ?? 0);
        $minimo    = (int)($_POST['stock_minimo']    ?? 0);
        $fecha     = $_POST['fecha_ingreso'] ?? date('Y-m-d');
        $proveedor = trim($_POST['proveedor']     ?? '') ?: null;
        $cat       = trim($_POST['id_categoria']  ?? '') ?: null;

        if (!$id || empty($nombre)) {
            $_SESSION['alert'] = ['icon'=>'warning', 'title'=>'Datos incompletos', 'text'=>'Nombre es obligatorio.'];
            header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
        }

        // Actualizar datos del producto (sin imagen ni stock — esos van aparte)
        $model->actualizar($id, [
            'nombre'        => $nombre,
            'precio_venta'  => $pventa,
            'precio_compra' => $pcompra,
            'fecha_ingreso' => $fecha,
            'proveedor'     => $proveedor,
            'id_categoria'  => $cat,
        ]);

        // Actualizar el stock_minimo en la tabla inventario
        $modelInventario->actualizarMinimo($id, $minimo);

        $_SESSION['alert'] = ['icon'=>'success', 'title'=>'Producto actualizado', 'text'=>"'$nombre' fue actualizado."];
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }

    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

// ── GET ?accion=toggle: cambiar estado activo/inactivo ────
if ($accion === 'toggle' && $rol === 'admin') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) (new Producto($db))->toggleEstado($id);
    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

// ── GET ?accion=eliminar: eliminar producto ───────────────
if ($accion === 'eliminar' && $rol === 'admin') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $resultado = (new Producto($db))->eliminar($id);
        if ($resultado !== true) {
            $_SESSION['alert'] = ['icon'=>'error', 'title'=>'No se puede eliminar', 'text'=>$resultado];
        } else {
            $_SESSION['alert'] = ['icon'=>'success', 'title'=>'Producto eliminado', 'text'=>'El producto fue eliminado del sistema.'];
        }
    }
    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

// ── GET sin accion: mostrar vista de productos ────────────
$modelProducto  = new Producto($db);
$modelCategoria = new Categoria($db);

$buscar          = trim($_GET['q']  ?? '');
$filtroCategoria = $_GET['cat']     ?? '';

$productos  = $modelProducto->obtenerTodos($buscar, $filtroCategoria);
$categorias = $modelCategoria->obtenerTodas();

$titulo = "Productos";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/productos.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
