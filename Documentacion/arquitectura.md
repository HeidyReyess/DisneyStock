# Arquitectura del Sistema DisneyStock

**Versión:** 1.0.1  
**Última actualización:** Agosto 2026  
**Autor:** Heidy Johanna Reyes Quesada

---

## 📋 Tabla de contenido

1. [Patrón de arquitectura](#patrón-de-arquitectura)
2. [Estructura de directorios](#estructura-de-directorios)
3. [Flujo de peticiones HTTP](#flujo-de-peticiones-http)
4. [Sistema de autenticación](#sistema-de-autenticación)
5. [Roles y permisos](#roles-y-permisos)
6. [Convenciones del código](#convenciones-del-código)
7. [Dependencias críticas](#dependencias-críticas)

---

## Patrón de arquitectura

DisneyStock implementa **MVC (Modelo-Vista-Controlador)** sin framework, con PHP 8.0+ y MySQL.

```
┌──────────────┐      ┌──────────────┐      ┌──────────────┐
│   VISTA      │ ◄──► │  CONTROLADOR │ ◄──► │    MODELO    │
│  (HTML/PHP)  │      │    (Lógica)  │      │     (BD)     │
└──────────────┘      └──────────────┘      └──────────────┘
```

### Responsabilidades por capa

| Capa | Responsabilidad | Ubicación |
|------|-----------------|-----------|
| **Vista** | HTML puro, recibe datos del controlador, NO accede a BD | `views/` |
| **Controlador** | Maneja peticiones, llama modelos, prepara datos, renderiza vistas | `controllers/` |
| **Modelo** | Ejecuta queries SQL, retorna arrays asociativos, NO hace echo ni HTML | `models/` |
| **Helper** | Funciones transversales (auth, CSRF) | `helpers/` |
| **Config** | Configuración de BD y constantes | `config/` |

---

## Estructura de directorios

```
DisneyStock/
│
├── config/
│   ├── database.php              ← Conexión PDO singleton
│   └── database.example.php      ← Plantilla para entorno nuevo
│
├── controllers/
│   ├── AuthController.php        ← Login/Logout (público)
│   ├── DashboardController.php   ← Panel principal (solo admin)
│   ├── ProductoController.php    ← CRUD productos (admin escribe, todos leen)
│   ├── InventarioController.php  ← Movimientos stock (admin escribe, todos leen)
│   ├── VentaController.php       ← Crear ventas (ambos), anular (solo admin)
│   ├── UsuarioController.php     ← CRUD usuarios (solo admin)
│   ├── CategoriaController.php   ← Crear/eliminar categorías (solo admin)
│   ├── ReporteController.php     ← 4 reportes (ambos roles)
│   ├── ConfiguracionController.php    ← Opciones del sistema (solo admin)
│   └── InformacionController.php      ← Info del negocio (ambos roles)
│
├── models/
│   ├── Usuario.php               ← Login, registro, CRUD usuarios
│   ├── Producto.php              ← CRUD productos, joins con inventario
│   ├── Categoria.php             ← CRUD categorías
│   ├── Inventario.php            ← Movimientos, alertas de stock
│   └── Venta.php                 ← Transacciones de venta, anulación
│
├── views/
│   ├── Layouts/
│   │   ├── header.php            ← <head>, CSS, dark mode, SweetAlert
│   │   ├── sidebar.php           ← Navegación lateral + topbar
│   │   └── footer.php            ← Cierre de tags
│   ├── dashboard/
│   │   ├── admin.php             ← Dashboard principal (métricas)
│   │   ├── productos.php         ← Tabla + modales CRUD
│   │   ├── inventario.php        ← Stock + movimientos
│   │   ├── ventas.php            ← Tabla ventas + modal nueva venta
│   │   ├── usuarios.php          ← Gestión usuarios
│   │   ├── reportes.php          ← Tabla dinámica según tipo
│   │   ├── configuracion.php     ← Opciones del sistema
│   │   └── informacion.php       ← Info del negocio
│   ├── partials/
│   │   └── detalle_venta.php     ← HTML de detalle venta (AJAX)
│   └── usuarios/
│       ├── login.php             ← Formulario login (standalone)
│       └── registre.php          ← Formulario registro (standalone, solo admin)
│
├── helpers/
│   └── auth.php                  ← requireAuth(), csrfToken(), validateCsrf()
│
├── public/
│   ├── index.php                 ← Landing page pública
│   ├── login.css                 ← Estilos login
│   ├── registro.css              ← Estilos registro
│   ├── style.css                 ← Estilos landing
│   └── uploads/productos/        ← (reservado para futuras imágenes)
│
├── Styles/
│   ├── dashboard.css             ← Estilos generales del dashboard
│   └── sidebar.css               ← Navegación + topbar + dark mode
│
├── sql/
│   ├── disney_stock_estructura.sql   ← SQL ACTIVO (usar este)
│   └── DisneyStock.sql               ← SQL VIEJO (no usar, deprecated)
│
├── img/                          ← Logos y recursos gráficos
├── Documentacion/                ← Docs técnicas y arquitectura
└── .gitignore
```

---

## Flujo de peticiones HTTP

### 1. Request GET típico (ver módulo)

```
Usuario → Controller GET
           ↓
    Llama a Model(s)
           ↓
    Prepara datos ($productos, $categorias, etc.)
           ↓
    Incluye header.php → sidebar.php → vista.php → footer.php
           ↓
    Navegador renderiza HTML completo
```

**Ejemplo:** `ProductoController.php?q=vestido&cat=2`
```php
// Controller prepara datos
$productos  = $modelProducto->obtenerTodos($buscar, $filtroCategoria);
$categorias = $modelCategoria->obtenerTodas();
$titulo     = "Productos";

// Renderiza
require_once __DIR__ . '/../views/Layouts/header.php';    // <html><head>...
require_once __DIR__ . '/../views/Layouts/sidebar.php';   // <aside>...
require_once __DIR__ . '/../views/dashboard/productos.php'; // <tabla>
require_once __DIR__ . '/../views/Layouts/footer.php';    // </body></html>
```

### 2. Request POST típico (crear/editar)

```
Usuario envía form POST → Controller
                            ↓
                   validateCsrf() ← verifica token
                            ↓
                   Sanitiza $_POST
                            ↓
                   Llama Model::crear() o actualizar()
                            ↓
                   Guarda alert en $_SESSION
                            ↓
                   header("Location: ...") → REDIRECCIONA
                            ↓
                   Request GET nuevo (patrón PRG)
```

**Patrón PRG (Post-Redirect-Get):**  
Todos los POST terminan en `header("Location: ...")` para evitar reenvío de formulario al recargar.

### 3. Request AJAX (detalle de venta)

```
JS fetch() → VentaController.php?accion=detalle&id=5
                ↓
         Model::obtenerDetalle(5)
                ↓
         require 'partials/detalle_venta.php' (solo HTML, sin layout)
                ↓
         echo HTML fragmento
                ↓
JS recibe texto HTML y lo inyecta en modal
```

---

## Sistema de autenticación

### Archivos involucrados

| Archivo | Función |
|---------|---------|
| `views/usuarios/login.php` | Formulario con CSRF token |
| `controllers/AuthController.php` | Verifica credenciales, crea sesión |
| `helpers/auth.php` | `requireAuth()`, `validateCsrf()` |
| `models/Usuario.php` | `obtenerPorUsuario()` con JOIN a admin/empleado |

### Flujo de login

```
1. Usuario completa login.php
   ↓
2. POST a AuthController.php
   ↓
3. validateCsrf() ← verifica token
   ↓
4. Usuario::obtenerPorUsuario($usuario)
   ↓
5. password_verify($password, $hash)
   ↓
6. session_regenerate_id(true) ← previene session fixation
   ↓
7. $_SESSION['usuario'] = [id, nombre, rol, ...]
   ↓
8. Redirige según rol:
   - admin    → DashboardController.php
   - empleado → VentaController.php
```

### Protección de rutas

**Todos los controllers (excepto AuthController) usan:**

```php
session_start();
require_once __DIR__ . '/../helpers/auth.php';

// Verificar sesión activa + rol opcional
requireAuth('admin', '/DisneyStock/controllers/DashboardController.php');
```

**Parámetros de `requireAuth()`:**
- `requireAuth()` → cualquier usuario autenticado
- `requireAuth('admin')` → solo admin, redirige a Dashboard si no lo es
- `requireAuth('empleado', '/ruta')` → solo empleado, redirige a /ruta

### Protección CSRF

**En formularios (vista):**
```php
<form method="POST" action="...">
    <?php csrfField(); ?>  ← genera <input type="hidden" name="csrf_token">
    ...
</form>
```

**En controller (POST):**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();  ← compara token con hash_equals(), muere si no coincide
    // ... procesar formulario
}
```

---

## Roles y permisos

### Tabla de acceso por módulo

| Módulo | Admin | Empleado | Público |
|--------|-------|----------|---------|
| **Login/Logout** | ✅ | ✅ | ✅ |
| **Landing page** | ✅ | ✅ | ✅ |
| **Dashboard** | ✅ | ❌ (redirige a Ventas) | ❌ |
| **Ventas** (crear) | ✅ | ✅ | ❌ |
| **Ventas** (anular) | ✅ | ❌ | ❌ |
| **Productos** (ver) | ✅ | ✅ | ❌ |
| **Productos** (crear/editar/eliminar) | ✅ | ❌ | ❌ |
| **Categorías** (crear/eliminar) | ✅ | ❌ | ❌ |
| **Inventario** (ver) | ✅ | ✅ | ❌ |
| **Inventario** (movimientos) | ✅ | ❌ | ❌ |
| **Reportes** | ✅ | ✅ | ❌ |
| **Usuarios** | ✅ | ❌ | ❌ |
| **Configuración** | ✅ | ❌ | ❌ |
| **Información** | ✅ | ✅ | ❌ |

### Verificación de rol en controller

```php
$rol = $_SESSION['usuario']['rol'];

// Bloquear escritura a empleados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rol !== 'admin') {
    header("Location: /DisneyStock/controllers/ProductoController.php");
    exit;
}

// Mostrar/ocultar botones en vista
<?php if ($rol === 'admin'): ?>
    <button onclick="openModal('nuevo')">Crear Producto</button>
<?php endif; ?>
```

---

## Convenciones del código

### Nomenclatura

| Elemento | Convención | Ejemplo |
|----------|------------|---------|
| **Clases** | PascalCase | `Usuario`, `Venta`, `AuthController` |
| **Métodos públicos** | camelCase | `obtenerTodos()`, `crearRegistro()` |
| **Variables** | snake_case | `$id_producto`, `$fecha_venta` |
| **Constantes** | SCREAMING_SNAKE | `URL_LOGIN`, `STOCK_MINIMO` |
| **Tablas BD** | minúsculas | `usuario`, `venta`, `movimiento_inventario` |
| **Columnas BD** | snake_case | `id_usuario`, `fecha_venta`, `stock_minimo` |

### Queries SQL

**✅ Siempre usar:**
- Prepared statements con placeholders nombrados (`:param`)
- `PDO::FETCH_ASSOC` para retornar arrays asociativos
- Transacciones para operaciones multi-tabla

**❌ Nunca usar:**
- Concatenación directa de variables en queries
- `mysql_*` functions (deprecated desde PHP 5.5)
- `PDO::FETCH_OBJ` (el proyecto usa arrays)

```php
// ✅ CORRECTO
$stmt = $this->conn->prepare("SELECT * FROM producto WHERE id_producto = :id");
$stmt->execute([':id' => $id]);
return $stmt->fetch(); // array asociativo

// ❌ INCORRECTO
$sql = "SELECT * FROM producto WHERE id_producto = $id"; // inyección SQL
$result = mysql_query($sql); // función deprecated
```

### Manejo de errores

**Base de datos:**
```php
try {
    $this->conn->beginTransaction();
    // ... operaciones
    $this->conn->commit();
    return ['ok' => true];
} catch (Exception $e) {
    $this->conn->rollBack();
    error_log("Modelo::metodo — " . $e->getMessage()); // log interno
    return ['ok' => false, 'error' => 'Mensaje amigable para usuario'];
}
```

**Alerts en sesión:**
```php
$_SESSION['alert'] = [
    'icon'  => 'success' | 'error' | 'warning' | 'info',
    'title' => 'Título corto',
    'text'  => 'Mensaje descriptivo'
];
```

---

## Dependencias críticas

### Backend

| Dependencia | Versión | Propósito |
|-------------|---------|-----------|
| **PHP** | 8.0+ | Lenguaje backend |
| **MySQL** | 5.7+ / MariaDB 10.4+ | Base de datos |
| **PDO** | ext-pdo_mysql | Driver de conexión |
| **Apache/Nginx** | 2.4+ | Servidor web con mod_rewrite |

### Frontend (CDN)

| Librería | Versión | Propósito |
|----------|---------|-----------|
| **Tailwind CSS** | 3.x (CDN) | Framework CSS utility-first |
| **Font Awesome** | 6.0 (CDN) | Iconos vectoriales |
| **SweetAlert2** | 11.x (CDN) | Modales y alerts elegantes |

**⚠️ IMPORTANTE:** El proyecto NO usa npm/composer. Todas las librerías frontend se cargan por CDN.

### Configuración de servidor

**Apache `.htaccess` (debe existir en `/public/`):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

**PHP `php.ini` (requerimientos mínimos):**
```ini
session.cookie_httponly = 1
session.cookie_samesite = "Lax"
display_errors = Off  ; producción
error_reporting = E_ALL
max_execution_time = 120
upload_max_filesize = 10M
post_max_size = 12M
```

---

## Diagrama de flujo completo

```
┌─────────────────────────────────────────────────────────┐
│                    USUARIO FINAL                         │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
          ┌───────────────────────────────┐
          │   login.php (public)          │
          └───────────────────────────────┘
                          │
                          ▼ POST + CSRF
          ┌───────────────────────────────┐
          │   AuthController.php          │
          │   - validateCsrf()            │
          │   - Usuario::obtenerPorUsuario│
          │   - password_verify()         │
          │   - $_SESSION['usuario']      │
          └───────────────────────────────┘
                          │
            ┌─────────────┴─────────────┐
            ▼ admin                      ▼ empleado
┌───────────────────────┐    ┌──────────────────────┐
│ DashboardController   │    │  VentaController     │
│ (métricas, tarjetas)  │    │  (registrar ventas)  │
└───────────────────────┘    └──────────────────────┘
            │                            │
            ▼                            ▼
  ┌─────────────────┐          ┌─────────────────┐
  │  sidebar.php    │          │  sidebar.php    │
  │  (9 módulos)    │          │  (4 módulos)    │
  └─────────────────┘          └─────────────────┘
            │                            │
            └────────────┬───────────────┘
                         ▼
         ┌───────────────────────────────┐
         │   Modelos (BD)                │
         │   - Usuario                   │
         │   - Producto ↔ Inventario     │
         │   - Venta ↔ Detalle ↔ Factura │
         │   - Categoria                 │
         └───────────────────────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │   MySQL: disney_stock         │
         │   12 tablas normalizadas      │
         └───────────────────────────────┘
```

---

## ⚠️ Puntos críticos para evaluación

Si tu tutor modifica el código, estos puntos DEBEN mantenerse:

1. **CSRF obligatorio** en todos los POST — `csrfField()` en form + `validateCsrf()` en controller
2. **Prepared statements** — nunca concatenar variables en SQL
3. **Transacciones** en ventas y anulaciones — `beginTransaction()` + `commit()` / `rollBack()`
4. **Verificación de rol** antes de escritura — `if ($rol !== 'admin') exit;`
5. **Patrón PRG** — POST siempre termina en `header("Location: ...")`, no en `echo`
6. **session_regenerate_id(true)** en login — previene session fixation
7. **password_hash() / password_verify()** — nunca guardar contraseñas en texto plano
8. **Nombres de tablas en minúsculas** — `usuario`, `venta`, no `Usuario`, `Venta`
9. **Campo `id_usuario` unificado** en ventas y movimientos — no `id_administrador` + `id_empleado` separados
10. **Stock en tabla `inventario`** — no en `producto`

---

**Autor:** Heidy Johanna Reyes Quesada  
**Institución:** SENA  
**Proyecto:** DisneyStock — Sistema de Gestión de Inventario  
**Fecha:** Agosto 2026
