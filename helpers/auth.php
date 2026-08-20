<?php
// ============================================================
//  DisneyStock — Helper de autenticación
//  Archivo: helpers/auth.php
//
//  RESPONSABILIDAD:
//  Centraliza la verificación de sesión y la protección CSRF
//  para evitar repetir el mismo bloque en cada controlador.
//
//  FUNCIONES DISPONIBLES:
//
//  - requireAuth(?string $rolRequerido, string $redireccion)
//      → Verifica que haya sesión activa. Si no, redirige al login.
//        Si se pasa $rolRequerido y el rol no coincide, redirige
//        a $redireccion (por defecto al DashboardController).
//        Ejemplo: requireAuth('admin') → solo admins pasan.
//
//  - csrfToken(): string
//      → Genera y guarda un token aleatorio en sesión si no existe.
//        Retorna el token para usarlo en campos ocultos.
//
//  - validateCsrf(): void
//      → Compara el token enviado en POST['csrf_token'] con el
//        guardado en sesión usando hash_equals() (resistente a
//        timing attacks). Si no coinciden, devuelve 403 y detiene.
//        Llamar al inicio de cada controlador que procese POST.
//
//  - csrfField(): void
//      → Imprime directamente el campo oculto HTML listo para
//        pegar dentro de cualquier <form>:
//        <input type="hidden" name="csrf_token" value="...">
//
//  USO TÍPICO EN CONTROLADOR:
//      require_once __DIR__ . '/../helpers/auth.php';
//      requireAuth('admin');              // verifica sesión y rol
//      if ($_SERVER['REQUEST_METHOD'] === 'POST') validateCsrf();
//
//  USO TÍPICO EN VISTA (dentro de <form>):
//      csrfField();
// ============================================================

/**
 * Verifica que haya sesión activa. Si no, redirige al login.
 * Opcionalmente verifica que el rol coincida.
 *
 * @param string|null $rolRequerido  'admin' | 'empleado' | null (cualquier rol)
 * @param string      $redireccion   URL de destino si el rol no coincide
 */
function requireAuth(?string $rolRequerido = null, string $redireccion = '/DisneyStock/controllers/DashboardController.php'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        header("Location: /DisneyStock/views/usuarios/login.php");
        exit;
    }

    if ($rolRequerido !== null && $_SESSION['usuario']['rol'] !== $rolRequerido) {
        header("Location: $redireccion");
        exit;
    }
}

/**
 * Genera un token CSRF en sesión si no existe y lo devuelve.
 */
function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF enviado en un formulario POST.
 * Si falla, detiene la ejecución con error 403.
 */
function validateCsrf(): void
{
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    $tokenSesion  = $_SESSION['csrf_token'] ?? '';

    if (empty($tokenEnviado) || !hash_equals($tokenSesion, $tokenEnviado)) {
        http_response_code(403);
        die('Error de seguridad: token CSRF inválido. Recarga la página e inténtalo de nuevo.');
    }
}

/**
 * Imprime el campo oculto CSRF listo para usar dentro de un <form>.
 */
function csrfField(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}
