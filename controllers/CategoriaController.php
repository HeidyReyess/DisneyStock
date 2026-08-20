<?php
// ============================================================
//  DisneyStock — Controlador de Acciones de Categoría
//  Archivo: controllers/CategoriaController.php
//
//  RESPONSABILIDAD:
//  Procesa la creación y eliminación de categorías de productos.
//  Se maneja desde el modal de Categorías dentro de la vista
//  de Productos (views/dashboard/productos.php).
//
//  ACCIONES DISPONIBLES ($_POST['accion'] o $_GET['accion']):
//  - crear    → Verifica que el nombre no esté vacío ni duplicado,
//               luego inserta la nueva categoría en BD.
//  - eliminar → Solo elimina si la categoría no tiene productos
//               asociados. Si los tiene, rechaza con alerta.
//               Recibe ?id= por GET.
//
//  SEGURIDAD:
//  - Solo admin. Valida CSRF en POST.
//
//  VISTA QUE REDIRIGE:
//  controllers/ProductoViewController.php
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth('admin', '/DisneyStock/controllers/DashboardController.php');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Categoria.php';

// Validar CSRF en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
}

$db     = (new Database())->conectar();
$model  = new Categoria($db);
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$back   = '/DisneyStock/controllers/ProductoViewController.php';

switch ($accion) {

    case 'crear':
        $nombre = trim($_POST['nombre_categoria'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '') ?: null;

        if (empty($nombre)) {
            header("Location: $back"); exit;
        }
        if ($model->existePorNombre($nombre)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Ya existe','text'=>"La categoría '$nombre' ya está registrada."];
            header("Location: $back"); exit;
        }

        $model->crear($nombre, $desc);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Categoría creada','text'=>"'$nombre' fue agregada."];
        header("Location: $back"); exit;

    case 'eliminar':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { header("Location: $back"); exit; }

        if ($model->tieneProductos($id)) {
            $_SESSION['alert'] = ['icon'=>'error','title'=>'No se puede eliminar','text'=>'Esta categoría tiene productos asociados.'];
            header("Location: $back"); exit;
        }

        $model->eliminar($id);
        header("Location: $back"); exit;

    default:
        header("Location: $back"); exit;
}
