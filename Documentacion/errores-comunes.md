# Errores Comunes y Cómo Resolverlos — DisneyStock

**Propósito:** Guía de diagnóstico para cuando el código es modificado externamente  
**Audiencia:** Desarrolladora del proyecto ante revisiones del tutor  
**Versión:** 1.0.1 — Agosto 2026

---

## 📋 Tabla de contenido

1. [Errores de base de datos](#errores-de-base-de-datos)
2. [Errores de autenticación y sesión](#errores-de-autenticación-y-sesión)
3. [Errores de rutas y redirección](#errores-de-rutas-y-redirección)
4. [Errores de seguridad CSRF](#errores-de-seguridad-csrf)
5. [Errores de inventario y ventas](#errores-de-inventario-y-ventas)
6. [Errores visuales y de vistas](#errores-visuales-y-de-vistas)
7. [Errores de configuración del servidor](#errores-de-configuración-del-servidor)
8. [Lista de verificación rápida](#lista-de-verificación-rápida)

---

## Errores de base de datos

---

### ❌ E01 — Login falla con "Usuario no encontrado" aunque el usuario existe

**Síntoma:** Al intentar iniciar sesión, siempre redirige al login con el error, incluso con credenciales correctas.

**Causa más probable:** El nombre del campo de contraseña fue cambiado.

**Dónde buscar:**

```php
// models/Usuario.php — línea del SELECT
// CORRECTO:
"u.contrasenia AS password_hash"

// INCORRECTO (lo que introdujo el tutor):
"u.contrasena AS password_hash"   // sin la 'i'
"u.password AS password_hash"     // nombre distinto
"u.pass AS password_hash"
```

**También verificar en BD:**
```sql
DESCRIBE usuario;
-- Debe aparecer la columna 'contrasenia' (con i)
-- Si aparece 'contrasena', la BD está en el esquema viejo
```

**Solución:**
```sql
-- Si la columna está mal nombrada en BD:
ALTER TABLE usuario CHANGE contrasena contrasenia VARCHAR(100);
```

---

### ❌ E02 — "Column not found: cantidad_stock" o error en inventario

**Síntoma:** Página de inventario o productos da error de columna no encontrada.

**Causa:** El tutor usó `DisneyStock.sql` (esquema viejo) para recrear la BD, o modificó `Producto.php` para buscar el stock en la tabla `producto`.

**Verificar:**
```sql
-- Debe existir la tabla inventario
SHOW TABLES LIKE 'inventario';

-- Debe tener estas columnas
DESCRIBE inventario;
-- id_inventario, cantidad_stock, stock_minimo, fecha_actualizacion, id_producto
```

**En el modelo — CORRECTO:**
```php
// models/Producto.php
"LEFT JOIN inventario i ON i.id_producto = p.id_producto"
"COALESCE(i.cantidad_stock, 0) AS stock_actual"

// INCORRECTO (esquema viejo):
"p.stock_actual"  // campo que ya NO existe en producto
```

**Solución:** Recrear la BD con `sql/disney_stock_estructura.sql`, nunca con `DisneyStock.sql`.

---

### ❌ E03 — Error al crear venta: "Column id_empleado not found"

**Síntoma:** Al registrar una venta, da error de columna en el INSERT.

**Causa:** El INSERT de venta fue modificado para usar los campos del esquema viejo.

**CORRECTO en `models/Venta.php`:**
```php
"INSERT INTO venta (fecha_venta, subtotal, impuesto, descuento, total, estado, id_usuario)
 VALUES (CURDATE(), :sub, 0, :desc, :total, 'completada', :uid)"
```

**INCORRECTO (esquema viejo):**
```php
"INSERT INTO venta (..., id_empleado, id_administrador)"  // esas columnas no existen
```

**Verificar en BD:**
```sql
DESCRIBE venta;
-- Debe tener 'id_usuario', NO 'id_empleado' ni 'id_administrador'
```

---

### ❌ E04 — Stock no se descuenta al vender

**Síntoma:** Se registra la venta pero el inventario no cambia.

**Causa:** El UPDATE de stock fue modificado para apuntar a `producto` en lugar de `inventario`.

**CORRECTO en `models/Venta.php`:**
```php
"UPDATE inventario
 SET cantidad_stock = cantidad_stock - :cant, fecha_actualizacion = CURDATE()
 WHERE id_producto = :pid"
```

**INCORRECTO:**
```php
"UPDATE producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid"
// La columna stock_actual NO existe en producto en la BD actual
```

---

### ❌ E05 — "estado = 'activo'" no filtra usuarios correctamente

**Síntoma:** Usuarios inactivos pueden iniciar sesión, o usuarios activos aparecen como inexistentes.

**Causa:** El WHERE del login fue cambiado para usar el campo del esquema viejo.

**CORRECTO:**
```sql
WHERE u.usuario = :usuario AND u.estado = 'activo'
-- estado es VARCHAR(20) con valores 'activo' o 'inactivo'
```

**INCORRECTO (esquema viejo):**
```sql
WHERE u.usuario = :usuario AND u.activo = 1
-- 'activo' como columna TINYINT no existe en la BD actual
```

---

## Errores de autenticación y sesión

---

### ❌ E06 — Cualquier URL es accesible sin estar autenticado

**Síntoma:** Se puede acceder a productos, ventas o usuarios sin iniciar sesión.

**Causa:** Se eliminó o comentó `requireAuth()` en el controller.

**Verificar en cada controller:**
```php
// CORRECTO — primeras líneas después de los require_once:
session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth();           // cualquier usuario autenticado
// o
requireAuth('admin');    // solo admin
```

**INCORRECTO (lo que puede haber hecho el tutor):**
```php
// Comentado o eliminado:
// requireAuth();

// Reemplazado por verificación incompleta:
if (!$_SESSION['usuario']) { ... }  // da error si $_SESSION no existe
```

---

### ❌ E07 — Empleados pueden acceder al Dashboard o gestionar usuarios

**Síntoma:** Un usuario con rol empleado ve el dashboard o puede crear/eliminar usuarios.

**Causa:** La verificación de rol fue eliminada o debilitada.

**CORRECTO en `DashboardController.php`:**
```php
if ($_SESSION['usuario']['rol'] === 'empleado') {
    header("Location: /DisneyStock/controllers/VentaController.php");
    exit;
}
```

**CORRECTO en `UsuarioController.php`:**
```php
requireAuth('admin', '/DisneyStock/controllers/DashboardController.php');
```

**CORRECTO en vistas (botones que no deben ver los empleados):**
```php
<?php if ($rol === 'admin'): ?>
    <button>Eliminar</button>
<?php endif; ?>
```

---

### ❌ E08 — Al recargar la página después de un POST, el formulario se reenvía

**Síntoma:** El navegador pregunta "¿Reenviar datos del formulario?" al recargar.

**Causa:** Un controller que procesa POST no termina con `header("Location: ...")`.

**CORRECTO — patrón PRG obligatorio:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    // ... procesar
    $_SESSION['alert'] = ['icon' => 'success', ...];
    header("Location: /DisneyStock/controllers/ProductoController.php");
    exit;  // ← SIEMPRE después del header
}
```

**INCORRECTO:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... procesar
    echo "Guardado correctamente";  // no redirige → doble envío posible
}
```

---

### ❌ E09 — Contraseñas guardadas en texto plano

**Síntoma:** En la BD aparecen contraseñas legibles como "admin123" en lugar de un hash.

**Causa:** Se reemplazó `password_hash()` por asignación directa.

**CORRECTO en `controllers/UsuarioController.php`:**
```php
'password_hash' => password_hash($password, PASSWORD_DEFAULT)
// Genera algo como: $2y$10$92IXUNpkjO0rOQ5...
```

**INCORRECTO:**
```php
'password_hash' => $password        // texto plano — vulnerabilidad crítica
'password_hash' => md5($password)   // md5 es inseguro para contraseñas
'password_hash' => sha1($password)  // sha1 también es inseguro
```

**Verificar también el login:**
```php
// CORRECTO:
password_verify($password, $user['password_hash'])

// INCORRECTO:
$password === $user['password_hash']  // comparación directa
```

---

## Errores de rutas y redirección

---

### ❌ E10 — Links del sidebar dan 404 o redirigen a una página en blanco

**Síntoma:** Al hacer clic en un módulo del menú lateral, página no encontrada.

**Causa:** Las rutas en `sidebar.php` fueron modificadas con nombres incorrectos.

**Rutas correctas en `views/Layouts/sidebar.php`:**
```php
/DisneyStock/controllers/DashboardController.php
/DisneyStock/controllers/VentaController.php
/DisneyStock/controllers/ProductoController.php
/DisneyStock/controllers/InventarioController.php
/DisneyStock/controllers/ReporteController.php
/DisneyStock/controllers/UsuarioController.php
/DisneyStock/controllers/ConfiguracionController.php
/DisneyStock/controllers/InformacionController.php
```

**Errores comunes introducidos:**
```php
// ❌ Nombre incorrecto del archivo
/DisneyStock/controllers/VentaViewController.php       // no existe
/DisneyStock/controllers/InventarioViewController.php  // no existe
/DisneyStock/views/dashboard/configuracion.php         // acceso directo sin auth
/DisneyStock/views/dashboard/informacion.php           // acceso directo sin auth
```

---

### ❌ E11 — Después de login, siempre redirige al Dashboard aunque el usuario sea empleado

**Síntoma:** Un empleado inicia sesión y cae en el Dashboard (que está restringido).

**Causa:** La lógica de destino post-login fue simplificada.

**CORRECTO en `controllers/AuthController.php`:**
```php
$destino = $user['rol'] === 'empleado'
    ? '/DisneyStock/controllers/VentaController.php'
    : '/DisneyStock/controllers/DashboardController.php';

header("Location: " . $destino);
exit;
```

**INCORRECTO:**
```php
header("Location: /DisneyStock/controllers/DashboardController.php"); // siempre dashboard
```

---

### ❌ E12 — El botón "Cerrar sesión" no funciona o da error

**Síntoma:** Al hacer clic en cerrar sesión, redirige a una página de error o no destruye la sesión.

**URL correcta del logout:**
```html
/DisneyStock/controllers/AuthController.php?accion=logout
```

**CORRECTO en `views/Layouts/sidebar.php`:**
```php
<a href="/DisneyStock/controllers/AuthController.php?accion=logout">
    Cerrar sesión
</a>
```

**Verificar también que `AuthController::logout()` destruye correctamente:**
```php
public function logout(): void
{
    session_unset();   // ← limpiar variables
    session_destroy(); // ← destruir sesión
    setcookie('ds_remember_user', '', ['expires' => time() - 3600, 'path' => '/']);
    header("Location: /DisneyStock/views/usuarios/login.php");
    exit;
}
```

---

## Errores de seguridad CSRF

---

### ❌ E13 — Error 403 "Token CSRF inválido" al enviar cualquier formulario

**Síntoma:** Al guardar un producto, registrar venta, etc., aparece el mensaje de error CSRF y la acción no se completa.

**Causa A — Token no está en el formulario:**
```php
// Verificar que la vista tenga dentro del <form>:
<?php csrfField(); ?>
// Genera: <input type="hidden" name="csrf_token" value="abc123...">
```

**Causa B — `validateCsrf()` se llama antes de `session_start()`:**
```php
// CORRECTO — orden obligatorio:
session_start();
require_once __DIR__ . '/../helpers/auth.php';
// ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(); // sesión ya está activa aquí
}
```

**Causa C — El campo fue renombrado en el formulario:**
```php
// CORRECTO — el name debe ser exactamente 'csrf_token':
<input type="hidden" name="csrf_token" value="...">

// INCORRECTO:
<input type="hidden" name="token" value="...">      // nombre diferente
<input type="hidden" name="_csrf" value="...">       // nombre diferente
```

---

### ❌ E14 — Formularios POST sin protección CSRF (tutor eliminó validateCsrf)

**Síntoma:** No hay error visible, pero los formularios procesan sin verificar el token.

**Riesgo:** Vulnerabilidad Cross-Site Request Forgery — permite ataques desde otras páginas.

**Verificar que TODOS los controllers que procesan POST tengan:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(); // ← obligatorio, primera línea del bloque POST
    // ...
}
```

**Controllers que deben tenerlo:**
- `AuthController.php` ✓
- `ProductoController.php` ✓
- `InventarioController.php` ✓
- `VentaController.php` ✓
- `UsuarioController.php` ✓
- `CategoriaController.php` ✓
- `ConfiguracionController.php` ✓

---

## Errores de inventario y ventas

---

### ❌ E15 — Al anular una venta, el stock no se restaura

**Síntoma:** La venta queda como "anulada" pero `inventario.cantidad_stock` no sube.

**Causa:** La lógica de restauración de stock fue eliminada o modificada en `Venta::anular()`.

**CORRECTO en `models/Venta.php` — método `anular()`:**
```php
// Debe restaurar stock por cada item
foreach ($detalles->fetchAll() as $d) {
    $this->conn->prepare(
        "UPDATE inventario
         SET cantidad_stock = cantidad_stock + :cant, fecha_actualizacion = CURDATE()
         WHERE id_producto = :pid"
    )->execute([':cant' => $d['cantidad'], ':pid' => $d['id_producto']]);

    // Y registrar el movimiento de entrada
    $this->conn->prepare(
        "INSERT INTO movimiento_inventario (tipo_movimiento, cantidad, fecha, descripcion, id_producto, id_usuario, id_venta)
         VALUES ('entrada', :cant, CURDATE(), :desc, :pid, :uid, :vid)"
    )->execute([...]);
}
```

---

### ❌ E16 — Las transacciones no hacen rollback al fallar

**Síntoma:** Al haber un error a mitad de una venta (ej. stock insuficiente en un producto), algunos cambios quedan guardados parcialmente.

**Causa:** Se eliminó el `try/catch` o el `rollBack()`.

**CORRECTO — estructura obligatoria para ventas y anulaciones:**
```php
$this->conn->beginTransaction();
try {
    // ... todas las operaciones
    $this->conn->commit();
    return ['ok' => true, ...];
} catch (Exception $e) {
    $this->conn->rollBack(); // ← deshace TODO si algo falla
    return ['ok' => false, 'error' => $e->getMessage()];
}
```

---

### ❌ E17 — Alertas de stock no se generan ni resuelven

**Síntoma:** Productos con stock bajo no aparecen en el panel de alertas, o alertas resueltas siguen apareciendo.

**Causa:** La lógica de alertas fue removida de `Inventario::registrar()` o `Venta::crear()`.

**Condición para CREAR alerta:**
```php
// Después de actualizar el stock:
if ($act['stock_minimo'] > 0 && $act['cantidad_stock'] <= $act['stock_minimo']) {
    // Crear solo si no hay una activa ya
    $ya = $this->conn->prepare(
        "SELECT COUNT(*) FROM alerta WHERE id_producto = :pid AND estado = 'activa'"
    );
    // Si count = 0, insertar alerta
}
```

**Condición para RESOLVER alerta:**
```php
// En entradas y ajustes que suben el stock:
} elseif (in_array($tipo, ['entrada', 'ajuste'])) {
    $this->conn->prepare(
        "UPDATE alerta SET estado = 'resuelta', fecha_resolucion = CURDATE()
         WHERE id_producto = :pid AND estado = 'activa'"
    )->execute([':pid' => $id_producto]);
}
```

---

## Errores visuales y de vistas

---

### ❌ E18 — Fechas muestran "00:00" o "01/01/1970"

**Síntoma:** Las columnas de fecha en tablas muestran hora `00:00` o la fecha base de Unix.

**Causa A — Formato incorrecto con `H:i`:**
```php
// INCORRECTO — fecha_venta es DATE, no tiene hora:
date('d/m/Y H:i', strtotime($v['fecha_venta']))  // muestra 00:00

// CORRECTO:
date('d/m/Y', strtotime($v['fecha_venta']))
```

**Causa B — `strtotime()` falló y retornó `false`:**
```php
// Si la fecha viene vacía o en formato incorrecto:
$fecha = $v['fecha_venta'] ?? null;
echo $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
```

**Archivos donde aplica:**
- `views/dashboard/ventas.php`
- `views/dashboard/admin.php` (tabla ventas recientes)
- `views/dashboard/reportes.php`
- `views/partials/detalle_venta.php`
- `views/dashboard/inventario.php`

---

### ❌ E19 — Modo oscuro no se activa o se activa siempre aunque no se pidió

**Síntoma:** El toggle de modo oscuro no funciona, o la página siempre carga oscura/clara ignorando la preferencia.

**Causa A — Script de inicialización fue movido debajo del `<body>`:**
```php
// CORRECTO — debe estar INMEDIATAMENTE después de <body> en header.php:
<body>
<script>
    if (localStorage.getItem('darkMode') === '1') {
        document.body.classList.add('dark');
    }
</script>
```

**Causa B — `id="darkToggle"` o `id="darkIcon"` duplicados o renombrados:**
```js
// La función actualizarIcono() busca este ID:
document.getElementById('darkIcon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
// Si el ID no existe o está duplicado, da error silencioso
```

**Causa C — `toggleDark()` fue eliminado de `sidebar.php`:**
```js
// Debe existir en sidebar.php:
function toggleDark() {
    const isDark = document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', isDark ? '1' : '0');
    actualizarIcono(isDark);
}
```

---

### ❌ E20 — SweetAlert no aparece después de acciones (crear, editar, etc.)

**Síntoma:** Se guarda un producto pero no aparece el mensaje de confirmación verde.

**Causa A — `$_SESSION['alert']` no se limpia en `header.php`:**
```php
// CORRECTO en views/Layouts/header.php:
$_dsAlert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']); // ← limpiar para que no se repita
```

**Causa B — El JS de SweetAlert usa el nombre de variable incorrecto:**
```php
// CORRECTO en header.php:
<?php if ($_dsAlert): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon:  <?= json_encode($_dsAlert['icon']) ?>,
            title: <?= json_encode($_dsAlert['title']) ?>,
            text:  <?= json_encode($_dsAlert['text']) ?>,
        });
    });
</script>
<?php endif; ?>
```

**Causa C — El controller no guarda la alerta antes de redirigir:**
```php
// CORRECTO — la alerta va ANTES del header Location:
$_SESSION['alert'] = ['icon' => 'success', 'title' => 'Listo', 'text' => 'Guardado.'];
header("Location: /DisneyStock/controllers/ProductoController.php");
exit;
```

---

### ❌ E21 — Categorías no aparecen en el select del modal de productos

**Síntoma:** El desplegable de categorías está vacío al crear o editar un producto.

**Causa A — La BD no tiene datos en `categoria`:**
```sql
SELECT COUNT(*) FROM categoria; -- si retorna 0, insertar datos iniciales
```

**Causa B — El controller no pasa `$categorias` a la vista:**
```php
// CORRECTO en ProductoController.php (sección GET):
$modelCategoria = new Categoria($db);
$categorias     = $modelCategoria->obtenerTodas();

// Luego en la vista se usa:
foreach ($categorias as $cat): ?>
    <option value="<?= $cat['id_categoria'] ?>"><?= $cat['nombre_categoria'] ?></option>
<?php endforeach;
```

**Causa C — `Categoria::obtenerTodas()` tiene error en el nombre de tabla:**
```php
// CORRECTO — tabla en minúsculas:
"SELECT id_categoria, nombre_categoria, descripcion FROM categoria ORDER BY nombre_categoria"

// INCORRECTO:
"SELECT ... FROM Categoria ..."  // mayúscula (puede fallar en Linux)
```

---

## Errores de configuración del servidor

---

### ❌ E22 — Página en blanco o error 500 al acceder a cualquier módulo

**Síntoma:** Pantalla completamente blanca o error de servidor sin mensaje.

**Causa más probable:** Error de PHP no visible porque `display_errors` está apagado.

**Solución temporal — activar errores en desarrollo:**
```php
// Agregar al inicio del archivo problemático:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

**O verificar el log de errores de Apache:**
```
c:\laragon\logs\apache\error.log
```

---

### ❌ E23 — "No se pudo conectar a la base de datos DisneyStock"

**Síntoma:** Mensaje de error de conexión en cualquier página del sistema.

**Verificar `config/database.php`:**
```php
private string $host     = "127.0.0.1";
private string $port     = "3320";    // ← puerto de Laragon (puede ser 3306)
private string $db_name  = "disney_stock";  // ← minúsculas
private string $username = "root";
private string $password = "";
```

**Errores comunes:**
- Puerto cambiado a `3306` (si Laragon usa `3320` o viceversa)
- Nombre de BD con mayúsculas: `Disney_Stock` o `DisneyStock` — debe ser `disney_stock`
- Laragon no está corriendo MySQL

**Verificar puerto actual de Laragon:**
```
Laragon → MySQL → botón derecho → "MySQL Settings" → ver puerto
```

---

### ❌ E24 — Imágenes o CSS no cargan (rutas relativas rotas)

**Síntoma:** El sidebar carga sin estilos, o los logos no aparecen.

**Causa:** Las rutas absolutas fueron cambiadas a relativas, o la raíz del proyecto cambió.

**CORRECTO — todas las rutas usan `/DisneyStock/` como raíz:**
```html
<!-- CSS -->
<link rel="stylesheet" href="/DisneyStock/Styles/dashboard.css?v=1.0.1">

<!-- Imágenes -->
<img src="/DisneyStock/img/BlancoSolo.png" alt="Logo">

<!-- Favicon -->
<link rel="shortcut icon" href="/DisneyStock/img/LogoSolo.png">
```

**INCORRECTO:**
```html
<link rel="stylesheet" href="../../Styles/dashboard.css">  <!-- relativa, se rompe -->
<link rel="stylesheet" href="Styles/dashboard.css">         <!-- sin raíz -->
```

---

## Lista de verificación rápida

Ante cualquier cambio del tutor, revisar esta lista antes de buscar el error:

### ✅ Base de datos
- [ ] La columna de contraseña se llama `contrasenia` (con **i**)
- [ ] El campo `estado` en `usuario` es `VARCHAR` con valores `'activo'`/`'inactivo'`
- [ ] La tabla `inventario` existe y tiene `cantidad_stock`, `stock_minimo`
- [ ] La tabla `producto` NO tiene `stock_actual` ni `stock_minimo`
- [ ] La tabla `venta` tiene `id_usuario`, NO `id_empleado` ni `id_administrador`
- [ ] Las categorías tienen datos (10 filas mínimo)

### ✅ Seguridad
- [ ] `requireAuth()` está en todos los controllers (excepto `AuthController`)
- [ ] `validateCsrf()` está al inicio del bloque POST en todos los controllers
- [ ] `csrfField()` está dentro de todos los `<form method="POST">`
- [ ] Las contraseñas se guardan con `password_hash()` y verifican con `password_verify()`
- [ ] `session_regenerate_id(true)` está en el login tras autenticar

### ✅ Flujo de controllers
- [ ] Todos los POST terminan en `header("Location: ...")` + `exit`
- [ ] Los empleados son redirigidos a `VentaController`, no al Dashboard
- [ ] Las acciones de escritura verifican `$rol === 'admin'` antes de ejecutar

### ✅ Modelos
- [ ] Todos los queries usan prepared statements (`:parametro`, no concatenación)
- [ ] Las ventas y anulaciones están dentro de `beginTransaction()` / `commit()` / `rollBack()`
- [ ] El stock se actualiza en `inventario`, no en `producto`

### ✅ Vistas
- [ ] Los formatos de fecha usan `d/m/Y` sin `H:i`
- [ ] Solo hay un `id="darkToggle"` y un `id="darkIcon"` en todo el HTML
- [ ] Los links del sidebar apuntan a `/DisneyStock/controllers/NombreController.php`
- [ ] Las rutas de CSS usan `/DisneyStock/Styles/` con versión fija (no `time()`)

### ✅ Servidor
- [ ] Laragon está corriendo (Apache + MySQL)
- [ ] El puerto de MySQL en `config/database.php` coincide con el de Laragon
- [ ] El nombre de la BD en `config/database.php` es `disney_stock` (minúsculas)

---

## Restaurar desde git

Si el código fue modificado y algo deja de funcionar, se puede restaurar cualquier archivo:

```bash
# Ver qué cambió
git diff

# Ver historial de commits
git log --oneline

# Restaurar un archivo específico al último commit
git restore controllers/VentaController.php

# Restaurar TODOS los archivos al último commit
git restore .

# Restaurar a un commit específico (usar el hash del git log)
git checkout abc1234 -- controllers/VentaController.php
```

> ⚠️ `git restore .` descarta **todos** los cambios no commiteados. Úsarlo con precaución.

---

**Autor:** Heidy Johanna Reyes Quesada  
**Proyecto:** DisneyStock  
**Fecha:** Agosto 2026
