<?php
// ============================================================
//  DisneyStock — Controlador de Usuarios
//  Archivo: controllers/UsuarioController.php
//
//  GET  → Lista usuarios (solo admin)
//  POST → Crear o editar usuario (solo admin)
//  GET ?accion=toggle|resetpass → Acciones rápidas
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth('admin', '/DisneyStock/controllers/DashboardController.php');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';

$db     = (new Database())->conectar();
$model  = new Usuario($db);
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── POST: crear o editar ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    if ($accion === 'crear') {
        $nombre   = trim($_POST['nombre']   ?? '');
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol      = trim($_POST['rol']      ?? 'empleado');

        if (empty($nombre) || empty($usuario) || empty($password)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Campos incompletos','text'=>'Nombre, usuario y contraseña son obligatorios.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        if (strlen($password) < 8) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Contraseña corta','text'=>'Mínimo 8 caracteres.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        if ($model->existeUsuario($usuario)) {
            $_SESSION['alert'] = ['icon'=>'error','title'=>'Usuario duplicado','text'=>"El usuario '$usuario' ya existe."];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        $model->registrar(['nombre'=>$nombre,'usuario'=>$usuario,'password_hash'=>password_hash($password, PASSWORD_DEFAULT),'rol'=>$rol]);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Usuario creado','text'=>"'$nombre' fue creado como $rol."];
        header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
    }

    if ($accion === 'editar') {
        $id       = (int)($_POST['id']      ?? 0);
        $nombre   = trim($_POST['nombre']   ?? '');
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$id || empty($nombre) || empty($usuario)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Campos incompletos','text'=>'Nombre y usuario son obligatorios.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        $datos = ['nombre'=>$nombre,'usuario'=>$usuario];
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $_SESSION['alert'] = ['icon'=>'warning','title'=>'Contraseña corta','text'=>'Mínimo 8 caracteres.'];
                header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
            }
            $datos['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $model->actualizar($id, $datos);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Usuario actualizado','text'=>"'$nombre' fue actualizado."];
        header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
    }

    if ($accion === 'resetpass') {
        $id       = (int)($_POST['id']      ?? 0);
        $password = trim($_POST['password'] ?? '');
        if (!$id || strlen($password) < 8) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Error','text'=>'Contraseña mínimo 8 caracteres.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        $model->actualizar($id, ['password_hash'=>password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Contraseña restablecida','text'=>'La contraseña fue actualizada correctamente.'];
        header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
    }

    header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
}

// ── GET: toggle activo/inactivo ───────────────────────────
if ($accion === 'toggle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id && $id !== (int)$_SESSION['usuario']['id']) {
        $u = $model->obtenerPorId($id);
        if ($u) $model->cambiarEstado($id, $u['activo'] ? 0 : 1);
    }
    header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
}

// ── GET: mostrar vista de usuarios ────────────────────────
$usuarios = $model->obtenerTodos();

$titulo = "Usuarios";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/usuarios.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
