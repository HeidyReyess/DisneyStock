# Documentación Backend PHP — DisneyStock

> Sistema de gestión de inventario y ventas para Variedades Disney  
> Desarrolladora: Heidy Johanna Reyes Quesada — Huila, Colombia  
> Stack: PHP 8, MySQL, PDO, Patrón MVC sin framework

---

## Tabla de Contenidos

1. [Modelos](#1-modelos)
2. [Instancias](#2-instancias)
3. [Controladores](#3-controladores)
4. [Clases y Funciones](#4-clases-y-funciones)
5. [Listas con foreach y patrones de vista](#5-listas-con-foreach-y-patrones-de-vista)
6. [Procesos Algorítmicos — Anidadas, Ciclos, Funciones](#6-procesos-algorítmicos)
7. [Módulos](#7-módulos)
8. [Relación Frontend — Backend — Lógica de Negocio](#8-relación-frontend--backend--lógica-de-negocio)
9. [Helper de Autenticación](#9-helper-de-autenticación)
10. [Conexión a la Base de Datos](#10-conexión-a-la-base-de-datos)
11. [Base de Datos](#11-base-de-datos)

---

## 1. Modelos

Un **modelo** es una clase PHP que representa una tabla de la base de datos y contiene todos los métodos para leer, insertar, actualizar o eliminar registros. El modelo **nunca** genera HTML ni procesa formularios — solo habla con la BD.

### Regla de oro

```
Modelo  →  solo toca la BD
Vista   →  solo genera HTML
Controlador  →  coordina modelo y vista
```

### Estructura base de un modelo en DisneyStock

```php
class Producto
{
    // La conexión PDO se guarda como propiedad privada
    // para que solo esta clase pueda usarla
    private PDO $conn;

    // El constructor recibe la conexión desde el controlador
    // Esto se llama "inyección de dependencias"
    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Método público que retorna todos los productos
    public function obtenerTodos(): array
    {
        return $this->conn->query("SELECT * FROM Producto ORDER BY nombre ASC")->fetchAll();
    }
}
```

### Modelos del proyecto y sus responsabilidades

| Archivo | Tablas que usa | Qué hace |
|---|---|---|
| `models/Producto.php` | Producto, Categoria, Alerta, Detalle_Venta | CRUD de productos, stock, top vendidos, reportes |
| `models/Venta.php` | Venta, Detalle_Venta, Factura, Movimiento_Inventario, Alerta | Crear/anular ventas, métricas del dashboard |
| `models/Inventario.php` | Movimiento_Inventario, Producto, Alerta | Movimientos manuales de stock, gestión de alertas |
| `models/Usuario.php` | Usuario, Administrador, Empleado | Autenticación, CRUD de usuarios, roles |
| `models/Categoria.php` | Categoria | CRUD de categorías de productos |

### Métodos más importantes por modelo

**Producto.php**
- `obtenerTodos($buscar, $idCategoria)` — lista con filtros opcionales
- `obtenerActivos()` — solo activos, para selects de ventas
- `contarStockBajo()` — para la tarjeta del dashboard
- `topVendidos($limite)` — ranking para el dashboard
- `crear($datos)` — inserta y retorna el ID
- `eliminar($id)` — verifica dependencias antes de borrar

**Venta.php**
- `metricasHoy($hoy)` — cantidad y monto del día
- `crear($items, $descuento, $id_adm, $id_emp)` — transacción completa
- `anular($id, $id_adm)` — restaura stock y marca anulada
- `obtenerDetalle($id)` — para el modal AJAX

**Inventario.php**
- `registrar($id_producto, $tipo, $cantidad, ...)` — movimiento + alerta automática
- `alertasActivas($limite)` — para el panel del dashboard

**Usuario.php**
- `obtenerPorUsuario($usuario)` — para el login
- `registrar($datos)` — transacción en dos tablas (Usuario + Admin/Empleado)
- `actualizar($id, $datos)` — actualiza contraseña solo si viene en `$datos`

---

## 2. Instancias

Una **instancia** es un objeto creado a partir de una clase usando `new`. En PHP, cada vez que escribes `new NombreClase()` estás creando un objeto independiente en memoria.

### Cómo funciona en DisneyStock

```php
// Paso 1: crear la instancia de Database y conectar de inmediato
// (new Database()) crea el objeto y ->conectar() llama el método
$db = (new Database())->conectar();

// Paso 2: instanciar los modelos pasando la misma conexión
$modelVenta      = new Venta($db);       // objeto de tipo Venta
$modelProducto   = new Producto($db);    // objeto de tipo Producto
$modelInventario = new Inventario($db);  // objeto de tipo Inventario

// Paso 3: llamar métodos sobre las instancias
$ventasHoy      = $modelVenta->metricasHoy($hoy);       // retorna array
$totalProductos = $modelProducto->contarActivos();      // retorna int
$alertas        = $modelInventario->alertasActivas(5);  // retorna array
```

### ¿Por qué se pasa `$db` al constructor?

Todos los modelos comparten **la misma conexión** `$db`. Si cada modelo creara su propia conexión, se abrirían 3 conexiones a MySQL en una sola petición, lo cual es ineficiente. Pasarla al constructor es el patrón **Dependency Injection** (inyección de dependencias).

### Instancia con uso inmediato (sin guardar en variable)

En algunos controladores se crea la instancia y se usa en la misma línea:

```php
// ProductoController.php — accion toggle
if ($id) (new Producto($db))->toggleEstado($id);

// ProductoController.php — accion eliminar
$resultado = (new Producto($db))->eliminar($id);
```

---

## 3. Controladores

Un **controlador** es el archivo PHP que recibe la petición del navegador, verifica la sesión, coordina los modelos y decide qué vista mostrar o a dónde redirigir.

### Flujo completo de una petición

```
Navegador
    │  GET o POST
    ▼
Controlador (PHP)
    │  1. Verificar sesión (requireAuth)
    │  2. Validar CSRF si es POST
    │  3. Leer parámetros ($_GET / $_POST)
    │  4. Instanciar modelos
    │  5. Llamar métodos del modelo
    ▼
Modelo (PHP + PDO)
    │  6. Ejecutar query SQL
    │  7. Retornar array de datos
    ▼
Controlador
    │  8a. Si es POST → guardar alerta → redirigir
    │  8b. Si es GET  → pasar variables → cargar vista
    ▼
Vista (HTML + PHP)
    │  9. Renderizar HTML con los datos
    ▼
Navegador muestra el resultado
```

### Estructura de un controlador fusionado (GET + POST)

En DisneyStock cada módulo tiene **un solo controlador** que maneja tanto mostrar la vista (GET) como procesar acciones (POST):

```php
<?php
session_start();
require_once __DIR__ . '/../helpers/auth.php';

requireAuth(); // detiene si no hay sesión activa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Producto.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? ''; // leer la accion
$rol    = $_SESSION['usuario']['rol'];

// ── POST: procesar formulario ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(); // validar token de seguridad

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        // ... validar, crear, alertar
        $_SESSION['alert'] = ['icon'=>'success', 'title'=>'Creado', 'text'=>'...'];
        header("Location: /DisneyStock/controllers/ProductoController.php");
        exit;
    }
}

// ── GET: mostrar vista ────────────────────────────────────
$productos  = (new Producto($db))->obtenerTodos();
$titulo     = "Productos";
require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/productos.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
```

### Controladores del proyecto

| Archivo | Acceso | Responsabilidad |
|---|---|---|
| `AuthController.php` | Público | Login y logout. Único que usa clase PHP interna |
| `DashboardController.php` | Solo admin | Recopila 7 métricas y renderiza el panel |
| `ProductoController.php` | Ver: todos / Editar: admin | CRUD de productos + subida de imágenes |
| `VentaController.php` | Admin y empleado | Crear ventas, anular, AJAX de detalle |
| `InventarioController.php` | Ver: todos / Movimientos: admin | Ver stock + registrar movimientos |
| `UsuarioController.php` | Solo admin | CRUD de usuarios del sistema |
| `ReporteController.php` | Admin y empleado | 4 tipos de reporte con filtros de fecha |
| `CategoriaController.php` | Solo admin | Crear/eliminar categorías (sin vista propia) |

### AuthController — único que usa clase PHP

`AuthController.php` es el único que define una **clase** en lugar de código procedimental, porque maneja dos acciones distintas (login y logout) y necesita el método privado `alerta()`:

```php
class AuthController
{
    public function login(): void  { /* verifica credenciales */ }
    public function logout(): void { /* destruye la sesión */ }

    // private: solo usable dentro de esta clase
    private function alerta(string $icon, string $title, string $text): never
    {
        $_SESSION['alert'] = compact('icon', 'title', 'text');
        header("Location: " . URL_LOGIN);
        exit; // never = esta función nunca retorna (siempre hace exit)
    }
}

// Instanciar y ejecutar según el parámetro GET
$controller = new AuthController();
$accion     = $_GET['accion'] ?? 'login';
if ($accion === 'logout') $controller->logout();
else $controller->login();
```

---

## 4. Clases y Funciones

### Clases

Una **clase** en PHP agrupa propiedades (variables) y métodos (funciones) que pertenecen a un mismo concepto. En DisneyStock se usan para:

- **Modelos** — representan tablas de BD
- **Database** — encapsula la conexión PDO
- **AuthController** — maneja el proceso de autenticación

```php
class Usuario
{
    // ── Propiedades ───────────────────────────────────────
    private PDO $conn;  // private = solo accesible dentro de Usuario

    // ── Constructor: se ejecuta al hacer new Usuario($db) ─
    public function __construct(PDO $db)
    {
        $this->conn = $db; // $this = la instancia actual
    }

    // ── Método público: accesible desde el controlador ────
    public function obtenerPorUsuario(string $usuario): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id_usuario AS id, u.nombre, u.contrasena AS password_hash,
                    CASE WHEN a.id_administrador IS NOT NULL THEN 'admin' ELSE 'empleado' END AS rol
             FROM Usuario u
             LEFT JOIN Administrador a ON a.id_usuario = u.id_usuario
             WHERE u.usuario = :usuario AND u.activo = 1 LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->fetch(); // retorna array o false si no existe
    }

    // ── Método con lógica condicional en el UPDATE ────────
    public function actualizar(int $id_usuario, array $datos): bool|string
    {
        $sql = "UPDATE Usuario SET nombre = :nombre, usuario = :usuario, updated_at = NOW()";

        // Solo agregar contraseña al UPDATE si el admin envió una nueva
        if (!empty($datos['password_hash'])) {
            $sql .= ", contrasena = :contrasena";
        }
        $sql .= " WHERE id_usuario = :id";
        // ...
    }
}
```

**Modificadores de acceso en el proyecto:**

| Modificador | Usado en | Significado |
|---|---|---|
| `private` | `$conn` en todos los modelos | Solo la clase puede acceder |
| `public` | Todos los métodos de modelos | Accesible desde el controlador |
| `private` | `alerta()` en AuthController | Solo AuthController puede llamarla |

### Funciones globales (fuera de clases)

Se usan en los helpers y como funciones auxiliares dentro de controladores:

```php
// ── helpers/auth.php — usada en todos los controladores ──
function requireAuth(?string $rolRequerido = null, string $redireccion = '...'): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Si no hay sesión, mandar al login
    if (!isset($_SESSION['usuario'])) {
        header("Location: /DisneyStock/views/usuarios/login.php");
        exit;
    }

    // Si se exige un rol y no coincide, redirigir
    if ($rolRequerido !== null && $_SESSION['usuario']['rol'] !== $rolRequerido) {
        header("Location: $redireccion");
        exit;
    }
}

// ── controllers/ProductoController.php — función local ───
// Retorna: string (nombre archivo) | false (error) | null (no se subio imagen)
function procesarImagen(array $file, ?string $imagenActual = null): string|false|null
{
    if (empty($file['name'])) return null; // no se adjuntó imagen

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
        $_SESSION['alert'] = ['icon'=>'warning', ...];
        return false; // error: formato no permitido
    }

    $nombreFinal = uniqid('prod_', true) . '.' . $ext; // nombre único
    move_uploaded_file($file['tmp_name'], $carpeta . $nombreFinal);

    // Borrar imagen anterior si existía
    if ($imagenActual && file_exists($carpeta . $imagenActual)) {
        unlink($carpeta . $imagenActual);
    }

    return $nombreFinal; // éxito
}
```

### Tipos de retorno en PHP 8

El proyecto usa tipos de retorno modernos de PHP 8:

```php
array         // siempre retorna un array (puede estar vacío)
array|false   // retorna array o false si no encontró nada
bool|string   // retorna true (éxito) o string con mensaje de error
int|false     // retorna el ID generado o false si falló
?string       // retorna string o null (el ? indica nullable)
void          // no retorna nada
never         // nunca retorna (siempre hace exit o throw)
```

---

## 5. Listas con foreach y Patrones de Vista

### foreach básico — renderizar tabla

El uso más frecuente es recorrer los resultados de una query para construir filas HTML:

```php
// views/dashboard/productos.php
<?php foreach ($productos as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['nombre']) ?></td>
    <td>$<?= number_format($p['precio_venta'], 0, ',', '.') ?></td>
    <td>
        <span style="background:<?= $p['estado']==='activo' ? '#D1FAE5' : '#FEE2E2' ?>;">
            <?= ucfirst($p['estado']) ?>
        </span>
    </td>
</tr>
<?php endforeach; ?>
```

### foreach con índice — ranking del dashboard

Cuando necesitas el número de posición, usas `$indice => $valor`:

```php
// views/dashboard/admin.php — Top Productos
<?php foreach ($topProductos as $i => $p): ?>
<div>
    <!-- $i empieza en 0, por eso se le suma 1 para mostrar 1, 2, 3... -->
    <span>#<?= $i + 1 ?></span>
    <span><?= htmlspecialchars($p['nombre']) ?></span>
    <span><?= $p['vendidos'] ?> unidades</span>
</div>
<?php endforeach; ?>
```

### Patrón if/empty + else + foreach — tabla con estado vacío

Este patrón se repite en todas las vistas del dashboard:

```php
<?php if (empty($productos)): ?>
    <!-- Mensaje cuando no hay datos -->
    <div style="text-align:center; padding:60px 20px; color:#94A3B8;">
        <i class="fas fa-box" style="font-size:3rem; opacity:0.3;"></i>
        <p>No hay productos registrados.</p>
    </div>
<?php else: ?>
    <!-- Tabla con datos -->
    <table>
        <thead>...</thead>
        <tbody>
            <?php foreach ($productos as $p): ?>
            <tr>...</tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
```

### foreach en los modelos — calcular subtotal

Dentro de los modelos también se usa foreach para procesar colecciones de datos:

```php
// models/Venta.php — calcular subtotal antes de insertar
$subtotal = 0;
foreach ($items as $it) {
    // precio × cantidad de cada ítem
    $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
}
$total = max(0, $subtotal - $descuento); // max(0,...) evita totales negativos
```

### foreach con match — colores dinámicos por estado

```php
// views/dashboard/ventas.php
<?php foreach ($ventas as $v):
    // match es como switch pero más conciso y devuelve un valor
    $ec = match($v['estado']) {
        'completada' => ['#D1FAE5', '#065F46'],  // verde
        'anulada'    => ['#FEE2E2', '#991B1B'],  // rojo
        default      => ['#FEF3C7', '#92400E'],  // amarillo (pendiente)
    };
?>
<tr>
    <td>
        <span style="background:<?= $ec[0] ?>; color:<?= $ec[1] ?>;">
            <?= htmlspecialchars($v['estado']) ?>
        </span>
    </td>
</tr>
<?php endforeach; ?>
```

---

## 6. Procesos Algorítmicos

### Función con múltiples validaciones anidadas — `registrar()` en Inventario

Este método muestra cómo se encadenan validaciones, condicionales y lógica de negocio:

```php
// models/Inventario.php
public function registrar(int $id_producto, string $tipo, int $cantidad,
                          ?string $descripcion, ?int $id_adm): array
{
    // Nivel 1: leer el estado actual del producto
    $stk = $this->conn->prepare("SELECT stock_actual, stock_minimo, nombre FROM Producto WHERE id_producto = :pid");
    $stk->execute([':pid' => $id_producto]);
    $prod = $stk->fetch();

    // Nivel 1: validación — producto existe?
    if (!$prod) {
        return ['ok' => false, 'error' => 'Producto no encontrado.'];
    }

    // Nivel 1: validación — hay stock suficiente para la salida?
    if ($tipo === 'salida' && $prod['stock_actual'] < $cantidad) {
        return ['ok' => false, 'error' => "Solo hay {$prod['stock_actual']} unidades disponibles."];
    }

    // Insertar el movimiento en el historial
    $this->conn->prepare("INSERT INTO Movimiento_Inventario (...) VALUES (...)")->execute([...]);

    // Nivel 1: seleccionar la operación SQL según el tipo
    if ($tipo === 'entrada') {
        $sql = "UPDATE Producto SET stock_actual = stock_actual + :cant WHERE id_producto = :pid";
    } elseif ($tipo === 'salida') {
        $sql = "UPDATE Producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid";
    } else { // ajuste: reemplaza el valor exacto
        $sql = "UPDATE Producto SET stock_actual = :cant WHERE id_producto = :pid";
    }
    $this->conn->prepare($sql)->execute([':cant' => $cantidad, ':pid' => $id_producto]);

    // Releer el stock ya actualizado
    $nuevo = $this->conn->prepare("SELECT stock_actual, stock_minimo FROM Producto WHERE id_producto = :pid");
    $nuevo->execute([':pid' => $id_producto]);
    $act = $nuevo->fetch();

    // Nivel 1: lógica de alertas
    if ($act['stock_minimo'] > 0 && $act['stock_actual'] <= $act['stock_minimo']) {
        // Nivel 2: crear alerta solo si no existe una activa para este producto
        $ya = $this->conn->prepare("SELECT COUNT(*) FROM Alerta WHERE id_producto=:pid AND estado='activa'");
        $ya->execute([':pid' => $id_producto]);
        if (!(int)$ya->fetchColumn()) {
            // Nivel 3: insertar la alerta
            $this->conn->prepare("INSERT INTO Alerta (...) VALUES (...)")->execute([...]);
        }
    } elseif (in_array($tipo, ['entrada', 'ajuste'])) {
        // Si el stock se normalizó, resolver alertas activas
        $this->conn->prepare("UPDATE Alerta SET estado='resuelta', fecha_resolucion=NOW() WHERE id_producto=:pid AND estado='activa'")->execute([':pid' => $id_producto]);
    }

    return ['ok' => true];
}
```

### Transacción con try/catch — `crear()` en Venta

Una **transacción** agrupa múltiples operaciones SQL de forma que o todas se ejecutan o ninguna. Si una falla, el `rollBack()` deshace todo:

```php
// models/Venta.php
public function crear(array $items, float $descuento, ?int $id_adm, ?int $id_emp): array
{
    // Ciclo 1: calcular subtotal antes de iniciar la transacción
    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
    }
    $total = max(0, $subtotal - $descuento);

    $this->conn->beginTransaction(); // inicio: todo o nada

    try {
        // 1. Insertar cabecera de la venta
        $this->conn->prepare("INSERT INTO Venta (...) VALUES (...)")->execute([...]);
        $id_venta = (int)$this->conn->lastInsertId();
        $numFac   = 'DS-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT); // DS-000001

        // Ciclo 2: procesar cada producto del carrito
        foreach ($items as $it) {
            $pid    = (int)$it['id_producto'];
            $cant   = (int)$it['cantidad'];
            $precio = (float)$it['precio_unitario'];

            // 2. Verificar stock — si falla, lanza excepción que activa el catch
            $stk = $this->conn->prepare("SELECT stock_actual, nombre FROM Producto WHERE id_producto = :pid");
            $stk->execute([':pid' => $pid]);
            $prod = $stk->fetch();
            if (!$prod || $prod['stock_actual'] < $cant) {
                throw new Exception("Stock insuficiente para: {$prod['nombre']}");
            }

            // 3. Insertar línea del detalle
            $this->conn->prepare("INSERT INTO Detalle_Venta (...) VALUES (...)")->execute([...]);

            // 4. Descontar el stock del producto
            $this->conn->prepare("UPDATE Producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid")->execute([...]);

            // 5. Registrar movimiento de salida
            $this->conn->prepare("INSERT INTO Movimiento_Inventario (...) VALUES ('salida', ...)")->execute([...]);
        }

        // 6. Crear la factura
        $this->conn->prepare("INSERT INTO Factura (numero, total, id_venta) VALUES (:num, :total, :vid)")->execute([...]);

        $this->conn->commit(); // confirmar todos los cambios
        return ['ok' => true, 'factura' => $numFac, 'total' => $total];

    } catch (Exception $e) {
        $this->conn->rollBack(); // deshacer TODO si algo falló
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
```

### switch — despachar tipos de reporte

```php
// controllers/ReporteController.php
switch ($tipo) {
    case 'ventas':
        $datos    = $modelVenta->reporteVentas($desde, $hasta);
        $columnas = ['Factura', 'Vendedor', 'Subtotal', 'Descuento', 'Total', 'Estado', 'Fecha'];
        break;

    case 'inventario':
        $datos    = $modelProducto->reporteInventario();
        $columnas = ['ID', 'Nombre', 'Categoria', 'Stock', 'Minimo', 'Valor Costo', 'Valor Venta'];
        break;

    case 'stock_bajo':
        $datos    = $modelProducto->reporteStockBajo();
        $columnas = ['ID', 'Nombre', 'Categoria', 'Stock Actual', 'Stock Minimo', 'Faltante'];
        break;

    case 'movimientos':
        $datos    = $modelInventario->reporteMovimientos($desde, $hasta);
        $columnas = ['Producto', 'Tipo', 'Cantidad', 'Descripcion', 'Usuario', 'Fecha'];
        break;
}
```

### SQL dinámico con condiciones opcionales

En `Producto::obtenerTodos()` y `Venta::listar()` la query se construye dinámicamente:

```php
// models/Producto.php
public function obtenerTodos(string $buscar = '', int|string $idCategoria = ''): array
{
    $sql    = "SELECT p.*, c.nombre_categoria FROM Producto p LEFT JOIN Categoria c ON ... WHERE 1=1";
    $params = [];

    // Solo agregar filtro de nombre si se buscó algo
    if ($buscar) {
        $sql .= " AND p.nombre LIKE :q";
        $params[':q'] = "%$buscar%"; // % permite buscar en cualquier posición
    }

    // Solo agregar filtro de categoría si se seleccionó una
    if ($idCategoria !== '') {
        $sql .= " AND p.id_categoria = :cat";
        $params[':cat'] = $idCategoria;
    }

    $sql .= " ORDER BY p.nombre ASC";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
```

---

## 7. Módulos

DisneyStock está organizado en **módulos funcionales**. Cada módulo agrupa un controlador, uno o más modelos y una vista.

### Tabla de módulos

| Módulo | Controlador | Modelo(s) | Vista |
|---|---|---|---|
| **Landing** | — | — | `public/index.php` |
| **Login** | `AuthController.php` | `Usuario.php` | `views/usuarios/login.php` |
| **Dashboard** | `DashboardController.php` | Venta, Producto, Inventario | `views/dashboard/admin.php` |
| **Ventas** | `VentaController.php` | `Venta.php`, `Producto.php` | `views/dashboard/ventas.php` |
| **Productos** | `ProductoController.php` | `Producto.php`, `Categoria.php` | `views/dashboard/productos.php` |
| **Inventario** | `InventarioController.php` | `Inventario.php`, `Producto.php` | `views/dashboard/inventario.php` |
| **Reportes** | `ReporteController.php` | Venta, Producto, Inventario | `views/dashboard/reportes.php` |
| **Usuarios** | `UsuarioController.php` | `Usuario.php` | `views/dashboard/usuarios.php` |
| **Categorias** | `CategoriaController.php` | `Categoria.php` | *(modal dentro de productos)* |
| **Configuracion** | `ConfiguracionController.php` | — | `views/dashboard/configuracion.php` |

### Estructura completa de carpetas

```
DisneyStock/
├── config/
│   └── database.php            ← Clase Database con conexión PDO
│
├── controllers/
│   ├── AuthController.php      ← Login / Logout (usa clase PHP)
│   ├── DashboardController.php ← Panel principal (solo admin)
│   ├── VentaController.php     ← Ventas (admin + empleado)
│   ├── ProductoController.php  ← Productos (admin edita, empleado ve)
│   ├── InventarioController.php← Stock y movimientos
│   ├── UsuarioController.php   ← Gestión de usuarios (solo admin)
│   ├── ReporteController.php   ← Reportes (admin + empleado)
│   └── CategoriaController.php ← Categorías (solo admin)
│
├── models/
│   ├── Venta.php               ← Venta, Detalle_Venta, Factura
│   ├── Producto.php            ← Producto, Alerta, reportes
│   ├── Inventario.php          ← Movimiento_Inventario, alertas
│   ├── Usuario.php             ← Usuario, Administrador, Empleado
│   └── Categoria.php           ← Categoria
│
├── views/
│   ├── Layouts/
│   │   ├── header.php          ← DOCTYPE, CSS, verificación sesión, CSRF
│   │   ├── sidebar.php         ← Navegación lateral + topbar con usuario
│   │   └── footer.php          ← Cierre de etiquetas HTML
│   ├── dashboard/
│   │   ├── admin.php           ← Panel con métricas y tablas
│   │   ├── ventas.php          ← Tabla + modal nueva venta + modal detalle
│   │   ├── productos.php       ← Tabla + modal producto + modal categorías
│   │   ├── inventario.php      ← Tabla + panel movimientos + modal
│   │   ├── reportes.php        ← Tabla dinámica con 4 tipos de reporte
│   │   ├── usuarios.php        ← Tabla + modal crear/editar usuario
│   │   ├── configuracion.php   ← Ajustes del sistema (persiste en sesión)
│   │   └── informacion.php     ← Datos del negocio y del sistema
│   ├── partials/
│   │   └── detalle_venta.php   ← HTML parcial — respuesta AJAX del modal
│   └── usuarios/
│       ├── login.php           ← Formulario de inicio de sesión
│       └── registre.php        ← Formulario de registro (solo admin accede)
│
├── helpers/
│   └── auth.php                ← requireAuth(), csrfToken(), validateCsrf(), csrfField()
│
├── public/
│   ├── index.php               ← Landing page con carrusel
│   ├── login.css               ← Estilos del formulario de login
│   └── style.css               ← Estilos de la landing page
│
├── Styles/
│   ├── dashboard.css           ← Clases del dashboard (cards, botones, tablas)
│   └── sidebar.css             ← Estilos del sidebar y navegación
│
├── img/                        ← Logos del proyecto
├── sql/
│   └── DisneyStock.sql         ← Script completo de creación de BD
└── Documentacion/
    └── backend-php.md          ← Este archivo
```

### Roles y acceso por módulo

| Módulo | Admin | Empleado |
|---|---|---|
| Dashboard | ✅ Ver métricas completas | ❌ Redirige a Ventas |
| Ventas | ✅ Crear + Anular | ✅ Solo Crear |
| Productos | ✅ CRUD completo | ✅ Solo ver |
| Inventario | ✅ Ver + Registrar movimientos | ✅ Solo ver |
| Reportes | ✅ Todos los tipos | ✅ Todos los tipos |
| Usuarios | ✅ CRUD completo | ❌ Sin acceso |
| Configuracion | ✅ Acceso total | ❌ Sin acceso |

---

## 8. Relación Frontend — Backend — Lógica de Negocio

### Las tres capas del sistema

```
┌─────────────────────────────────────────────────────────┐
│  FRONTEND (Navegador)                                   │
│  HTML + CSS + JavaScript                                │
│  Tailwind CSS, SweetAlert2, Font Awesome                │
│  Muestra datos, captura inputs, feedback visual         │
└─────────────────────┬───────────────────────────────────┘
                      │  HTTP GET / POST
┌─────────────────────▼───────────────────────────────────┐
│  BACKEND — CONTROLADOR (PHP)                            │
│  Verifica sesión y CSRF                                 │
│  Lee $_GET y $_POST                                     │
│  Coordina los modelos                                   │
│  Decide qué vista cargar o a dónde redirigir            │
└─────────────────────┬───────────────────────────────────┘
                      │  Llama métodos del modelo
┌─────────────────────▼───────────────────────────────────┐
│  BACKEND — MODELO (PHP + PDO)                           │
│  Lógica de negocio (calcular totales, validar stock)    │
│  Queries SQL con prepared statements                    │
│  Transacciones con rollback                             │
│  Retorna arrays de datos                                │
└─────────────────────┬───────────────────────────────────┘
                      │  SQL
┌─────────────────────▼───────────────────────────────────┐
│  BASE DE DATOS (MySQL)                                  │
│  11 tablas relacionadas                                 │
│  Usuario, Administrador, Empleado                       │
│  Producto, Categoria, Alerta                            │
│  Venta, Detalle_Venta, Factura                          │
│  Movimiento_Inventario, Reporte                         │
└─────────────────────────────────────────────────────────┘
```

### Ejemplo completo: flujo de registrar una venta

#### Paso 1 — Frontend: el usuario arma el carrito

```javascript
// views/dashboard/ventas.php — JavaScript
function agregarItem() {
    const sel   = document.getElementById('selProducto');
    const cant  = parseInt(document.getElementById('cantidadItem').value) || 1;
    const opt   = sel.options[sel.selectedIndex];
    const precio = parseFloat(opt.dataset.precio) || 0;

    // Agregar al array en memoria
    items.push({ id_producto: sel.value, nombre: opt.dataset.nombre, precio_unitario: precio, cantidad: cant });

    renderItems();     // actualizar la lista visual
    calcularTotales(); // actualizar subtotal, descuento y total
}

// Al enviar el formulario, serializar el carrito como JSON
document.getElementById('itemsJSON').value = JSON.stringify(items);
// El formulario POST envía: accion=crear, items=[{...},{...}], descuento=0
```

#### Paso 2 — Backend: el controlador recibe y delega

```php
// controllers/VentaController.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(); // verificar token de seguridad

    if ($accion === 'crear') {
        $descuento = (float)($_POST['descuento'] ?? 0);
        $items     = json_decode($_POST['items'] ?? '[]', true); // JSON → array PHP

        if (empty($items)) {
            $_SESSION['alert'] = ['icon'=>'warning', 'title'=>'Sin productos', ...];
            header("Location: /DisneyStock/controllers/VentaController.php"); exit;
        }

        // Delegar toda la lógica al modelo
        $resultado = $model->crear($items, $descuento, $id_adm, $id_emp);

        // Guardar alerta según el resultado
        $_SESSION['alert'] = $resultado['ok']
            ? ['icon'=>'success', 'title'=>'Venta registrada', 'text'=>"Factura {$resultado['factura']}"]
            : ['icon'=>'error',   'title'=>'Error',            'text'=>$resultado['error']];

        header("Location: /DisneyStock/controllers/VentaController.php"); exit;
    }
}
```

#### Paso 3 — Backend: el modelo ejecuta la transacción

```php
// models/Venta.php — lógica de negocio real
public function crear(array $items, float $descuento, ?int $id_adm, ?int $id_emp): array
{
    // Calcular subtotal
    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
    }
    $total = max(0, $subtotal - $descuento); // nunca negativo

    $this->conn->beginTransaction();
    try {
        // Insertar Venta + Detalle_Venta + descontar stock + Movimiento + Alerta + Factura
        // ...
        $this->conn->commit();
        return ['ok' => true, 'factura' => 'DS-000001', 'total' => 60000];
    } catch (Exception $e) {
        $this->conn->rollBack(); // si algo falla, deshacer TODO
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
```

#### Paso 4 — Frontend: mostrar resultado al usuario

```php
// views/Layouts/header.php — se ejecuta en la siguiente carga de página
<?php if ($_dsAlert): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon:  <?= json_encode($_dsAlert['icon']) ?>,   // json_encode evita XSS
            title: <?= json_encode($_dsAlert['title']) ?>,
            text:  <?= json_encode($_dsAlert['text']) ?>,
            confirmButtonColor: '#4A1D96'
        });
    });
</script>
<?php endif; ?>
```

### Separación de responsabilidades — regla de oro

| Capa | Puede hacer | NO puede hacer |
|---|---|---|
| **Vista** | Mostrar datos, capturar inputs con formularios, llamadas AJAX | Conectarse a BD, leer `$_POST`, lógica de negocio |
| **Controlador** | Leer `$_GET`/`$_POST`, validar inputs, instanciar modelos, redirigir | Queries SQL directas, generar HTML |
| **Modelo** | Queries SQL, cálculos de negocio, transacciones | Leer `$_POST`, hacer `echo` o generar HTML |

---

## 9. Helper de Autenticación

El archivo `helpers/auth.php` centraliza la seguridad. Se incluye en todos los controladores:

```php
// Verificar sesión — bloquea sin rol específico
requireAuth();

// Verificar sesión con rol requerido
requireAuth('admin');                                         // solo admin
requireAuth('admin', '/DisneyStock/controllers/VentaController.php'); // admin, sino ir a ventas

// Validar CSRF en peticiones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(); // detiene con 403 si el token no coincide
}

// En las vistas, dentro de los formularios
<?php csrfField(); ?>
// Genera: <input type="hidden" name="csrf_token" value="a3f9...">
```

### ¿Qué es CSRF y por qué importa?

Un ataque **CSRF** (Cross-Site Request Forgery) ocurre cuando una página maliciosa envía un formulario a tu sistema usando la sesión activa del usuario. El token CSRF previene esto porque:

1. Al cargar el formulario, el servidor genera un token aleatorio único y lo guarda en sesión
2. El token se incluye como campo oculto en el formulario
3. Al procesar el POST, el servidor compara el token enviado con el de sesión
4. Si no coinciden, rechaza con error 403

```
Token en sesión: "a3f9b2c1..."
Token en formulario: "a3f9b2c1..."  ← coinciden → OK
Token de sitio malicioso: no tiene → rechazado
```

---

## 10. Conexión a la Base de Datos

La clase `Database` en `config/database.php` maneja la conexión PDO con un patrón **Singleton** — si ya existe una conexión, la reutiliza en lugar de crear una nueva:

```php
class Database
{
    // Credenciales de conexión
    private string $host    = "127.0.0.1";
    private string $port    = "3320";       // puerto de Laragon
    private string $db_name = "disneystock";
    private string $username = "root";
    private string $password = "";

    public ?PDO $conn = null; // null hasta que se llame conectar()

    public function conectar(): PDO
    {
        // Si ya existe conexión, retornarla directamente (Singleton)
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // DSN = Data Source Name: cadena de configuración de PDO
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // errores como excepciones
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // arrays asociativos por defecto
                PDO::ATTR_EMULATE_PREPARES   => false,                   // prepared statements reales
            ]);

        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage()); // registrar en log del servidor
            die(json_encode(['error' => true, 'mensaje' => 'No se pudo conectar a la BD.']));
        }

        return $this->conn;
    }
}
```

### PDO y Prepared Statements

Todos los modelos usan **prepared statements** — esto previene SQL injection:

```php
// ❌ Vulnerable a SQL Injection (NUNCA hacer esto)
$stmt = $conn->query("SELECT * FROM Usuario WHERE usuario = '$usuario'");
// Si $usuario = "' OR 1=1 --" → devuelve todos los usuarios

// ✅ Seguro con prepared statements
$stmt = $conn->prepare("SELECT * FROM Usuario WHERE usuario = :usuario");
$stmt->execute([':usuario' => $usuario]);
// El valor se trata siempre como dato, nunca como SQL
```

---

## 11. Base de Datos

### Tablas del sistema

```sql
Usuario          ← datos base de autenticación (nombre, usuario, contrasena)
Administrador    ← extiende Usuario con rol admin
Empleado         ← extiende Usuario con rol empleado
Categoria        ← categorías de productos
Producto         ← catálogo con precio, stock, imagen
Alerta           ← alertas de stock bajo (activa / resuelta)
Venta            ← cabecera de cada venta (total, estado, fecha)
Detalle_Venta    ← ítems de cada venta (precio × cantidad)
Factura          ← número de factura DS-XXXXXX vinculado a Venta
Movimiento_Inventario ← historial de entradas/salidas/ajustes de stock
Reporte          ← registro de reportes generados (sin implementar aún)
```

### Diagrama simplificado de relaciones

```
Usuario ──┬── Administrador ──┬── Venta ──── Detalle_Venta ──── Producto
          └── Empleado ───────┘              Factura            │
                                             Movimiento_Inventario
                               Categoria ───── Producto ────── Alerta
```

### Usuario inicial (dato de prueba)

```
Usuario:    admin
Contraseña: admin123
Rol:        Administrador
```

---

*Documentación generada para el proyecto DisneyStock — v1.0 — Agosto 2026*  
*Desarrolladora: Heidy Johanna Reyes Quesada — Huila, Colombia*
