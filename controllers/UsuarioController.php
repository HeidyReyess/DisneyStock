<?php
// ============================================================
//  DisneyStock — Controlador de Usuarios
//  Archivo: controllers/UsuarioController.php
//  Solo admin. GET muestra lista, POST procesa acciones.
// ============================================================

session_start();
require_once __DIR__ . '/../helpers/auth.php';

// Solo admin puede gestionar usuarios
requireAuth('admin', '/DisneyStock/controllers/DashboardController.php');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';

$db     = (new Database())->conectar();
$model  = new Usuario($db);
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── POST: crear, editar o resetear contrasena ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    // Crear un nuevo usuario con nombre, usuario, contrasena y rol
    if ($accion === 'crear') {
        $nombre   = trim($_POST['nombre']   ?? '');
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol      = trim($_POST['rol']      ?? 'empleado'); // empleado por defecto

        // Los tres campos son obligatorios al crear
        if (empty($nombre) || empty($usuario) || empty($password)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Campos incompletos','text'=>'Nombre, usuario y contrasena son obligatorios.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        // Minimo de seguridad para la contrasena
        if (strlen($password) < 8) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Contrasena corta','text'=>'Minimo 8 caracteres.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        // Verificar que el nombre de usuario no este ya registrado
        if ($model->existeUsuario($usuario)) {
            $_SESSION['alert'] = ['icon'=>'error','title'=>'Usuario duplicado','text'=>"El usuario '$usuario' ya existe."];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }

        // El modelo inserta en Usuario y en la tabla de rol (Admin o Empleado)
        $model->registrar([
            'nombre'        => $nombre,
            'usuario'       => $usuario,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT), // nunca texto plano
            'rol'           => $rol,
        ]);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Usuario creado','text'=>"'$nombre' fue creado como $rol."];
        header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
    }

    // Editar nombre, usuario y opcionalmente la contrasena
    if ($accion === 'editar') {
        $id       = (int)($_POST['id']      ?? 0);
        $nombre   = trim($_POST['nombre']   ?? '');
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? ''); // vacio = no cambiar

        if (!$id || empty($nombre) || empty($usuario)) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Campos incompletos','text'=>'Nombre y usuario son obligatorios.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }

        // Siempre actualizar nombre y usuario
        $datos = ['nombre' => $nombre, 'usuario' => $usuario];

        // Solo agregar la contrasena al update si el admin escribio una nueva
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $_SESSION['alert'] = ['icon'=>'warning','title'=>'Contrasena corta','text'=>'Minimo 8 caracteres.'];
                header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
            }
            $datos['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $model->actualizar($id, $datos);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Usuario actualizado','text'=>"'$nombre' fue actualizado."];
        header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
    }

    // Restablecer contrasena desde el panel admin
    if ($accion === 'resetpass') {
        $id       = (int)($_POST['id']      ?? 0);
        $password = trim($_POST['password'] ?? '');
        if (!$id || strlen($password) < 8) {
            $_SESSION['alert'] = ['icon'=>'warning','title'=>'Error','text'=>'Contrasena minimo 8 caracteres.'];
            header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
        }
        // Reutiliza actualizar() pasando solo el campo de contrasena
        $model->actualizar($id, ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['alert'] = ['icon'=>'success','title'=>'Contrasena restablecida','text'=>'La contrasena fue actualizada correctamente.'];
        header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
    }

    header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
}

// ── GET ?accion=toggle: activar o desactivar usuario ──────
if ($accion === 'toggle') {
    $id = (int)($_GET['id'] ?? 0);

    // Proteccion: no se puede desactivar la propia cuenta
    if ($id && $id !== (int)$_SESSION['usuario']['id']) {
        $u = $model->obtenerPorId($id);
        // Toggle: si esta activo (1) pasa a 0, si esta inactivo (0) pasa a 1
        if ($u) $model->cambiarEstado($id, $u['activo'] ? 0 : 1);
    }
    header("Location: /DisneyStock/controllers/UsuarioController.php"); exit;
}

// ── GET sin accion: mostrar lista de usuarios ─────────────
$usuarios = $model->obtenerTodos();

$titulo = "Usuarios";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/usuarios.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
