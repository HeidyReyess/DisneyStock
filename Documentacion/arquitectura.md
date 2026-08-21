# Arquitectura del Sistema DisneyStock

**Versión:** 1.0.1  
**Última actualización:** Agosto 2026  
**Autor:** Heidy Johanna Reyes Quesada

---

## Tabla de contenido

1. [Patrón de arquitectura](#patrón-de-arquitectura)
2. [Estructura de directorios](#estructura-de-directorios)
3. [Flujo de peticiones HTTP](#flujo-de-peticiones-http)
4. [Sistema de autenticación](#sistema-de-autenticación)
5. [Roles y permisos](#roles-y-permisos)
6. [Convenciones del código](#convenciones-del-código)
7. [Dependencias críticas](#dependencias-críticas)
8. [Puntos críticos para evaluación](#puntos-críticos-para-evaluación)

---

## Patrón de arquitectura

DisneyStock implementa **MVC (Modelo-Vista-Controlador)** sin framework, con PHP 8.0+ y MySQL.

```
┌──────────────┐      ┌──────────────┐      ┌──────────────┐
│   VISTA      │ ◄──► │  CONTROLADOR │ ◄──► │    MODELO    │
│  (HTML/PHP)  │      │   (Lógica)   │      │    (BD)      │
└──────────────┘      └──────────────┘      └──────────────┘
```

### Responsabilidades por capa

| Capa | Responsabilidad | Ubicación |
|------|-----------------|-----------|
| **Vista** | HTML puro, recibe variables del controlador, NO accede a BD | `views/` |
| **Controlador** | Maneja peticiones, llama modelos, prepara datos, renderiza vistas | `controllers/` |
| **Modelo** | Ejecuta queries SQL, retorna arrays asociativos, NO hace echo ni HTML | `models/` |
| **Helper** | Funciones transversales reutilizables (auth, CSRF) | `helpers/` |
| **Config** | Conexión a BD, constantes del sistema | `config/` |

---

## Estructura de directorios

```
DisneyStock/
│
├── config/
│   ├── database.php              ← Conexión PDO singleton (host, puerto, BD)
│   └── database.example.php      ← Plantilla para entorno nuevo
│
├── controllers/
│   ├── AuthController.php        ← Login / Logout (acceso público)
│   ├── DashboardController.php   ← Panel principal (solo admin)
│   ├── ProductoController.php    ← CRUD productos
│   ├── InventarioController.php  ← Movimientos de stock
│   ├── VentaController.php       ← Crear ventas (ambos roles), anular (admin)
│   ├── UsuarioController.php     ← CRUD usuarios (solo admin)
│   ├── CategoriaController.php   ← Crear/eliminar categorías (solo admin)
│   ├── ReporteController.php     ← 4 tipos de reporte (ambos roles)
│   ├── ConfiguracionController.php ← Opciones del sistema (solo admin)
│   └── InformacionController.php   ← Info del negocio (ambos roles)
│
├── models/
│   ├── Usuario.php               ← Login, registro, CRUD usuarios
│   ├── Producto.php              ← CRUD productos + joins inventario
│   ├── Categoria.php             ← CRUD categorías
│   ├── Inventario.php            ← Movimientos, alertas de stock
│   └── Venta.php                 ← Transacciones de venta y anulación
│
├── views/
│   ├── Layouts/
│   │   ├── header.php            ← <head>, CSS, dark mode, SweetAlert
│   │   ├── sidebar.php           ← Navegación lateral + topbar
│   │   └── footer.php            ← Cierre de etiquetas HTML
│   ├── dashboard/
│   │   ├── admin.php             ← Dashboard principal (métricas y gráficas)
│   │   ├── productos.php         ← Tabla + modales CRUD
│   │   ├── inventario.php        ← Stock actual + historial de movimientos
│   │   ├── ventas.php            ← Tabla ventas + modal nueva venta
│   │   ├── usuarios.php          ← Gestión de usuarios
│   │   ├── reportes.php          ← Tabla dinámica según tipo de reporte
│   │   ├── configuracion.php     ← Opciones del sistema (solo HTML)
│   │   └── informacion.php       ← Info del negocio (solo HTML)
│   ├── partials/
│   │   └── detalle_venta.php     ← Fragmento HTML del detalle (usado por AJAX)
│   └── usuarios/
│       ├── login.php             ← Formulario de login (standalone)
│       └── registre.php          ← Formulario de registro (standalone, solo admin)
│
├── helpers/
│   └── auth.php                  ← requireAuth(), csrfToken(), validateCsrf(), csrfField()
│
├── public/
│   ├── index.php                 ← Landing page pública
│   ├── login.css                 ← Estilos del formulario de login
│   ├── registro.css              ← Estilos del formulario de registro
│   ├── style.css                 ← Estilos de la landing page
│   └── uploads/productos/        ← Imágenes de productos (JPG, PNG, WEBP, GIF — máx. 2MB)
│
├── Styles/
│   ├── dashboard.css             ← Estilos generales del dashboard
│   └── sidebar.css               ← Navegación, topbar, botón dark mode
│
├── sql/
│   ├── disney_stock_estructura.sql  ← SQL ACTIVO (usar siempre este)
│   └── DisneyStock.sql              ← SQL VIEJO — incompatible, NO usar
│
├── img/                          ← Logos y recursos gráficos
└── Documentacion/                ← Documentación técnica del proyecto
```

---

## Flujo de peticiones HTTP

### 1. Request GET — ver un módulo

```
Usuario → NombreController.php (GET)
                ↓
        Instancia modelo(s)
                ↓
        Ejecuta queries → retorna arrays
                ↓
        Asigna variables ($productos, $titulo, etc.)
                ↓
        require header.php → sidebar.php → vista.php → footer.php
                ↓
        Navegador renderiza HTML completo
```

**Ejemplo — `ProductoController.php?q=vestido&cat=2`:**
```php
$productos  = $modelProducto->obtenerTodos($buscar, $filtroCategoria);
$categorias = $modelCategoria->obtenerTodas();
$titulo     = "Productos";

require_once __DIR__ . '/../views/Layouts/header.php';
require_once __DIR__ . '/../views/Layouts/sidebar.php';
require_once __DIR__ . '/../views/dashboard/productos.php';
require_once __DIR__ . '/../views/Layouts/footer.php';
```

---

### 2. Request POST — crear/editar/eliminar

```
Usuario envía formulario (POST)
                ↓
        Controller recibe $_POST
                ↓
        validateCsrf()  ← verifica token de seguridad
                ↓
        Sanitiza y valida datos
                ↓
        Llama a Model::metodo()
                ↓
        $_SESSION['alert'] = [...]
                ↓
        header("Location: ...")  ← SIEMPRE redirige (patrón PRG)
        exit;
                ↓
        Nuevo request GET → muestra alert via SweetAlert
```

> **Patrón PRG (Post-Redirect-Get):** todos los POST terminan en redirección para evitar reenvío de formulario al recargar la página.

---

### 3. Request AJAX — detalle de venta

```
JS fetch('/DisneyStock/controllers/VentaController.php?accion=detalle&id=5')
                ↓
        VentaController detecta accion=detalle
                ↓
        Venta::obtenerDetalle(5) + Venta::obtenerItems(5)
                ↓
        require 'views/partials/detalle_venta.php'  ← solo HTML, sin layout
                ↓
        exit;  ← detiene la ejecución aquí
                ↓
JS recibe el HTML como texto y lo inyecta en el modal
```

---

## Sistema de autenticación

### Archivos involucrados

| Archivo | Función |
|---------|---------|
| `views/usuarios/login.php` | Formulario con token CSRF embebido |
| `controllers/AuthController.php` | Verifica credenciales, crea sesión, maneja cookie |
| `helpers/auth.php` | `requireAuth()`, `validateCsrf()`, `csrfField()` |
| `models/Usuario.php` | `obtenerPorUsuario()` con JOIN a admin/empleado |

### Flujo de login paso a paso

```
1. Usuario llena login.php (usuario + contraseña + token CSRF)
                ↓
2. POST → AuthController.php
                ↓
3. validateCsrf()  ← falla → 403, no continúa
                ↓
4. Usuario::obtenerPorUsuario($usuario)
   → SELECT con JOIN a administrador y empleado
   → retorna rol, id, hash de contraseña
                ↓
5. password_verify($password, $user['password_hash'])
   → falla → alert de error y redirige al login
                ↓
6. session_regenerate_id(true)  ← previene session fixation
                ↓
7. $_SESSION['usuario'] = [id, nombre, rol, ...]
                ↓
8. Redirige según rol:
   admin    → DashboardController.php
   empleado → VentaController.php
```

### Protección de rutas con `requireAuth()`

```php
// En TODOS los controllers (excepto AuthController):
session_start();
require_once __DIR__ . '/../helpers/auth.php';

requireAuth();             // cualquier usuario autenticado
requireAuth('admin');      // solo admin → redirige al Dashboard si no lo es
requireAuth('admin', '/DisneyStock/controllers/DashboardController.php');
```

### Protección CSRF

**En la vista (dentro del `<form>`):**
```php
<form method="POST" action="...">
    <?php csrfField(); ?>
    <!-- genera: <input type="hidden" name="csrf_token" value="abc123..."> -->
</form>
```

**En el controller (bloque POST):**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();  // compara con hash_equals(), muere con 403 si no coincide
    // ... procesar formulario
}
```

---

## Roles y permisos

### Tabla de acceso por módulo

| Módulo | Admin | Empleado | Sin sesión |
|--------|-------|----------|------------|
| Landing page / Login | ✅ | ✅ | ✅ |
| Dashboard (métricas) | ✅ | ❌ redirige a Ventas | ❌ |
| Ventas — ver y crear | ✅ | ✅ | ❌ |
| Ventas — anular | ✅ | ❌ | ❌ |
| Productos — ver | ✅ | ✅ | ❌ |
| Productos — crear/editar/eliminar | ✅ | ❌ | ❌ |
| Categorías — crear/eliminar | ✅ | ❌ | ❌ |
| Inventario — ver | ✅ | ✅ | ❌ |
| Inventario — registrar movimiento | ✅ | ❌ | ❌ |
| Reportes | ✅ | ✅ | ❌ |
| Usuarios | ✅ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ |
| Información | ✅ | ✅ | ❌ |

### Verificación de rol en controller

```php
$rol = $_SESSION['usuario']['rol'];

// Bloquear escritura a empleados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rol !== 'admin') {
    header("Location: /DisneyStock/controllers/ProductoController.php");
    exit;
}
```

### Verificación de rol en vista

```php
<?php if ($rol === 'admin'): ?>
    <button onclick="openModal('nuevo')">Nuevo Producto</button>
    <a onclick="confirmarEliminar(<?= $p['id_producto'] ?>)">Eliminar</a>
<?php endif; ?>
```

---

## Convenciones del código

### Nomenclatura

| Elemento | Convención | Ejemplo |
|----------|------------|---------|
| Clases | PascalCase | `Usuario`, `Venta`, `AuthController` |
| Métodos | camelCase | `obtenerTodos()`, `crearRegistro()` |
| Variables | snake_case | `$id_producto`, `$fecha_venta` |
| Constantes | SCREAMING_SNAKE | `URL_LOGIN`, `URL_DASHBOARD` |
| Tablas BD | minúsculas | `usuario`, `venta`, `movimiento_inventario` |
| Columnas BD | snake_case | `id_usuario`, `fecha_venta`, `stock_minimo` |

### Queries SQL — reglas

```php
// ✅ CORRECTO — siempre prepared statements con parámetros nombrados
$stmt = $this->conn->prepare("SELECT * FROM producto WHERE id_producto = :id");
$stmt->execute([':id' => $id]);
return $stmt->fetch(); // retorna array asociativo

// ❌ INCORRECTO — vulnerabilidad de inyección SQL
$sql = "SELECT * FROM producto WHERE id_producto = $id";
```

### Manejo de errores

```php
// Transacciones (ventas, anulaciones)
try {
    $this->conn->beginTransaction();
    // ... operaciones
    $this->conn->commit();
    return ['ok' => true, 'factura' => $numFac];
} catch (Exception $e) {
    $this->conn->rollBack();
    error_log("Venta::crear — " . $e->getMessage());
    return ['ok' => false, 'error' => $e->getMessage()];
}

// Alerts en sesión (se muestran con SweetAlert2)
$_SESSION['alert'] = [
    'icon'  => 'success',  // success | error | warning | info
    'title' => 'Título',
    'text'  => 'Descripción del resultado'
];
```

---

## Dependencias críticas

### Backend

| Dependencia | Versión mínima | Propósito |
|-------------|----------------|-----------|
| PHP | 8.0+ | Lenguaje del servidor |
| MySQL / MariaDB | 5.7+ / 10.4+ | Base de datos |
| PDO + pdo_mysql | Incluido en PHP | Driver de conexión |
| Apache | 2.4+ | Servidor web (Laragon) |

### Frontend (CDN — sin npm ni composer)

| Librería | Versión | Propósito |
|----------|---------|-----------|
| Tailwind CSS | 3.x | Framework CSS utility-first |
| Font Awesome | 6.0 | Iconos vectoriales |
| SweetAlert2 | 11.x | Modales y notificaciones |

> El proyecto **no usa** composer, npm, webpack ni ningún gestor de paquetes. Todo el frontend se carga por CDN directamente en `header.php`.

---

## Puntos críticos para evaluación

Si el tutor modifica el código, estos puntos **deben mantenerse** para que el sistema funcione:

1. **`contrasenia`** con **i** en la tabla `usuario` y en todos los queries — cambiar rompe el login
2. **`estado` = 'activo' / 'inactivo'** en `usuario` — no es un TINYINT, es VARCHAR
3. **Stock en tabla `inventario`** — no en `producto` (que no tiene esas columnas)
4. **`id_usuario`** en `venta` y `movimiento_inventario` — no `id_empleado` ni `id_administrador`
5. **`requireAuth()`** al inicio de cada controller — sin esto cualquiera accede sin login
6. **`validateCsrf()`** en cada bloque POST — sin esto hay vulnerabilidad CSRF
7. **`password_hash()` / `password_verify()`** — nunca contraseñas en texto plano
8. **`session_regenerate_id(true)`** en el login — previene session fixation
9. **Transacciones** en ventas y anulaciones — sin `rollBack()` quedan datos corruptos
10. **Patrón PRG** — todos los POST terminan en `header("Location: ...")` + `exit`
11. **SQL activo**: `disney_stock_estructura.sql` — nunca recrear con `DisneyStock.sql`
12. **Tablas en minúsculas** — `usuario`, `venta`, `producto` (sensible a mayúsculas en Linux)

---

**Autor:** Heidy Johanna Reyes Quesada  
**Institución:** SENA  
**Proyecto:** DisneyStock — Sistema de Gestión de Inventario  
**Fecha:** Agosto 2026
