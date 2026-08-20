-- ============================================================
--  DisneyStock — Base de Datos
--  Negocio: Variedades Disney | Huila, Colombia
--  Desarrolladora: Heidy Johanna Reyes Quesada
-- ============================================================

CREATE DATABASE IF NOT EXISTS DisneyStock
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE DisneyStock;

-- ── 1. Usuario (tabla base de autenticación) ──────────────
CREATE TABLE IF NOT EXISTS Usuario (
    id_usuario     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(150)  NOT NULL,
    usuario        VARCHAR(80)   NOT NULL UNIQUE,
    contrasena     VARCHAR(255)  NOT NULL,          -- password_hash
    activo         TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 2. Administrador (extiende Usuario) ───────────────────
CREATE TABLE IF NOT EXISTS Administrador (
    id_administrador INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT UNSIGNED NOT NULL UNIQUE,
    CONSTRAINT fk_admin_usuario FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 3. Empleado (extiende Usuario) ────────────────────────
CREATE TABLE IF NOT EXISTS Empleado (
    id_empleado INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario  INT UNSIGNED NOT NULL UNIQUE,
    CONSTRAINT fk_empleado_usuario FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 4. Categoria ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Categoria (
    id_categoria    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL UNIQUE,
    descripcion      TEXT
) ENGINE=InnoDB;

-- ── 5. Producto ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Producto (
    id_producto   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(255) NOT NULL,
    precio_venta  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock_actual  INT          NOT NULL DEFAULT 0,
    stock_minimo  INT          NOT NULL DEFAULT 0,
    fecha_ingreso DATE         NOT NULL DEFAULT (CURDATE()),
    estado        ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    proveedor     VARCHAR(200),
    id_categoria  INT UNSIGNED,
    CONSTRAINT fk_prod_cat FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 6. Alerta ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Alerta (
    id_alerta       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_alerta     VARCHAR(80)  NOT NULL DEFAULT 'stock_bajo',
    fecha_alerta    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME,
    mensaje         TEXT         NOT NULL,
    estado          ENUM('activa','resuelta') NOT NULL DEFAULT 'activa',
    id_producto     INT UNSIGNED NOT NULL,
    CONSTRAINT fk_alerta_prod FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 7. Venta ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Venta (
    id_venta         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha_venta      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    impuesto         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descuento        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado           ENUM('completada','pendiente','anulada') NOT NULL DEFAULT 'completada',
    id_empleado      INT UNSIGNED,
    id_administrador INT UNSIGNED,
    CONSTRAINT fk_venta_emp  FOREIGN KEY (id_empleado)      REFERENCES Empleado(id_empleado)           ON DELETE SET NULL,
    CONSTRAINT fk_venta_adm  FOREIGN KEY (id_administrador) REFERENCES Administrador(id_administrador) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 8. Detalle_Venta ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS Detalle_Venta (
    id_detalle     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cantidad       INT           NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    subtotal       DECIMAL(12,2) NOT NULL,
    id_venta       INT UNSIGNED  NOT NULL,
    id_producto    INT UNSIGNED  NOT NULL,
    CONSTRAINT fk_dv_venta FOREIGN KEY (id_venta)    REFERENCES Venta(id_venta)       ON DELETE CASCADE,
    CONSTRAINT fk_dv_prod  FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ── 9. Factura ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Factura (
    id_Factura    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero        VARCHAR(20)  NOT NULL UNIQUE,
    fecha_emision DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total         DECIMAL(12,2) NOT NULL,
    formato       VARCHAR(20)  NOT NULL DEFAULT 'PDF',
    id_venta      INT UNSIGNED NOT NULL UNIQUE,
    CONSTRAINT fk_factura_venta FOREIGN KEY (id_venta) REFERENCES Venta(id_venta) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 10. Movimiento_Inventario ─────────────────────────────
CREATE TABLE IF NOT EXISTS Movimiento_Inventario (
    id_movimiento    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_movimiento  ENUM('entrada','salida','ajuste') NOT NULL,
    cantidad         INT      NOT NULL,
    fecha            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descripcion      TEXT,
    id_producto      INT UNSIGNED NOT NULL,
    id_administrador INT UNSIGNED,
    id_venta         INT UNSIGNED,
    CONSTRAINT fk_mov_prod  FOREIGN KEY (id_producto)      REFERENCES Producto(id_producto)             ON DELETE CASCADE,
    CONSTRAINT fk_mov_adm   FOREIGN KEY (id_administrador) REFERENCES Administrador(id_administrador)   ON DELETE SET NULL,
    CONSTRAINT fk_mov_venta FOREIGN KEY (id_venta)         REFERENCES Venta(id_venta)                   ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 11. Reporte ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS Reporte (
    id_reporte        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_reporte      VARCHAR(80)  NOT NULL,
    fecha_generacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descripcion       TEXT,
    id_administrador  INT UNSIGNED,
    CONSTRAINT fk_rep_adm FOREIGN KEY (id_administrador) REFERENCES Administrador(id_administrador) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  DATOS INICIALES
-- ============================================================

-- Usuario administrador por defecto
-- Usuario: admin | Contraseña: admin123
INSERT INTO Usuario (nombre, usuario, contrasena) VALUES
('Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Registrar como Administrador
INSERT INTO Administrador (id_usuario) VALUES (1);

-- Categorías iniciales para Variedades Disney
INSERT INTO Categoria (nombre_categoria, descripcion) VALUES
('Manillas',    'Pulseras y manillas de todo tipo'),
('Vestidos',    'Vestidos y faldas para dama'),
('Accesorios',  'Collares, aretes y accesorios varios'),
('Ropa',        'Prendas de vestir en general'),
('Bolsos',      'Bolsos, carteras y monederos');
