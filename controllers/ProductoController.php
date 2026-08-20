<?php
// ============================================================
//  DisneyStock — Controlador de Productos
//  Archivo: controllers/ProductoController.php
//  GET muestra vista, POST crea/edita, GET ?accion= para toggle y eliminar.
//  Las acciones de modificacion solo las puede hacer el admin.
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';

// Admin y empleado pueden ver productos
requireAuth();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Categoria.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$rol    = $_SESSION['usuario']['rol'];

// ── Funcion auxiliar: valida y guarda imagen subida ──────
// Retorna: string (nombre del archivo) | false (error) | null (no se subio)
function procesarImagen(array $file, ?string $imagenActual = null): string|false|null
{
    // Si no se adjunto ninguna imagen, no hacer nada
    if (empty($file['name'])) return null;

    // Validar que la extension sea una imagen permitida
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) {
        $_SESSION['alert'] = ['icon'=>'warning', 'title'=>'Formato invalido', 'text'=>'Solo se permiten imagenes JPG, PNG, WEBP o GIF.'];
        return false;
    }

    // Validar que no supere 3 MB
    if ($file['size'] > 3 * 1024 * 1024) {
        $_SESSION['alert'] = ['icon'=>'warning', 'title'=>'Imagen muy grande', 'text'=>'El tamano maximo es 3 MB.'];
        return false;
    }

    // Generar nombre unico para evitar colisiones en el servidor
    $carpeta     = __DIR__ . '/../public/uploads/productos/';
    $nombreFinal = uniqid('prod_', true) . '.' . $ext;

    // Mover el archivo temporal al directorio de uploads
    if (!move_uploaded_file($file['tmp_name'], $carpeta . $nombreFinal)) {
        $_SESSION['alert'] = ['icon'=>'error', 'title'=>'Error', 'text'=>'No se pudo guardar la imagen.'];
        return false;
    }

    // Si habia una imagen anterior, borrarla del servidor
    if ($imagenActual && file_exists($carpeta . $imagenActual)) {
        unlink($carpeta . $imagenActual);
    }

    return $nombreFinal;
}

// ── POST: crear o editar producto (solo admin) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Los empleados no pueden modificar productos
    if ($rol !== 'admin') {
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }
    validateCsrf();
    $model = new Producto($db);

    // Crear nuevo producto
    if ($accion === 'crear') {
        // Sanitizar y castear cada campo del formulario
        $nombre    = trim($_POST['nombre']         ?? '');
        $pventa    = (float)($_POST['precio_venta']  ?? 0);
        $pcompra   = (float)($_POST['precio_compra'] ?? 0);
        $stock     = (int)($_POST['stock_actual']    ?? 0);
        $minimo    = (int)($_POST['stock_minimo']    ?? 0);
        $fecha     = $_POST['fecha_ingreso'] ?? date('Y-m-d');
        $proveedor = trim($_POST['proveedor']      ?? '') ?: null;
        $cat       = trim($_POST['id_categoria']   ?? '') ?: null;

        // Nombre y precio de venta son los unicos obligatorios
        if (empty($nombre) || $pventa <= 0) {
            $_SESSION['alert'] = ['icon'=>'warning', 'title'=>'Datos incompletos', 'text'=>'Nombre y precio de venta son obligatorios.'];
            header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
        }

        // Procesar imagen si se adjunto una
        $imagen = null;
        if (!empty($_FILES['imagen']['name'])) {
            $imagen = procesarImagen($_FILES['imagen']);
            if ($imagen === false) { header("Location: /DisneyStock/controllers/ProductoController.php"); exit; }
        }

        // Insertar el producto y obtener el ID generado
        $id = $model->crear([
            'nombre'        => $nombre,
            'precio_venta'  => $pventa,
            'precio_compra' => $pcompra,
            'stock_actual'  => $stock,
            'stock_minimo'  => $minimo,
            'fecha_ingreso' => $fecha,
            'proveedor'     => $proveedor,
            'imagen'        => $imagen,
            'id_categoria'  => $cat,
        ]);

        // Si se registro con stock ya bajo el minimo, crear alerta de inmediato
        if ($id && $minimo > 0 && $stock <= $minimo) {
            $db->prepare("INSERT INTO Alerta (tipo_alerta, mensaje, id_producto) VALUES ('stock_bajo', :msg, :pid)")
               ->execute([':msg' => "Producto '$nombre' registrado con stock bajo (stock: $stock, minimo: $minimo)", ':pid' => $id]);
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

        // Preparar los datos que siempre se actualizan
        $datos = [
            'nombre'        => $nombre,
            'precio_venta'  => $pventa,
            'precio_compra' => $pcompra,
            'stock_minimo'  => $minimo,
            'fecha_ingreso' => $fecha,
            'proveedor'     => $proveedor,
            'id_categoria'  => $cat,
        ];

        // Solo procesar imagen si se subio una nueva en el formulario
        if (!empty($_FILES['imagen']['name'])) {
            $prod = $model->obtenerPorId($id); // necesario para borrar la imagen anterior
            $nuevaImagen = procesarImagen($_FILES['imagen'], $prod['imagen'] ?? null);
            if ($nuevaImagen === false) { header("Location: /DisneyStock/controllers/ProductoController.php"); exit; }
            $datos['imagen'] = $nuevaImagen;
        }

        $model->actualizar($id, $datos);
        $_SESSION['alert'] = ['icon'=>'success', 'title'=>'Producto actualizado', 'text'=>"'$nombre' fue actualizado."];
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }

    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

// ── GET ?accion=toggle: cambiar estado activo/inactivo ────
if ($accion === 'toggle' && $rol === 'admin') {
    $id = (int)($_GET['id'] ?? 0);
    // El modelo usa IF en SQL para alternar sin leer el estado primero
    if ($id) (new Producto($db))->toggleEstado($id);
    header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
}

// ── GET ?accion=eliminar: eliminar producto ───────────────
if ($accion === 'eliminar' && $rol === 'admin') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        // El modelo verifica dependencias y retorna string si no se puede eliminar
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

// Leer filtros opcionales del GET
$buscar          = trim($_GET['q']  ?? '');
$filtroCategoria = $_GET['cat']     ?? '';

// Obtener productos con los filtros aplicados
$productos  = $modelProducto->obtenerTodos($buscar, $filtroCategoria);

// Categorias para el select del filtro y del modal de crear/editar
$categorias = $modelCategoria->obtenerTodas();

$titulo = "Productos";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/productos.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
