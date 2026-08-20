<?php
// ============================================================
//  DisneyStock — Controlador de Productos
//  Archivo: controllers/ProductoController.php
//
//  GET  → Prepara datos y renderiza la vista de productos
//  POST → Procesa crear o editar un producto
//  GET ?accion=toggle|eliminar → Acciones rápidas
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Categoria.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$rol    = $_SESSION['usuario']['rol'];

// ── Función auxiliar: procesar imagen subida ─────────────
function procesarImagen(array $file, ?string $imagenActual = null): string|false|null
{
    if (empty($file['name'])) return null;
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon'=>'warning','title'=>'Formato inválido','text'=>'Solo se permiten imágenes JPG, PNG, WEBP o GIF.'];
        return false;
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        $_SESSION['alert'] = ['icon'=>'warning','title'=>'Imagen muy grande','text'=>'El tamaño máximo es 3 MB.'];
        return false;
    }
    $carpeta     = __DIR__ . '/../public/uploads/productos/';
    $nombreFinal = uniqid('prod_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $carpeta . $nombreFinal)) {
        $_SESSION['alert'] = ['icon'=>'error','title'=>'Error','text'=>'No se pudo guardar la imagen.'];
        return false;
    }
    if ($imagenActual && file_exists($carpeta . $imagenActual)) {
        unlink($carpeta . $imagenActual);
    }
    return $nombreFinal;
}

// ── POST: crear o editar (solo admin) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rol !== 'admin') { header("Location: /DisneyStock/controllers/ProductoController.php"); exit; }
    validateCsrf();
    $model = new Producto($db);

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
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Datos incompletos','text'=>'Nombre y precio de venta son obligatorios.'];
            header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
        }
        $imagen = null;
        if (!empty($_FILES['imagen']['name'])) {
            $imagen = procesarImagen($_FILES['imagen']);
            if ($imagen === false) { header("Location: /DisneyStock/controllers/ProductoController.php"); exit; }
        }
        $id = $model->crear(['nombre'=>$nombre,'precio_venta'=>$pventa,'precio_compra'=>$pcompra,'stock_actual'=>$stock,'stock_minimo'=>$minimo,'fecha_ingreso'=>$fecha,'proveedor'=>$proveedor,'imagen'=>$imagen,'id_categoria'=>$cat]);
        if ($id && $minimo > 0 && $stock <= $minimo) {
            $db->prepare("INSERT INTO Alerta (tipo_alerta, mensaje, id_producto) VALUES ('stock_bajo', :msg, :pid)")
               ->execute([':msg'=>"Producto '$nombre' registrado con stock bajo (stock: $stock, mínimo: $minimo)",':pid'=>$id]);
        }
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Producto creado','text'=>"'$nombre' fue agregado al catálogo."];
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }

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
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Datos incompletos','text'=>'Nombre es obligatorio.'];
            header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
        }
        $datos = ['nombre'=>$nombre,'precio_venta'=>$pventa,'precio_compra'=>$pcompra,'stock_minimo'=>$minimo,'fecha_ingreso'=>$fecha,'proveedor'=>$proveedor,'id_categoria'=>$cat];
        if (!empty($_FILES['imagen']['name'])) {
            $prod = $model->obtenerPorId($id);
            $nuevaImagen = procesarImagen($_FILES['imagen'], $prod['imagen'] ?? null);
            if ($nuevaImagen === false) { header("Location: /DisneyStock/controllers/ProductoController.php"); exit; }
            $datos['imagen'] = $nuevaImagen;
        }
        $model->actualizar($id, $datos);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Producto actualizado','text'=>"'$nombre' fue actualizado."];
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }

    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

// ── GET: acciones rápidas (solo admin) ───────────────────
if ($accion === 'toggle' && $rol === 'admin') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) (new Producto($db))->toggleEstado($id);
    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

if ($accion === 'eliminar' && $rol === 'admin') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $resultado = (new Producto($db))->eliminar($id);
        if ($resultado !== true) {
            $_SESSION['alert'] = ['icon'=>'error','title'=>'No se puede eliminar','text'=>$resultado];
        } else {
            $_SESSION['alert'] = ['icon'=>'success','title'=>'Producto eliminado','text'=>'El producto fue eliminado del sistema.'];
        }
    }
    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

// ── GET: mostrar vista de productos ──────────────────────
$modelProducto  = new Producto($db);
$modelCategoria = new Categoria($db);

$buscar          = trim($_GET['q']   ?? '');
$filtroCategoria = $_GET['cat']      ?? '';

$productos  = $modelProducto->obtenerTodos($buscar, $filtroCategoria);
$categorias = $modelCategoria->obtenerTodas();

$titulo = "Productos";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/productos.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
