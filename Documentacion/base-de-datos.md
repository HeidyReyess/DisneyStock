# Base de Datos — DisneyStock

**BD activa:** `disney_stock`  
**Archivo SQL a usar:** `sql/disney_stock_estructura.sql`  
**Archivo SQL deprecado:** `sql/DisneyStock.sql` — NO usar, esquema viejo incompatible  
**Motor:** MariaDB 10.4+ / MySQL 5.7+  
**Charset:** utf8mb4 / utf8mb4_general_ci  
**Puerto Laragon:** 3320 (configurado en `config/database.php`)

---

## Tabla de contenido

1. [Diagrama de relaciones](#diagrama-de-relaciones)
2. [Tablas y columnas](#tablas-y-columnas)
3. [Claves foráneas](#claves-foráneas)
4. [Datos iniciales](#datos-iniciales)
5. [Queries clave del sistema](#queries-clave-del-sistema)
6. [Diferencias con el esquema viejo](#diferencias-con-el-esquema-viejo)

---

## Diagrama de relaciones

```
usuario (tabla raíz de autenticación)
┌─────────────────────────────────────┐
│ id_usuario (PK)                     │
│ nombre, usuario, contrasenia        │
│ estado, fecha_registro              │
│ requiere_cambio_contrasenia         │
└──────────────┬──────────────────────┘
               │ 1
     ┌─────────┴──────────┐
     ▼ 0..1               ▼ 0..1
administrador           empleado
┌─────────────────┐   ┌─────────────────┐
│ id_admin (PK)   │   │ id_empleado(PK) │
│ id_usuario FK   │   │ id_usuario FK   │
└─────────────────┘   └─────────────────┘


categoria              producto
┌──────────────────┐  ┌────────────────────────────────┐
│ id_categoria(PK) │◄─│ id_producto (PK)               │
│ nombre_categoria │  │ nombre, precio_venta           │
│ descripcion      │  │ precio_compra, fecha_ingreso    │
└──────────────────┘  │ estado, proveedor               │
                      │ id_categoria FK                 │
                      └──────────┬─────────────────────┘
                                 │ 1:1       │ 1:N      │ 1:N
                                 ▼           ▼          ▼
                           inventario     alerta   detalle_venta
                      ┌───────────────┐ ┌────────┐ ┌───────────────┐
                      │ id_inventario │ │ ...    │ │ id_detalle    │
                      │ cantidad_stock│ │ estado │ │ cantidad      │
                      │ stock_minimo  │ └────────┘ │ precio_unit.  │
                      │ fecha_actual. │            │ subtotal      │
                      └───────────────┘            │ id_venta FK   │
                                                   └───────┬───────┘
                                                           │ N:1
                                                           ▼
venta                                         ┌──────────────────────┐
┌──────────────────────┐                      │ venta (PK)           │
│ id_venta (PK)        │◄─────────────────────│ fecha_venta (DATE)   │
│ subtotal, impuesto   │                      │ subtotal, impuesto   │
│ descuento, total     │                      │ descuento, total     │
│ estado               │                      │ estado               │
│ id_usuario FK        │                      │ id_usuario FK        │
└──────┬───────────────┘                      └──────────────────────┘
       │ 1:1                    │ 1:N
       ▼                        ▼
   factura             movimiento_inventario
┌───────────────────┐  ┌──────────────────────────┐
│ id_factura (PK)   │  │ id_movimiento (PK)        │
│ numero (DS-000001)│  │ tipo_movimiento           │
│ fecha_emision     │  │ cantidad, fecha           │
│ total, formato    │  │ descripcion, proveedor    │
│ id_venta FK       │  │ id_producto FK            │
└───────────────────┘  │ id_usuario FK             │
                       │ id_venta FK (nullable)    │
                       └──────────────────────────┘
```

---

## Tablas y columnas

### `usuario`
Tabla base de autenticación. Todos los usuarios del sistema.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_usuario` | INT(11) | NO | PK autoincremental |
| `nombre` | VARCHAR(100) | SÍ | Nombre completo |
| `usuario` | VARCHAR(50) | SÍ | Username único para login |
| `contrasenia` | VARCHAR(100) | SÍ | Hash bcrypt — `password_hash()` |
| `estado` | VARCHAR(20) | SÍ | `'activo'` o `'inactivo'` |
| `fecha_registro` | DATE | SÍ | Fecha de creación de la cuenta |
| `requiere_cambio_contrasenia` | TINYINT(1) | SÍ | `1` = debe cambiar contraseña al entrar |

> ⚠️ El campo es `contrasenia` (con **i**). Si se cambia a `contrasena`, el login deja de funcionar.

---

### `administrador`
Extensión de `usuario` para el rol admin. Relación 1:1.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_administrador` | INT(11) | NO | PK autoincremental |
| `id_usuario` | INT(11) | SÍ | FK → `usuario.id_usuario` |

---

### `empleado`
Extensión de `usuario` para el rol empleado. Relación 1:1.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_empleado` | INT(11) | NO | PK autoincremental |
| `id_usuario` | INT(11) | SÍ | FK → `usuario.id_usuario` |

> Un usuario tiene registro en `administrador` **o** en `empleado`, nunca en ambas.

---

### `categoria`
Clasificación de productos.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_categoria` | INT(11) | NO | PK autoincremental |
| `nombre_categoria` | VARCHAR(100) | SÍ | Nombre único de la categoría |
| `descripcion` | VARCHAR(200) | SÍ | Descripción opcional |

---

### `producto`
Catálogo de productos. **No contiene stock** — ese dato está en `inventario`.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_producto` | INT(11) | NO | PK autoincremental |
| `nombre` | VARCHAR(100) | SÍ | Nombre del producto |
| `precio_venta` | DECIMAL(10,2) | SÍ | Precio al público |
| `precio_compra` | DECIMAL(10,2) | SÍ | Precio de costo |
| `fecha_ingreso` | DATE | SÍ | Fecha de ingreso al catálogo |
| `estado` | VARCHAR(20) | SÍ | `'activo'` o `'inactivo'` |
| `proveedor` | VARCHAR(100) | SÍ | Nombre del proveedor (opcional) |
| `imagen` | VARCHAR(255) | SÍ | Nombre del archivo en `public/uploads/productos/` (opcional) |
| `id_categoria` | INT(11) | SÍ | FK → `categoria.id_categoria` |

> ⚠️ Esta tabla **NO tiene** `stock_actual` ni `stock_minimo`. Están en `inventario`.

---

### `inventario`
Stock de cada producto. Relación 1:1 con `producto`.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_inventario` | INT(11) | NO | PK autoincremental |
| `cantidad_stock` | INT(11) | SÍ | Unidades disponibles actualmente |
| `stock_minimo` | INT(11) | SÍ | Umbral para generar alerta de stock bajo |
| `fecha_actualizacion` | DATE | SÍ | Última vez que cambió el stock |
| `id_producto` | INT(11) | SÍ | FK → `producto.id_producto` |

> Cuando `cantidad_stock <= stock_minimo` (y `stock_minimo > 0`), el sistema crea una alerta automáticamente.

---

### `alerta`
Alertas automáticas de stock bajo. Se crean y resuelven sin intervención manual.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_alerta` | INT(11) | NO | PK autoincremental |
| `tipo_alerta` | VARCHAR(50) | SÍ | Siempre `'stock_bajo'` actualmente |
| `mensaje` | VARCHAR(200) | SÍ | Texto descriptivo |
| `fecha_alerta` | DATE | SÍ | Fecha de creación |
| `fecha_resolucion` | DATE | SÍ | Fecha en que se resolvió (NULL si activa) |
| `estado` | VARCHAR(20) | SÍ | `'activa'` o `'resuelta'` |
| `id_producto` | INT(11) | SÍ | FK → `producto.id_producto` |

**Ciclo automático:**
- Se **crea** al vender o registrar salida cuando `cantidad_stock <= stock_minimo`
- Se **resuelve** automáticamente cuando se registra una entrada o ajuste que sube el stock

---

### `venta`
Cabecera de cada transacción.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_venta` | INT(11) | NO | PK autoincremental |
| `fecha_venta` | DATE | SÍ | Fecha de la venta (DATE, sin hora) |
| `subtotal` | DECIMAL(10,2) | SÍ | Suma de items antes del descuento |
| `impuesto` | DECIMAL(10,2) | SÍ | Impuesto (actualmente siempre 0) |
| `descuento` | DECIMAL(10,2) | SÍ | Descuento global aplicado |
| `total` | DECIMAL(10,2) | SÍ | `subtotal - descuento` (mínimo 0) |
| `estado` | VARCHAR(20) | SÍ | `'completada'` o `'anulada'` |
| `id_usuario` | INT(11) | SÍ | FK → `usuario.id_usuario` (el vendedor) |

> ⚠️ El campo es `id_usuario`, no `id_empleado` ni `id_administrador`. Unifica ambos roles.

---

### `detalle_venta`
Líneas individuales de cada venta (un registro por producto vendido).

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_detalle` | INT(11) | NO | PK autoincremental |
| `cantidad` | INT(11) | SÍ | Unidades vendidas |
| `precio_unitario` | DECIMAL(10,2) | SÍ | Precio al momento de la venta |
| `subtotal` | DECIMAL(10,2) | SÍ | `cantidad * precio_unitario` |
| `id_venta` | INT(11) | SÍ | FK → `venta.id_venta` |
| `id_producto` | INT(11) | SÍ | FK → `producto.id_producto` |

---

### `factura`
Número de factura vinculado a cada venta. Relación 1:1 con `venta`.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_factura` | INT(11) | NO | PK autoincremental |
| `numero` | VARCHAR(20) | SÍ | Ej: `DS-000001` |
| `fecha_emision` | DATE | SÍ | Igual a `venta.fecha_venta` |
| `total` | DECIMAL(10,2) | SÍ | Igual a `venta.total` |
| `formato` | VARCHAR(50) | SÍ | NULL actualmente (reservado) |
| `id_venta` | INT(11) | SÍ | FK → `venta.id_venta` |

**Formato del número de factura:**
```php
'DS-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT)
// id_venta = 1  → DS-000001
// id_venta = 42 → DS-000042
```

---

### `movimiento_inventario`
Historial completo de cambios de stock.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_movimiento` | INT(11) | NO | PK autoincremental |
| `tipo_movimiento` | VARCHAR(50) | SÍ | `'entrada'`, `'salida'` o `'ajuste'` |
| `cantidad` | INT(11) | SÍ | Unidades del movimiento |
| `fecha` | DATE | SÍ | Fecha del movimiento |
| `descripcion` | VARCHAR(200) | SÍ | Motivo o nota (opcional) |
| `proveedor` | VARCHAR(100) | SÍ | Proveedor en entradas (opcional) |
| `id_producto` | INT(11) | SÍ | FK → `producto.id_producto` |
| `id_usuario` | INT(11) | SÍ | FK → `usuario.id_usuario` |
| `id_venta` | INT(11) | SÍ | FK → `venta.id_venta` (solo salidas por venta) |

**Efecto sobre `inventario.cantidad_stock`:**

| tipo_movimiento | Operación |
|-----------------|-----------|
| `entrada` | `cantidad_stock + :cant` |
| `salida` | `cantidad_stock - :cant` |
| `ajuste` | `cantidad_stock = :cant` (reemplaza el valor) |

---

### `reporte`
Tabla existente pero sin uso en el código actual. Reservada para implementación futura.

| Columna | Tipo | Nulo | Descripción |
|---------|------|------|-------------|
| `id_reporte` | INT(11) | NO | PK autoincremental |
| `fecha_generacion` | DATE | SÍ | Cuándo se generó |
| `descripcion` | VARCHAR(200) | SÍ | Descripción |
| `formato` | VARCHAR(50) | SÍ | PDF, Excel, etc. |
| `tipo_reporte` | VARCHAR(50) | SÍ | ventas, inventario, etc. |
| `fecha_inicio` | DATE | SÍ | Inicio del período |
| `fecha_fin` | DATE | SÍ | Fin del período |
| `id_administrador` | INT(11) | SÍ | FK → `administrador.id_administrador` |

---

## Claves foráneas

```sql
administrador.id_usuario  → usuario.id_usuario
empleado.id_usuario       → usuario.id_usuario
producto.id_categoria     → categoria.id_categoria
inventario.id_producto    → producto.id_producto
alerta.id_producto        → producto.id_producto
venta.id_usuario          → usuario.id_usuario
detalle_venta.id_venta    → venta.id_venta
detalle_venta.id_producto → producto.id_producto
factura.id_venta          → venta.id_venta
movimiento_inventario.id_producto → producto.id_producto
movimiento_inventario.id_usuario  → usuario.id_usuario
movimiento_inventario.id_venta    → venta.id_venta  (nullable)
reporte.id_administrador  → administrador.id_administrador
```

---

## Datos iniciales

Para que el sistema funcione desde cero se necesitan estos inserts:

### Administrador por defecto

```sql
-- Contraseña: admin123
INSERT INTO usuario (nombre, usuario, contrasenia, estado, fecha_registro, requiere_cambio_contrasenia)
VALUES ('Administrador', 'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'activo', CURDATE(), 0);

INSERT INTO administrador (id_usuario) VALUES (1);
```

> Si se necesita cambiar la contraseña del admin sin entrar al sistema:
> ```php
> echo password_hash('nueva_clave', PASSWORD_DEFAULT);
> // Copiar el resultado y hacer: UPDATE usuario SET contrasenia = '...' WHERE id_usuario = 1;
> ```

### Categorías (10 iniciales)

```sql
INSERT INTO categoria (nombre_categoria, descripcion) VALUES
('Manillas',   'Pulseras y manillas de todo tipo'),
('Vestidos',   'Vestidos y faldas para dama'),
('Accesorios', 'Collares, aretes y accesorios varios'),
('Ropa',       'Prendas de vestir en general'),
('Bolsos',     'Bolsos, carteras y monederos'),
('Zapatos',    'Calzado para dama y niña'),
('Ropa Niña',  'Prendas de vestir para niñas'),
('Bisutería',  'Joyas y adornos de bisutería'),
('Cinturones', 'Cinturones y correas de moda'),
('Gorras',     'Gorras, sombreros y viseras');
```

---

## Queries clave del sistema

### Determinar rol en el login

```sql
SELECT u.id_usuario AS id, u.nombre, u.contrasenia AS password_hash,
       u.estado, u.requiere_cambio_contrasenia,
       CASE
           WHEN a.id_administrador IS NOT NULL THEN 'admin'
           WHEN e.id_empleado      IS NOT NULL THEN 'empleado'
           ELSE 'empleado'
       END AS rol,
       a.id_administrador, e.id_empleado
FROM usuario u
LEFT JOIN administrador a ON a.id_usuario = u.id_usuario
LEFT JOIN empleado      e ON e.id_usuario = u.id_usuario
WHERE u.usuario = :usuario AND u.estado = 'activo'
LIMIT 1;
```

### Obtener productos con stock

```sql
SELECT p.*,
       c.nombre_categoria AS categoria_nombre,
       COALESCE(i.cantidad_stock, 0) AS stock_actual,
       COALESCE(i.stock_minimo,   0) AS stock_minimo
FROM producto p
LEFT JOIN categoria c  ON p.id_categoria = c.id_categoria
LEFT JOIN inventario i ON i.id_producto  = p.id_producto
ORDER BY p.nombre ASC;
```

### Métricas del dashboard

```sql
-- Ventas de hoy
SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS monto
FROM venta WHERE fecha_venta = CURDATE() AND estado != 'anulada';

-- Ingresos del mes actual
SELECT COALESCE(SUM(total), 0) FROM venta
WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
  AND estado != 'anulada';

-- Productos con stock bajo
SELECT COUNT(*) FROM producto p
JOIN inventario i ON i.id_producto = p.id_producto
WHERE p.estado = 'activo' AND i.stock_minimo > 0
  AND i.cantidad_stock <= i.stock_minimo;
```

---

## Diferencias con el esquema viejo

> `DisneyStock.sql` es el esquema **original incompatible**. Si se usa para recrear la BD, el sistema no funciona.

| Elemento | Esquema viejo | Esquema actual |
|----------|---------------|----------------|
| Campo contraseña | `contrasena` (sin i) | `contrasenia` (con i) |
| Estado usuario | `activo TINYINT(1)` | `estado VARCHAR(20)` |
| Fecha creación | `created_at DATETIME` | `fecha_registro DATE` |
| Stock | En tabla `producto` | Tabla separada `inventario` |
| Fechas | `DATETIME` | `DATE` (sin hora) |
| Vendedor en venta | `id_empleado` + `id_administrador` | `id_usuario` único |
| Movimientos | `id_administrador FK` | `id_usuario FK` |
| Nombres de tablas | `Usuario`, `Venta` (mayúsculas) | `usuario`, `venta` (minúsculas) |

---

**Autor:** Heidy Johanna Reyes Quesada  
**Proyecto:** DisneyStock  
**Fecha:** Agosto 2026
