# Documentación Backend PHP — DisneyStock

> Sistema de gestión de inventario y ventas para Variedades Disney  
> Desarrolladora: Heidy Johanna Reyes Quesada — Huila, Colombia  
> Stack: PHP 8, MySQL, PDO, Patrón MVC sin framework

---

## 1. Modelos

Un **modelo** es una clase PHP que representa una tabla de la base de datos y contiene todos los métodos para leer, insertar, actualizar o eliminar registros. El modelo **nunca** genera HTML ni procesa formularios — solo habla con la BD.

### Estructura base de un modelo en DisneyStock

```php
class Producto
{
    private PDO $conn; // conexión recibida desde el controlador

    public function __construct(PDO $db)
    {
        $this->conn = $db; // se guarda la conexión en la instancia
    }

    public function obtenerTodos(): array
    {
        return $this->conn->query("SELECT * FROM Producto")->fetchAll();
    }
}
```

### Modelos del proyecto

| Archivo | Tabla(s) principal(es) | Responsabilidad |
|---|---|---|
| `models/Producto.php` | Producto, Categoria, Alerta | CRUD de productos, stock, reportes |
| `models/Venta.php` | Venta, Detalle_Venta, Factura | Crear/anular ventas, métricas |
| `models/Inventario.php` | Movimiento_Inventario, Alerta | Movimientos manuales de stock |
| `models/Usuario.php` | Usuario, Administrador, Empleado | Autenticación, gestión de usuarios |
| `models/Categoria.php` | Categoria | CRUD de categorías |

---

## 2. Instancias

Una **instancia** es un objeto creado a partir de una clase usando `new`. En DisneyStock, los modelos se instancian en los controladores pasándoles la conexión a la base de datos.

### Cómo se crea una instancia en el proyecto

```php
// Paso 1: conectar a la BD usando la clase Database
$db = (new Database())->conectar();

// Paso 2: instanciar el modelo pasándole la conexión
$modelProducto = new Producto($db);

// Paso 3: usar el modelo
$productos = $modelProducto->obtenerTodos();
```

**¿Por qué se pasa `$db` al constructor?**  
Porque todos los modelos comparten la misma conexión. Así no se abren múltiples conexiones a MySQL en una misma petición — patrón conocido como *Dependency Injection*.

### Ejemplo real en DashboardController.php

```php
$db              = (new Database())->conectar();
$modelVenta      = new Venta($db);       // instancia de Venta
$modelProducto   = new Producto($db);    // instancia de Producto
$modelInventario = new Inventario($db);  // instancia de Inventario

$ventasHoy    = $modelVenta->metricasHoy($hoy);
$totalProductos = $modelProducto->contarActivos();
```

---

## 3. Controladores

Un **controlador** es el archivo PHP que recibe la petición del usuario (GET o POST), coordina los modelos y decide qué vista renderizar. En DisneyStock cada módulo tiene un controlador único que maneja ambas responsabilidades.

### Flujo de una petición

```
Navegador  →  Controlador  →  Modelo  →  Base de Datos
                   ↓
               Vista (HTML)
```

### Estructura de un controlador fusionado (GET + POST)

```php
session_start();
require_once __DIR__ . '/../helpers/auth.php';
requireAuth(); // verifica sesión

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Producto.php';

$db     = (new Database())->conectar();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// POST: procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    // ... lógica de guardado
    header("Location: /DisneyStock/controllers/ProductoController.php");
    exit;
}

// GET: preparar datos y mostrar vista
$productos = (new Producto($db))->obtenerTodos();
require_once __DIR__ . '/../views/dashboard/productos.php';
```

### Controladores del proyecto

| Archivo | Acceso | Qué hace |
|---|---|---|
| `AuthController.php` | Público | Login y logout |
| `DashboardController.php` | Solo admin | Métricas del panel |
| `ProductoController.php` | Admin (editar), Empleado (ver) | CRUD de productos |
| `VentaController.php` | Admin y empleado | Registrar y anular ventas |
| `InventarioController.php` | Admin y empleado | Ver stock, registrar movimientos |
| `UsuarioController.php` | Solo admin | Gestión de usuarios |
| `ReporteController.php` | Admin y empleado | Generar reportes |
| `CategoriaController.php` | Solo admin | CRUD de categorías |

---

## 4. Clases y Funciones

### Clases

Una **clase** en PHP agrupa datos (propiedades) y comportamientos (métodos) relacionados. En DisneyStock se usan clases para los modelos y el controlador de autenticación.

```php
class Usuario
{
    private PDO $conn;          // propiedad privada

    public function __construct(PDO $db)  // constructor
    {
        $this->conn = $db;
    }

    public function obtenerPorUsuario(string $usuario): array|false
    {
        // método público que retorna array o false
        $stmt = $this->conn->prepare("SELECT ... FROM Usuario WHERE usuario = :u");
        $stmt->execute([':u' => $usuario]);
        return $stmt->fetch();
    }
}
```

**Modificadores de acceso:**
- `private` — solo accesible dentro de la misma clase (ej: `$conn`)
- `public` — accesible desde cualquier parte
- `protected` — accesible en la clase y sus hijas (no se usa en este proyecto)

### Funciones auxiliares

Fuera de las clases se usan funciones globales en los helpers y controladores:

```php
// helpers/auth.php — función reutilizable en todo el proyecto
function requireAuth(?string $rolRequerido = null): void
{
    if (!isset($_SESSION['usuario'])) {
        header("Location: /DisneyStock/views/usuarios/login.php");
        exit;
    }
}

// controllers/ProductoController.php — función auxiliar local
function procesarImagen(array $file, ?string $imagenActual = null): string|false|null
{
    if (empty($file['name'])) return null;
    // validar extensión, tamaño y guardar
}
```

---

## 5. Listas con foreach y for/else

### foreach — recorrer arrays de BD

El uso más frecuente es iterar los resultados de una consulta para mostrarlos en HTML:

```php
// En la vista productos.php
<?php foreach ($productos as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['nombre']) ?></td>
    <td>$<?= number_format($p['precio_venta'], 0, ',', '.') ?></td>
</tr>
<?php endforeach; ?>
```

### foreach con índice

```php
// Top productos en dashboard/admin.php
<?php foreach ($topProductos as $i => $p): ?>
<div>
    <span>#<?= $i + 1 ?></span>  <!-- índice del ranking -->
    <span><?= htmlspecialchars($p['nombre']) ?></span>
</div>
<?php endforeach; ?>
```

### Patrón if/else para tabla vacía

```php
<?php if (empty($productos)): ?>
    <p>No hay productos registrados.</p>
<?php else: ?>
    <table>
        <?php foreach ($productos as $p): ?>
            <tr>...</tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
```

### foreach dentro de una transacción (modelo Venta)

```php
// Procesar cada ítem del carrito dentro de una transacción
foreach ($items as $it) {
    $pid    = (int)$it['id_producto'];
    $cant   = (int)$it['cantidad'];
    $precio = (float)$it['precio_unitario'];

    // Verificar stock por cada ítem
    if ($prod['stock_actual'] < $cant) {
        throw new Exception("Stock insuficiente para: {$prod['nombre']}");
    }

    // Insertar detalle
    $this->conn->prepare("INSERT INTO Detalle_Venta ...")->execute([...]);

    // Descontar stock
    $this->conn->prepare("UPDATE Producto SET stock_actual = stock_actual - :cant ...")->execute([...]);
}
```

---

## 6. Procesos Algorítmicos — Anidadas, Ciclos, Funciones

### Función con lógica anidada: registrar movimiento de inventario

Este método en `models/Inventario.php` muestra múltiples niveles de lógica:

```php
public function registrar(int $id_producto, string $tipo, int $cantidad, ?string $descripcion, ?int $id_adm): array
{
    // 1. Leer el producto actual
    $stk = $this->conn->prepare("SELECT stock_actual, stock_minimo, nombre FROM Producto WHERE id_producto = :pid");
    $stk->execute([':pid' => $id_producto]);
    $prod = $stk->fetch();

    // 2. Validación: producto no existe
    if (!$prod) {
        return ['ok' => false, 'error' => 'Producto no encontrado.'];
    }

    // 3. Validación anidada: solo para salidas
    if ($tipo === 'salida' && $prod['stock_actual'] < $cantidad) {
        return ['ok' => false, 'error' => "Solo hay {$prod['stock_actual']} unidades disponibles."];
    }

    // 4. Insertar movimiento en BD
    $this->conn->prepare("INSERT INTO Movimiento_Inventario ...")->execute([...]);

    // 5. Condicional anidado para seleccionar el tipo de UPDATE
    if ($tipo === 'entrada') {
        $sql = "UPDATE Producto SET stock_actual = stock_actual + :cant WHERE id_producto = :pid";
    } elseif ($tipo === 'salida') {
        $sql = "UPDATE Producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid";
    } else { // ajuste
        $sql = "UPDATE Producto SET stock_actual = :cant WHERE id_producto = :pid";
    }
    $this->conn->prepare($sql)->execute([':cant' => $cantidad, ':pid' => $id_producto]);

    // 6. Leer stock actualizado para decidir sobre alertas
    $nuevo = $this->conn->prepare("SELECT stock_actual, stock_minimo FROM Producto WHERE id_producto = :pid");
    $nuevo->execute([':pid' => $id_producto]);
    $act = $nuevo->fetch();

    // 7. Lógica de alertas anidada
    if ($act['stock_minimo'] > 0 && $act['stock_actual'] <= $act['stock_minimo']) {
        // Crear alerta solo si no existe una activa
        $ya = $this->conn->prepare("SELECT COUNT(*) FROM Alerta WHERE id_producto=:pid AND estado='activa'");
        $ya->execute([':pid' => $id_producto]);
        if (!(int)$ya->fetchColumn()) {
            $this->conn->prepare("INSERT INTO Alerta ...")->execute([...]);
        }
    } elseif (in_array($tipo, ['entrada', 'ajuste'])) {
        // Resolver alertas activas si el stock se normalizó
        $this->conn->prepare("UPDATE Alerta SET estado='resuelta' ...")->execute([...]);
    }

    return ['ok' => true];
}
```

### Transacción con ciclo y manejo de excepciones (modelo Venta)

```php
public function crear(array $items, float $descuento, ?int $id_adm, ?int $id_emp): array
{
    // Ciclo 1: calcular subtotal recorriendo todos los ítems
    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
    }

    $this->conn->beginTransaction(); // inicio de transacción atómica
    try {
        // Insertar cabecera de venta
        $this->conn->prepare("INSERT INTO Venta ...")->execute([...]);
        $id_venta = (int)$this->conn->lastInsertId();

        // Ciclo 2: procesar cada ítem (verificar stock, insertar detalle, descontar)
        foreach ($items as $it) {
            // Verificación de stock (anidada dentro del ciclo)
            if ($prod['stock_actual'] < $cant) {
                throw new Exception("Stock insuficiente para: {$prod['nombre']}");
            }
            // Insertar detalle, descontar stock, registrar movimiento
        }

        // Crear factura
        $this->conn->prepare("INSERT INTO Factura ...")->execute([...]);
        $this->conn->commit();
        return ['ok' => true, 'factura' => $numFac, 'total' => $total];

    } catch (Exception $e) {
        $this->conn->rollBack(); // si algo falla, deshacer TODO
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
```

### switch para despachar acciones en controladores

```php
// ReporteController.php
switch ($tipo) {
    case 'ventas':
        $datos    = $modelVenta->reporteVentas($desde, $hasta);
        $columnas = ['Factura', 'Vendedor', 'Total', 'Estado', 'Fecha'];
        break;
    case 'inventario':
        $datos    = $modelProducto->reporteInventario();
        $columnas = ['ID', 'Nombre', 'Stock', 'Valor'];
        break;
    case 'stock_bajo':
        $datos    = $modelProducto->reporteStockBajo();
        break;
}
```

---

## 7. Módulos

DisneyStock está organizado en módulos funcionales. Cada módulo agrupa su controlador, modelo y vista.

### Módulos del sistema

| Módulo | Controlador | Modelo | Vista |
|---|---|---|---|
| **Dashboard** | `DashboardController.php` | Venta, Producto, Inventario | `views/dashboard/admin.php` |
| **Ventas** | `VentaController.php` | `Venta.php` | `views/dashboard/ventas.php` |
| **Productos** | `ProductoController.php` | `Producto.php`, `Categoria.php` | `views/dashboard/productos.php` |
| **Inventario** | `InventarioController.php` | `Inventario.php`, `Producto.php` | `views/dashboard/inventario.php` |
| **Reportes** | `ReporteController.php` | Venta, Producto, Inventario | `views/dashboard/reportes.php` |
| **Usuarios** | `UsuarioController.php` | `Usuario.php` | `views/dashboard/usuarios.php` |
| **Autenticación** | `AuthController.php` | `Usuario.php` | `views/usuarios/login.php` |
| **Categorías** | `CategoriaController.php` | `Categoria.php` | *(modal dentro de productos)* |

### Estructura de carpetas

```
DisneyStock/
├── config/
│   └── database.php          ← Conexión PDO a MySQL
├── controllers/
│   ├── AuthController.php
│   ├── DashboardController.php
│   ├── VentaController.php
│   ├── ProductoController.php
│   ├── InventarioController.php
│   ├── UsuarioController.php
│   ├── ReporteController.php
│   └── CategoriaController.php
├── models/
│   ├── Venta.php
│   ├── Producto.php
│   ├── Inventario.php
│   ├── Usuario.php
│   └── Categoria.php
├── views/
│   ├── Layouts/
│   │   ├── header.php        ← HTML inicial + CSS modo oscuro
│   │   ├── sidebar.php       ← Navegación + topbar
│   │   └── footer.php        ← Cierre HTML
│   ├── dashboard/
│   │   ├── admin.php
│   │   ├── ventas.php
│   │   ├── productos.php
│   │   ├── inventario.php
│   │   ├── reportes.php
│   │   ├── usuarios.php
│   │   ├── configuracion.php
│   │   └── informacion.php
│   ├── partials/
│   │   └── detalle_venta.php ← HTML parcial cargado por AJAX
│   └── usuarios/
│       ├── login.php
│       └── registre.php
├── helpers/
│   └── auth.php              ← requireAuth(), csrfField(), validateCsrf()
├── public/
│   ├── index.php             ← Landing page
│   ├── login.css
│   └── style.css
├── Styles/
│   ├── dashboard.css
│   └── sidebar.css
└── sql/
    └── DisneyStock.sql       ← Script de creación de BD
```

---

## 8. Relación Documentación — Frontend — Backend (Lógica de Negocio)

Esta sección explica cómo se conectan las tres capas del sistema.

### Diagrama de flujo completo

```
USUARIO (Navegador)
       │
       │  1. Ingresa URL o envía formulario
       ▼
CONTROLADOR (PHP)
       │  2. Verifica sesión y CSRF
       │  3. Lee parámetros GET/POST
       │  4. Llama al modelo
       ▼
MODELO (PHP + PDO)
       │  5. Ejecuta query SQL
       │  6. Retorna array de datos
       ▼
CONTROLADOR
       │  7. Pasa variables a la vista
       ▼
VISTA (HTML + PHP)
       │  8. Renderiza HTML con los datos
       ▼
USUARIO (Ve el resultado)
```

### Ejemplo completo: Registrar una venta

#### Frontend (vista `ventas.php`)
El usuario selecciona productos, cantidades y descuento. Al hacer clic en "Guardar Venta", el JavaScript serializa el carrito como JSON y envía el formulario:

```javascript
// ventas.php — JavaScript
items.push({ id_producto: sel.value, precio_unitario: precio, cantidad: cant });
document.getElementById('itemsJSON').value = JSON.stringify(items);
// El form POST lleva: accion=crear, items=[...JSON...], descuento=0
```

#### Backend — Controlador (`VentaController.php`)
Recibe el POST, valida el CSRF y delega al modelo:

```php
$items     = json_decode($_POST['items'] ?? '[]', true); // deserializar JSON
$descuento = (float)($_POST['descuento'] ?? 0);
$resultado = $model->crear($items, $descuento, $id_adm, $id_emp);
```

#### Backend — Modelo (`models/Venta.php`)
Ejecuta la transacción con la lógica de negocio:

```php
// Lógica de negocio: calcular totales, verificar stock, generar factura
$subtotal = 0;
foreach ($items as $it) {
    $subtotal += $it['precio_unitario'] * $it['cantidad'];
}
$total = max(0, $subtotal - $descuento);

// Validación de stock por ítem
if ($prod['stock_actual'] < $cant) {
    throw new Exception("Stock insuficiente");
}

// Actualizar BD: Venta + Detalle_Venta + Producto (stock) + Movimiento + Factura
$this->conn->commit();
return ['ok' => true, 'factura' => 'DS-000001', 'total' => 60000];
```

#### Frontend — Respuesta al usuario
El controlador redirige con una alerta en sesión que el header muestra con SweetAlert2:

```php
// VentaController.php
$_SESSION['alert'] = ['icon'=>'success', 'title'=>'Venta registrada', 'text'=>'Factura DS-000001'];
header("Location: /DisneyStock/controllers/VentaController.php");
```

```php
// views/Layouts/header.php — muestra la alerta al cargar la página
Swal.fire({
    icon:  <?= json_encode($_dsAlert['icon']) ?>,
    title: <?= json_encode($_dsAlert['title']) ?>,
    text:  <?= json_encode($_dsAlert['text']) ?>
});
```

### Separación de responsabilidades (principio clave)

| Capa | Responsabilidad | NO debe hacer |
|---|---|---|
| **Vista** | Mostrar datos, capturar inputs | Conectarse a BD, lógica de negocio |
| **Controlador** | Coordinar, validar inputs, redirigir | Queries SQL directas, generar HTML |
| **Modelo** | Queries SQL, lógica de negocio | Leer `$_POST`, generar HTML |

### Seguridad implementada

| Mecanismo | Dónde | Qué protege |
|---|---|---|
| `password_hash()` / `password_verify()` | `AuthController`, `UsuarioController` | Contraseñas en BD |
| `session_regenerate_id(true)` | `AuthController::login()` | Session fixation |
| Tokens CSRF | `helpers/auth.php` + todos los formularios | Peticiones falsas desde otros sitios |
| `htmlspecialchars()` | Todas las vistas | XSS (inyección de HTML) |
| `json_encode()` en alertas | `header.php`, `login.php` | XSS en JavaScript |
| PDO prepared statements | Todos los modelos | SQL injection |
| `requireAuth()` | Todos los controladores | Acceso sin sesión |
| Verificación de rol | Controladores que lo requieren | Escalada de privilegios |

---

*Documentación generada para el proyecto DisneyStock — v1.0 — 2026*
