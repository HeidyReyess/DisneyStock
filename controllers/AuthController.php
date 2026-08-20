<?php
// ============================================================
//  DisneyStock — Controlador de Autenticación
//  Archivo: controllers/AuthController.php
//
//  RESPONSABILIDAD: Maneja login y logout del sistema.
//  FLUJO: login.php → POST aquí → DashboardController o VentaViewController
// ============================================================

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/auth.php';

// URLs constantes para no repetir rutas en todo el archivo
define('URL_LOGIN',     '/DisneyStock/views/usuarios/login.php');
define('URL_DASHBOARD', '/DisneyStock/controllers/DashboardController.php');

class AuthController
{
    public function login(): void
    {
        // Si no es POST (alguien entró directo a este archivo), manda al login
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: " . URL_LOGIN);
            exit;
        }

        // Verifica que el token CSRF del formulario sea válido
        validateCsrf();

        // Sanitizar entradas del formulario
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = trim($_POST['password'] ?? '');

        // Ambos campos son obligatorios
        if (empty($usuario) || empty($password)) {
            $this->alerta('warning', 'Campos incompletos', 'Debes ingresar usuario y contraseña.');
        }

        // Conectar a BD y buscar el usuario por nombre de usuario
        $db    = (new Database())->conectar();
        $model = new Usuario($db);
        $user  = $model->obtenerPorUsuario($usuario);

        // Si no existe el usuario o está inactivo, rechazar
        if (!$user) {
            $this->alerta('error', 'Usuario no encontrado', 'El usuario no existe o está inactivo.');
        }

        // Comparar contraseña ingresada con el hash guardado en BD
        if (!password_verify($password, $user['password_hash'])) {
            $this->alerta('error', 'Contraseña incorrecta', 'Verifica tus credenciales e inténtalo de nuevo.');
        }

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        // Guardar datos del usuario en sesión
        $_SESSION['usuario'] = [
            'id'               => $user['id'],
            'nombre'           => trim($user['nombre']),
            'usuario'          => trim($user['usuario']),
            'rol'              => $user['rol'],
            'id_administrador' => $user['id_administrador'] ?? null, // null si es empleado
            'id_empleado'      => $user['id_empleado']      ?? null, // null si es admin
        ];

        // Los empleados van directo a ventas, los admin al dashboard
        $destino = $user['rol'] === 'empleado'
            ? '/DisneyStock/controllers/VentaController.php'
            : URL_DASHBOARD;

        header("Location: " . $destino);
        exit;
    }

    public function logout(): void
    {
        // Limpiar todas las variables de sesión y destruirla
        session_unset();
        session_destroy();
        header("Location: " . URL_LOGIN);
        exit;
    }

    // Guarda la alerta en sesión y redirige al login
    private function alerta(string $icon, string $title, string $text): never
    {
        $_SESSION['alert'] = compact('icon', 'title', 'text');
        header("Location: " . URL_LOGIN);
        exit;
    }
}

// Instanciar el controlador y ejecutar la acción según el parámetro GET
$controller = new AuthController();
$accion     = $_GET['accion'] ?? 'login';

if ($accion === 'logout') {
    $controller->logout();
} else {
    $controller->login();
}
