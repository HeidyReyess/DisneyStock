<?php
// ============================================================
//  DisneyStock — Controlador de Productos
//  Archivo: controllers/ProductoController.php
//  BD: disney_stock
//
//  CAMBIOS respecto a BD anterior:
//  - imagen reincorporada como VARCHAR(255) en tabla producto
//    upload a /public/uploads/productos/ con validacion de tipo y tamaño
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

// ── Helper: procesar upload de imagen ────────────────────
// Recibe el key del $_FILES, retorna la ruta relativa guardada o null
// Lanza Exception con mensaje amigable si el archivo es invalido
function procesarImagen(string $key, ?string $imagenActual = null): ?string
{
    // Si no se subio archivo o vino vacio, mantener la imagen actual
    if (empty($_FILES[$key]['name'])) {
        return $imagenActual; // null en crear, ruta existente en editar
    }

    $archivo = $_FILES[$key];

    // Verificar que no hubo error en la subida
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir la imagen. Intenta de nuevo.');
    }

    // Validar tipo MIME real (no confiar solo en la extension)
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $tiposPermitidos)) {
        throw new Exception('Tipo de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');
    }

    // Limitar tamaño a 2MB
    if ($archivo['size'] > 2 * 1024 * 1024) {
        throw new Exception('La imagen no puede superar 2MB.');
    }

    // Generar nombre unico para evitar colisiones y sobreescrituras
    $ext      = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    };
    $nombre   = 'prod_' . uniqid() . '.' . $ext;
    $carpeta  = __DIR__ . '/../public/uploads/productos/';
    $destino  = $carpeta . $nombre;

    // Crear carpeta si no existe
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    // Mover el archivo temporal al destino final
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new Exception('No se pudo guardar la imagen en el servidor.');
    }

    // Borrar imagen anterior si existia (para no acumular archivos huerfanos)
    if ($imagenActual) {
        $rutaAnterior = __DIR__ . '/../public/uploads/productos/' . $imagenActual;
        if (file_exists($rutaAnterior)) {
            unlink($rutaAnterior);
        }
    }

    return $nombre; // solo el nombre, la ruta base la arma la vista
}

// ── POST: crear o editar producto (solo admin) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rol !== 'admin') {
        header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
    }
    validateCsrf();
    $model           = new Producto($db);
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

        // Procesar imagen si se subio una
        try {
            $imagen = procesarImagen('imagen');
        } catch (Exception $e) {
            $_SESSION['alert'] = ['icon'=>'error', 'title'=>'Error en imagen', 'text'=>$e->getMessage()];
            header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
        }

        $id = $model->crear([
            'nombre'        => $nombre,
            'precio_venta'  => $pventa,
            'precio_compra' => $pcompra,
            'fecha_ingreso' => $fecha,
            'proveedor'     => $proveedor,
            'imagen'        => $imagen,
            'id_categoria'  => $cat,
        ]);

        if ($id) {
            $modelInventario->crearRegistro($id, $stock, $minimo);
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

        // Obtener imagen actual para no perderla si no se sube una nueva
        $productoActual = $model->obtenerPorId($id);
        $imagenActual   = $productoActual['imagen'] ?? null;

        try {
            $imagen = procesarImagen('imagen', $imagenActual);
        } catch (Exception $e) {
            $_SESSION['alert'] = ['icon'=>'error', 'title'=>'Error en imagen', 'text'=>$e->getMessage()];
            header("Location: /DisneyStock/controllers/ProductoController.php"); exit;
        }

        $datos = [
            'nombre'        => $nombre,
            'precio_venta'  => $pventa,
            'precio_compra' => $pcompra,
            'fecha_ingreso' => $fecha,
            'proveedor'     => $proveedor,
            'id_categoria'  => $cat,
        ];

        // Solo incluir imagen en el UPDATE si cambio respecto a la actual
        if ($imagen !== $imagenActual) {
            $datos['imagen'] = $imagen;
        }

        $model->actualizar($id, $datos);
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
        // Borrar imagen fisica del servidor antes de eliminar el registro
        $prod = (new Producto($db))->obtenerPorId($id);
        if ($prod && $prod['imagen']) {
            $ruta = __DIR__ . '/../public/uploads/productos/' . $prod['imagen'];
            if (file_exists($ruta)) unlink($ruta);
        }
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
