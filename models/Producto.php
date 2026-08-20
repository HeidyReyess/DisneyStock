<?php
// ============================================================
//  DisneyStock — Modelo de Producto
//  Archivo: models/Producto.php
//
//  RESPONSABILIDAD:
//  Todas las operaciones de BD relacionadas con productos,
//  categorías y alertas de stock.
//
//  MÉTODOS DISPONIBLES:
//  - obtenerTodos($buscar, $idCategoria)
//      → Lista productos con JOIN a Categoria. Filtra por nombre
//        y/o categoría si se proporcionan.
//  - obtenerActivos()
//      → Solo productos con estado='activo'. Usado en selects
//        de venta e inventario.
//  - contarActivos()
//      → Cuenta para la tarjeta del dashboard.
//  - contarStockBajo()
//      → Cuenta productos donde stock_actual <= stock_minimo.
//  - topVendidos($limite)
//      → Productos más vendidos por cantidad (JOIN Detalle_Venta).
//  - obtenerInventario($filtro)
//      → Lista con stock, mínimo, proveedor y categoría.
//        $filtro='bajo' filtra solo los que están bajo mínimo.
//  - crear($datos)
//      → Inserta producto y retorna el ID generado.
//  - actualizar($id, $datos)
//      → Actualiza campos. La imagen solo se actualiza si viene
//        en el array $datos.
//  - toggleEstado($id)
//      → Alterna entre 'activo' e 'inactivo'.
//  - eliminar($id)
//      → Verifica dependencias en Detalle_Venta antes de borrar.
//        Devuelve string con error si tiene ventas, true si ok.
//  - obtenerPorId($id)
//      → Busca un producto por su ID. Usado al editar imagen.
//  - reporteInventario()
//      → Inventario valorizado (stock * precio_compra y precio_venta).
//  - reporteStockBajo()
//      → Productos bajo mínimo con campo 'faltante' calculado.
// ============================================================

class Producto
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // ── Listado con filtros ───────────────────────────────────
    public function obtenerTodos(string $buscar = '', int|string $idCategoria = ''): array
    {
        $sql = "SELECT p.*, c.nombre_categoria AS categoria_nombre
                FROM Producto p
                LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
                WHERE 1=1";
        $params = [];
        if ($buscar) {
            $sql .= " AND p.nombre LIKE :q";
            $params[':q'] = "%$buscar%";
        }
        if ($idCategoria !== '') {
            $sql .= " AND p.id_categoria = :cat";
            $params[':cat'] = $idCategoria;
        }
        $sql .= " ORDER BY p.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Sólo activos (para selects de venta/inventario) ───────
    public function obtenerActivos(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_producto, nombre, precio_venta, stock_actual
             FROM Producto WHERE estado = 'activo' ORDER BY nombre ASC"
        );
        return $stmt->fetchAll();
    }

    // ── Contar activos ────────────────────────────────────────
    public function contarActivos(): int
    {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM Producto WHERE estado = 'activo'"
        )->fetchColumn();
    }

    // ── Contar con stock bajo ─────────────────────────────────
    public function contarStockBajo(): int
    {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM Producto
             WHERE stock_actual <= stock_minimo AND stock_minimo > 0 AND estado = 'activo'"
        )->fetchColumn();
    }

    // ── Top N más vendidos ────────────────────────────────────
    public function topVendidos(int $limite = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.nombre, SUM(d.cantidad) AS vendidos, SUM(d.subtotal) AS total
             FROM Detalle_Venta d
             JOIN Producto p ON d.id_producto = p.id_producto
             GROUP BY p.id_producto, p.nombre
             ORDER BY vendidos DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Inventario con filtro bajo/todos ──────────────────────
    public function obtenerInventario(string $filtro = 'todos'): array
    {
        $sql = "SELECT p.id_producto, p.nombre, p.stock_actual, p.stock_minimo,
                       p.proveedor, p.estado, p.fecha_ingreso,
                       c.nombre_categoria AS categoria
                FROM Producto p
                LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
                WHERE p.estado = 'activo'";
        if ($filtro === 'bajo') {
            $sql .= " AND p.stock_actual <= p.stock_minimo AND p.stock_minimo > 0";
        }
        $sql .= " ORDER BY p.nombre ASC";
        return $this->conn->query($sql)->fetchAll();
    }

    // ── Crear ─────────────────────────────────────────────────
    public function crear(array $datos): int|false
    {
        $this->conn->prepare(
            "INSERT INTO Producto
                (nombre, precio_venta, precio_compra, stock_actual, stock_minimo,
                 fecha_ingreso, proveedor, imagen, id_categoria)
             VALUES
                (:nombre, :pventa, :pcompra, :stock, :minimo, :fecha, :proveedor, :imagen, :cat)"
        )->execute([
            ':nombre'   => $datos['nombre'],
            ':pventa'   => $datos['precio_venta'],
            ':pcompra'  => $datos['precio_compra'],
            ':stock'    => $datos['stock_actual'],
            ':minimo'   => $datos['stock_minimo'],
            ':fecha'    => $datos['fecha_ingreso'],
            ':proveedor'=> $datos['proveedor'],
            ':imagen'   => $datos['imagen'] ?? null,
            ':cat'      => $datos['id_categoria'],
        ]);
        return (int)$this->conn->lastInsertId() ?: false;
    }

    // ── Editar ────────────────────────────────────────────────
    public function actualizar(int $id, array $datos): void
    {
        $sql = "UPDATE Producto
                SET nombre=:nombre, precio_venta=:pventa, precio_compra=:pcompra,
                    stock_minimo=:minimo, fecha_ingreso=:fecha, proveedor=:proveedor,
                    id_categoria=:cat";
        $params = [
            ':nombre'   => $datos['nombre'],
            ':pventa'   => $datos['precio_venta'],
            ':pcompra'  => $datos['precio_compra'],
            ':minimo'   => $datos['stock_minimo'],
            ':fecha'    => $datos['fecha_ingreso'],
            ':proveedor'=> $datos['proveedor'],
            ':cat'      => $datos['id_categoria'],
            ':id'       => $id,
        ];
        // Solo actualizar imagen si se envió una nueva
        if (array_key_exists('imagen', $datos) && $datos['imagen'] !== null) {
            $sql .= ", imagen=:imagen";
            $params[':imagen'] = $datos['imagen'];
        }
        $sql .= " WHERE id_producto=:id";
        $this->conn->prepare($sql)->execute($params);
    }

    // ── Toggle activo/inactivo ────────────────────────────────
    public function toggleEstado(int $id): void
    {
        $this->conn->prepare(
            "UPDATE Producto SET estado = IF(estado='activo','inactivo','activo')
             WHERE id_producto = :id"
        )->execute([':id' => $id]);
    }

    // ── Eliminar (verifica dependencias) ──────────────────────
    public function eliminar(int $id): bool|string
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM Detalle_Venta WHERE id_producto = :id"
        );
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            return 'Este producto tiene ventas registradas. Desactívalo en su lugar.';
        }
        $this->conn->prepare("DELETE FROM Alerta WHERE id_producto = :id")->execute([':id' => $id]);
        $this->conn->prepare("DELETE FROM Movimiento_Inventario WHERE id_producto = :id")->execute([':id' => $id]);
        $this->conn->prepare("DELETE FROM Producto WHERE id_producto = :id")->execute([':id' => $id]);
        return true;
    }

    // ── Verificar stock de un producto ────────────────────────
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM Producto WHERE id_producto = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // ── Reporte: inventario valorizado ────────────────────────
    public function reporteInventario(): array
    {
        return $this->conn->query(
            "SELECT p.id_producto AS codigo, p.nombre,
                    c.nombre_categoria AS categoria,
                    p.stock_actual, p.stock_minimo,
                    (p.stock_actual * p.precio_compra) AS valor_costo,
                    (p.stock_actual * p.precio_venta)  AS valor_venta,
                    p.fecha_ingreso AS ultima_actualizacion
             FROM Producto p
             LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
             WHERE p.estado = 'activo'
             ORDER BY p.nombre ASC"
        )->fetchAll();
    }

    // ── Reporte: productos con stock bajo ─────────────────────
    public function reporteStockBajo(): array
    {
        return $this->conn->query(
            "SELECT p.id_producto AS codigo, p.nombre,
                    c.nombre_categoria AS categoria,
                    p.stock_actual, p.stock_minimo,
                    (p.stock_minimo - p.stock_actual) AS faltante
             FROM Producto p
             LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
             WHERE p.stock_actual <= p.stock_minimo AND p.stock_minimo > 0 AND p.estado = 'activo'
             ORDER BY faltante DESC"
        )->fetchAll();
    }
}
