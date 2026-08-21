# Errores Comunes y Cómo Resolverlos — DisneyStock

**Propósito:** Diagnóstico cuando el código es modificado externamente (revisiones del tutor)  
**Versión:** 1.0.1 — Agosto 2026  
**Autor:** Heidy Johanna Reyes Quesada

---

## Tabla de contenido

1. [Errores de base de datos](#errores-de-base-de-datos)
2. [Errores de autenticación y sesión](#errores-de-autenticación-y-sesión)
3. [Errores de rutas y redirección](#errores-de-rutas-y-redirección)
4. [Errores de seguridad CSRF](#errores-de-seguridad-csrf)
5. [Errores de inventario y ventas](#errores-de-inventario-y-ventas)
6. [Errores visuales](#errores-visuales)
7. [Errores de servidor](#errores-de-servidor)
8. [Lista de verificación rápida](#lista-de-verificación-rápida)
9. [Restaurar con Git](#restaurar-con-git)

---

## Errores de base de datos

---

### E01 — Login falla aunque el usuario existe

**Síntoma:** Siempre redirige al login con "Usuario no encontrado", incluso con credenciales correctas.

**Causa más probable:** Se cambió el nombre del campo de contraseña.

```php
// models/Usuario.php — CORRECTO:
"u.contrasenia AS password_hash"   // con 'i'

// INCORRECTO (lo que introduce el tutor):
"u.contrasena AS password_hash"    // sin 'i'
"u.password AS password_hash"      // nombre distinto
```

**Verificar en BD:**
```sql
DESCRIBE usuario;
-- Debe aparecer 'contrasenia' (con i)
```

**Solución si la columna está mal:**
```sql
ALTER TABLE usuario CHANGE contrasena contrasenia VARCHAR(100);
```

---

### E02 — Error "Column not found: cantidad_stock"

**Síntoma:** Página de inventario o productos da error de columna.

**Causa:** Se usó `DisneyStock.sql` (esquema viejo) para recrear la BD, o se modificó `Producto.php` para buscar stock en `producto`.

**CORRECTO en `models/Producto.php`:**
```php
"LEFT JOIN inventario i ON i.id_producto = p.id_producto"
"COALESCE(i.cantidad_stock, 0) AS stock_actual"
```

**INCORRECTO:**
```php
"p.stock_actual"   // columna que no existe en producto en la BD actual
```

**Solución:** Recrear BD únicamente con `sql/disney_stock_estructura.sql`.

---

### E03 — Error al crear venta: "Column id_empleado not found"

**Síntoma:** Al registrar una venta, da error en el INSERT.

**CORRECTO en `models/Venta.php`:**
```php
"INSERT INTO venta (fecha_venta, subtotal, impuesto, descuento, total, estado, id_usuario)
 VALUES (CURDATE(), :sub, 0, :desc, :total, 'completada', :uid)"
```

**INCORRECTO:**
```php
"INSERT INTO venta (..., id_empleado, id_administrador)"  // columnas del esquema viejo
```

---

### E04 — Stock no se descuenta al vender

**Síntoma:** La venta se registra pero `inventario.cantidad_stock` no cambia.

**CORRECTO en `models/Venta.php`:**
```php
"UPDATE inventario
 SET cantidad_stock = cantidad_stock - :cant, fecha_actualizacion = CURDATE()
 WHERE id_producto = :pid"
```

**INCORRECTO:**
```php
"UPDATE producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid"
// stock_actual no existe en producto
```

---

### E05 — Usuarios inactivos pueden iniciar sesión

**Síntoma:** Usuarios marcados como inactivos pasan el login.

**CORRECTO en `models/Usuario.php`:**
```sql
WHERE u.usuario = :usuario AND u.estado = 'activo'
-- estado es VARCHAR con valores 'activo' / 'inactivo'
```

**INCORRECTO (esquema viejo):**
```sql
WHERE u.usuario = :usuario AND u.activo = 1
-- 'activo' como columna TINYINT no existe en la BD actual
```

---

## Errores de autenticación y sesión

---

### E06 — Cualquier URL es accesible sin estar autenticado

**Síntoma:** Se puede entrar a productos, ventas o usuarios sin iniciar sesión.

**Causa:** Se eliminó o comentó `requireAuth()` en el controller.

**CORRECTO — primeras líneas de cada controller:**
```php
session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth();        // cualquier usuario autenticado
// o
requireAuth('admin'); // solo admin
```

**INCORRECTO:**
```php
// requireAuth();   ← comentado o eliminado
if (!$_SESSION['usuario']) { ... }  // verificación incompleta, da error si sesión no existe
```

---

### E07 — Empleados acceden al Dashboard o gestionan usuarios

**Síntoma:** Un empleado ve el dashboard principal o puede crear/eliminar usuarios.

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

---

### E08 — Recargar la página reenvía el formulario

**Síntoma:** El navegador pregunta "¿Reenviar datos del formulario?" al presionar F5.

**Causa:** Un controller POST no termina con `header("Location: ...")`.

**CORRECTO — patrón PRG obligatorio:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    // ... procesar
    $_SESSION['alert'] = ['icon' => 'success', 'title' => '...', 'text' => '...'];
    header("Location: /DisneyStock/controllers/ProductoController.php");
    exit;  // siempre después del header
}
```

---

### E09 — Contraseñas guardadas en texto plano

**Síntoma:** En la BD aparece "admin123" en lugar de un hash `$2y$10$...`.

**CORRECTO:**
```php
password_hash($password, PASSWORD_DEFAULT)   // al guardar
password_verify($password, $user['password_hash'])  // al verificar
```

**INCORRECTO:**
```php
$password                  // texto plano
md5($password)             // md5 es inseguro para contraseñas
$password === $user['contrasenia']  // comparación directa
```

---

## Errores de rutas y redirección

---

### E10 — Links del sidebar dan 404

**Síntoma:** Al hacer clic en un módulo del menú, página no encontrada.

**Rutas correctas en `views/Layouts/sidebar.php`:**
```
/DisneyStock/controllers/DashboardController.php
/DisneyStock/controllers/VentaController.php
/DisneyStock/controllers/ProductoController.php
/DisneyStock/controllers/InventarioController.php
/DisneyStock/controllers/ReporteController.php
/DisneyStock/controllers/UsuarioController.php
/DisneyStock/controllers/ConfiguracionController.php
/DisneyStock/controllers/InformacionController.php
```

**Errores comunes:**
```
/DisneyStock/controllers/VentaViewController.php        ← no existe
/DisneyStock/controllers/InventarioViewController.php   ← no existe
/DisneyStock/views/dashboard/configuracion.php          ← acceso directo sin auth
/DisneyStock/views/dashboard/informacion.php            ← acceso directo sin auth
```

---

### E11 — Empleados siempre caen en el Dashboard

**Síntoma:** Un empleado inicia sesión y llega al Dashboard en lugar de Ventas.

**CORRECTO en `controllers/AuthController.php`:**
```php
$destino = $user['rol'] === 'empleado'
    ? '/DisneyStock/controllers/VentaController.php'
    : '/DisneyStock/controllers/DashboardController.php';

header("Location: " . $destino);
exit;
```

---

### E12 — El botón "Cerrar sesión" no funciona

**URL correcta:**
```
/DisneyStock/controllers/AuthController.php?accion=logout
```

**El método `logout()` debe:**
```php
session_unset();
session_destroy();
setcookie('ds_remember_user', '', ['expires' => time() - 3600, 'path' => '/']);
header("Location: /DisneyStock/views/usuarios/login.php");
exit;
```

---

## Errores de seguridad CSRF

---

### E13 — Error 403 "Token CSRF inválido" al enviar formularios

**Causa A — Token no está en la vista:**
```php
// Dentro de cada <form method="POST">:
<?php csrfField(); ?>
// Genera: <input type="hidden" name="csrf_token" value="...">
```

**Causa B — `validateCsrf()` se llama antes de `session_start()`:**
```php
// Orden correcto obligatorio:
session_start();
require_once __DIR__ . '/../helpers/auth.php';
// ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();  // sesión ya activa aquí
}
```

**Causa C — El campo fue renombrado:**
```php
// CORRECTO — name debe ser exactamente 'csrf_token':
<input type="hidden" name="csrf_token" value="...">

// INCORRECTO:
<input type="hidden" name="token" value="...">
<input type="hidden" name="_csrf" value="...">
```

---

### E14 — Formularios POST sin verificación CSRF

**Riesgo:** Vulnerabilidad Cross-Site Request Forgery.

**Controllers que DEBEN tener `validateCsrf()` en su bloque POST:**
- `AuthController.php`
- `ProductoController.php`
- `InventarioController.php`
- `VentaController.php`
- `UsuarioController.php`
- `CategoriaController.php`
- `ConfiguracionController.php`

---

## Errores de inventario y ventas

---

### E15 — Al anular una venta el stock no se restaura

**Causa:** Se eliminó la lógica de restauración en `Venta::anular()`.

**CORRECTO — debe restaurar por cada item:**
```php
foreach ($detalles->fetchAll() as $d) {
    // Devolver stock a inventario
    $this->conn->prepare(
        "UPDATE inventario
         SET cantidad_stock = cantidad_stock + :cant, fecha_actualizacion = CURDATE()
         WHERE id_producto = :pid"
    )->execute([':cant' => $d['cantidad'], ':pid' => $d['id_producto']]);

    // Registrar movimiento de entrada por la anulación
    $this->conn->prepare(
        "INSERT INTO movimiento_inventario
         (tipo_movimiento, cantidad, fecha, descripcion, id_producto, id_usuario, id_venta)
         VALUES ('entrada', :cant, CURDATE(), :desc, :pid, :uid, :vid)"
    )->execute([...]);
}
```

---

### E16 — Datos corruptos al fallar una venta a mitad

**Síntoma:** Algunos productos se descontaron pero la venta no quedó registrada.

**Causa:** Se eliminó el `try/catch` o el `rollBack()`.

**CORRECTO — estructura obligatoria:**
```php
$this->conn->beginTransaction();
try {
    // ... todas las operaciones
    $this->conn->commit();
    return ['ok' => true];
} catch (Exception $e) {
    $this->conn->rollBack();  // deshace TODO
    return ['ok' => false, 'error' => $e->getMessage()];
}
```

---

### E17 — Alertas de stock no se generan ni se resuelven

**Condición para CREAR alerta** (en `Inventario::registrar()` y `Venta::crear()`):
```php
if ($act['stock_minimo'] > 0 && $act['cantidad_stock'] <= $act['stock_minimo']) {
    // Solo si no hay una alerta activa ya para este producto
    $ya = $this->conn->prepare(
        "SELECT COUNT(*) FROM alerta WHERE id_producto = :pid AND estado = 'activa'"
    );
    $ya->execute([':pid' => $id_producto]);
    if (!(int)$ya->fetchColumn()) {
        // INSERT alerta ...
    }
}
```

**Condición para RESOLVER alerta** (en entradas y ajustes):
```php
} elseif (in_array($tipo, ['entrada', 'ajuste'])) {
    $this->conn->prepare(
        "UPDATE alerta SET estado = 'resuelta', fecha_resolucion = CURDATE()
         WHERE id_producto = :pid AND estado = 'activa'"
    )->execute([':pid' => $id_producto]);
}
```

---

## Errores visuales

---

### E18 — Fechas muestran "00:00" o fecha incorrecta

**Causa:** Se agregó `H:i` al formato de fecha, pero las columnas son `DATE` sin hora.

**CORRECTO:**
```php
date('d/m/Y', strtotime($v['fecha_venta']))
```

**INCORRECTO:**
```php
date('d/m/Y H:i', strtotime($v['fecha_venta']))  // siempre muestra 00:00
```

**Archivos donde aplica:**
- `views/dashboard/ventas.php`
- `views/dashboard/admin.php` (tabla ventas recientes)
- `views/dashboard/reportes.php`
- `views/partials/detalle_venta.php`
- `views/dashboard/inventario.php`

---

### E19 — Modo oscuro no funciona o siempre carga de un modo fijo

**Causa A — Script de inicialización movido:**
```html
<!-- CORRECTO — inmediatamente después de <body> en header.php: -->
<body>
<script>
    if (localStorage.getItem('darkMode') === '1') {
        document.body.classList.add('dark');
    }
</script>
```

**Causa B — IDs duplicados o renombrados:**
```js
// La función busca exactamente estos IDs:
document.getElementById('darkIcon')    // en sidebar.php (topbar)
document.getElementById('darkToggle')  // botón del toggle
// No debe haber duplicados en el HTML
```

**Causa C — `toggleDark()` eliminado de `sidebar.php`:**
```js
function toggleDark() {
    const isDark = document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', isDark ? '1' : '0');
    actualizarIcono(isDark);
}
```

---

### E20 — SweetAlert no aparece tras crear/editar/eliminar

**Causa A — La alerta no se guarda en sesión antes de redirigir:**
```php
// CORRECTO — orden obligatorio:
$_SESSION['alert'] = ['icon' => 'success', 'title' => '...', 'text' => '...'];
header("Location: /DisneyStock/controllers/ProductoController.php");
exit;
```

**Causa B — `$_SESSION['alert']` no se limpia en `header.php`:**
```php
// CORRECTO en header.php:
$_dsAlert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);  // limpiar para que no se repita en próxima carga
```

**Causa C — Nombre de variable distinto al esperado en el JS:**
```php
// El JS en header.php usa $_dsAlert, no $alert ni $alertas
<?php if ($_dsAlert): ?>
<script>
    Swal.fire({
        icon:  <?= json_encode($_dsAlert['icon']) ?>,
        title: <?= json_encode($_dsAlert['title']) ?>,
        text:  <?= json_encode($_dsAlert['text']) ?>,
    });
</script>
<?php endif; ?>
```

---

### E21 — Categorías no aparecen en el selector de productos

**Causa A — La tabla está vacía:**
```sql
SELECT COUNT(*) FROM categoria;
-- Si retorna 0, insertar los datos iniciales (ver base-de-datos.md)
```

**Causa B — El controller no pasa `$categorias` a la vista:**
```php
// CORRECTO en ProductoController.php:
$modelCategoria = new Categoria($db);
$categorias     = $modelCategoria->obtenerTodas();
// La vista usa $categorias en el <select>
```

**Causa C — Nombre de tabla con mayúscula:**
```php
// CORRECTO:
"SELECT id_categoria, nombre_categoria FROM categoria"

// INCORRECTO (falla en Linux):
"SELECT id_categoria, nombre_categoria FROM Categoria"
```

---

## Errores de servidor

---

### E22 — Pantalla en blanco o error 500

**Activar errores para ver qué falla:**
```php
// Agregar al inicio del archivo problemático:
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**O revisar el log de Apache en Laragon:**
```
c:\laragon\logs\apache\error.log
```

---

### E23 — "No se pudo conectar a la base de datos"

**Verificar `config/database.php`:**
```php
private string $host     = "127.0.0.1";
private string $port     = "3320";        // verificar puerto en Laragon
private string $db_name  = "disney_stock"; // minúsculas
private string $username = "root";
private string $password = "";
```

**Errores comunes:**
- Puerto incorrecto (`3306` vs `3320` según la instalación de Laragon)
- Nombre de BD con mayúsculas: `Disney_Stock` — debe ser `disney_stock`
- MySQL de Laragon no está corriendo

---

### E24 — CSS o imágenes no cargan

**Causa:** Rutas relativas rotas o raíz del proyecto cambiada.

**CORRECTO — rutas absolutas con `/DisneyStock/` como raíz:**
```html
<link rel="stylesheet" href="/DisneyStock/Styles/dashboard.css?v=1.0.1">
<img src="/DisneyStock/img/BlancoSolo.png" alt="Logo">
```

**INCORRECTO:**
```html
<link rel="stylesheet" href="../../Styles/dashboard.css">  <!-- relativa, se rompe -->
<link rel="stylesheet" href="Styles/dashboard.css">         <!-- sin raíz -->
```

---

### E25 — Imagen de producto no se guarda o da error al subir

**Síntoma:** Al crear o editar un producto con imagen, el formulario no sube el archivo.

**Causa A — Falta `enctype` en el formulario:**
```html
<!-- CORRECTO — obligatorio para enviar archivos: -->
<form method="POST" action="..." enctype="multipart/form-data">

<!-- INCORRECTO — sin enctype, $_FILES llega vacío: -->
<form method="POST" action="...">
```

**Causa B — El campo de imagen tiene un `name` distinto:**
```html
<!-- CORRECTO: -->
<input type="file" name="imagen" ...>

<!-- INCORRECTO: -->
<input type="file" name="foto" ...>    <!-- no coincide con procesarImagen('imagen') -->
<input type="file" name="archivo" ...>
```

**Causa C — La carpeta de destino no existe o no tiene permisos:**
```
public/uploads/productos/   ← debe existir y ser escribible
```
El controller la crea automáticamente con `mkdir()` si no existe. Si da error de permisos en Linux:
```bash
chmod 755 public/uploads/productos/
```

**Causa D — El archivo supera el límite de PHP:**
```ini
; php.ini — estos valores deben ser mayores a 2MB:
upload_max_filesize = 10M
post_max_size = 12M
```

**Verificar que la imagen se muestra en la tabla:**
```php
// CORRECTO en la vista — ruta completa al archivo:
'/DisneyStock/public/uploads/productos/' . $p['imagen']

// Si $p['imagen'] es NULL, el placeholder gris aparece automáticamente
```

---

### Lista de verificación — imágenes de productos
- [ ] Formulario tiene `enctype="multipart/form-data"`
- [ ] Campo de file tiene `name="imagen"`
- [ ] Carpeta `public/uploads/productos/` existe
- [ ] `php.ini`: `upload_max_filesize >= 2M` y `post_max_size >= 3M`
- [ ] Columna `imagen VARCHAR(255)` existe en tabla `producto`

---

## Lista de verificación rápida

Ante cualquier modificación del tutor, revisar estos puntos:

### Base de datos
- [ ] Columna `contrasenia` (con **i**) en tabla `usuario`
- [ ] Campo `estado` en `usuario` es VARCHAR con `'activo'`/`'inactivo'`
- [ ] Tabla `inventario` existe con `cantidad_stock` y `stock_minimo`
- [ ] Tabla `producto` **no tiene** `stock_actual` ni `stock_minimo`
- [ ] Tabla `producto` **tiene** columna `imagen VARCHAR(255)` (puede ser NULL)
- [ ] Tabla `venta` tiene `id_usuario`, no `id_empleado` ni `id_administrador`
- [ ] Tabla `categoria` tiene datos (al menos las 10 iniciales)
- [ ] BD fue creada con `disney_stock_estructura.sql`, no con `DisneyStock.sql`

### Seguridad
- [ ] `requireAuth()` presente en todos los controllers (excepto `AuthController`)
- [ ] `validateCsrf()` al inicio de cada bloque POST
- [ ] `csrfField()` dentro de todos los `<form method="POST">`
- [ ] Contraseñas con `password_hash()` y verificadas con `password_verify()`
- [ ] `session_regenerate_id(true)` en el login

### Flujo de controllers
- [ ] Todos los POST terminan en `header("Location: ...")` + `exit`
- [ ] Empleados redirigidos a `VentaController`, no al Dashboard
- [ ] Acciones de escritura verifican `$rol === 'admin'` antes de ejecutar

### Modelos
- [ ] Todos los queries usan prepared statements (`:parametro`, no concatenación)
- [ ] Ventas y anulaciones dentro de `beginTransaction()` / `commit()` / `rollBack()`
- [ ] Stock actualizado en `inventario`, no en `producto`

### Vistas
- [ ] Fechas con formato `d/m/Y` sin `H:i`
- [ ] Un solo `id="darkToggle"` y `id="darkIcon"` en todo el HTML
- [ ] Links del sidebar apuntan a `/DisneyStock/controllers/NombreController.php`
- [ ] CSS con versión fija (`?v=1.0.1`), no con `time()`

### Servidor
- [ ] Laragon corriendo (Apache + MySQL)
- [ ] Puerto MySQL en `config/database.php` coincide con Laragon
- [ ] Nombre BD en config es `disney_stock` en minúsculas

---

## Restaurar con Git

Si el tutor modificó algo y deja de funcionar, se puede restaurar desde el historial:

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

> ⚠️ `git restore .` descarta **todos** los cambios no commiteados. Usar con cuidado.

---

**Autor:** Heidy Johanna Reyes Quesada  
**Proyecto:** DisneyStock  
**Fecha:** Agosto 2026
