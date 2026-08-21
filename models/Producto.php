<?php
// ============================================================
//  DisneyStock — Modelo de Producto
//  Archivo: models/Producto.php
//  BD: disney_stock
//  Tablas: producto, categoria, inventario, alerta, detalle_venta
//
//  CAMBIOS respecto a BD anterior:
//  - stock_actual y stock_minimo ya NO estan en producto
//    ahora viven en la tabla inventario (id_inventario, cantidad_stock,
//    stock_minimo, fecha_actualizacion, id_producto)
//  - imagen fue eliminada y luego reincorporada como VARCHAR(255)
//    con upload a /public/uploads/productos/
//  - nombre de tablas en minuscula (producto, categoria)
// ============================================================

class Producto
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Lista productos con JOIN a categoria e inventario para obtener stock
    // Si $buscar no es vacio filtra por nombre
    // Si $idCategoria no es vacio filtra por categoria
    public function obtenerTodos(string $buscar = '', int|string $idCategoria = ''): array
    {
        $sql = "SELECT p.*,
                       c.nombre_categoria AS categoria_nombre,
                       COALESCE(i.cantidad_stock, 0) AS stock_actual,
                       COALESCE(i.stock_minimo, 0)   AS stock_minimo
                FROM producto p
                LEFT JOIN categoria c   ON p.id_categoria = c.id_categoria
                LEFT JOIN inventario i  ON i.id_producto  = p.id_producto
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

    // Solo productos activos con su stock — para el select del modal de ventas
    public function obtenerActivos(): array
    {
        $stmt = $this->conn->query(
            "SELECT p.id_producto, p.nombre, p.precio_venta,
                    COALESCE(i.cantidad_stock, 0) AS stock_actual
             FROM producto p
             LEFT JOIN inventario i ON i.id_producto = p.id_producto
             WHERE p.estado = 'activo'
             ORDER BY p.nombre ASC"
        );
        return $stmt->fetchAll();
    }

    // Cuenta productos activos para la tarjeta del dashboard
    public function contarActivos(): int
    {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM producto WHERE estado = 'activo'"
        )->fetchColumn();
    }

    // Cuenta productos activos cuyo stock esta en o por debajo del minimo
    // Hace JOIN con inventario porque el stock ya no esta en producto
    public function contarStockBajo(): int
    {
        return (int)$this->conn->query(
            "SELECT COUNT(*)
             FROM producto p
             JOIN inventario i ON i.id_producto = p.id_producto
             WHERE p.estado = 'activo'
               AND i.stock_minimo > 0
               AND i.cantidad_stock <= i.stock_minimo"
        )->fetchColumn();
    }

    // Top N productos mas vendidos por cantidad en detalle_venta
    public function topVendidos(int $limite = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.nombre, SUM(d.cantidad) AS vendidos, SUM(d.subtotal) AS total
             FROM detalle_venta d
             JOIN producto p ON d.id_producto = p.id_producto
             GROUP BY p.id_producto, p.nombre
             ORDER BY vendidos DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Inventario con stock desde tabla inventario
    // $filtro='bajo' trae solo los que estan en o bajo el minimo
    public function obtenerInventario(string $filtro = 'todos'): array
    {
        $sql = "SELECT p.id_producto, p.nombre, p.proveedor, p.estado, p.fecha_ingreso,
                       c.nombre_categoria AS categoria,
                       COALESCE(i.cantidad_stock, 0) AS stock_actual,
                       COALESCE(i.stock_minimo, 0)   AS stock_minimo,
                       i.fecha_actualizacion
                FROM producto p
                LEFT JOIN categoria c  ON p.id_categoria = c.id_categoria
                LEFT JOIN inventario i ON i.id_producto  = p.id_producto
                WHERE p.estado = 'activo'";

        if ($filtro === 'bajo') {
            $sql .= " AND i.stock_minimo > 0 AND i.cantidad_stock <= i.stock_minimo";
        }
        $sql .= " ORDER BY p.nombre ASC";
        return $this->conn->query($sql)->fetchAll();
    }

    // Inserta un nuevo producto SIN stock (el stock se maneja en Inventario)
    // imagen es opcional — ruta relativa desde la raiz del proyecto
    // Retorna el ID generado o false
    public function crear(array $datos): int|false
    {
        $this->conn->prepare(
            "INSERT INTO producto (nombre, precio_venta, precio_compra, fecha_ingreso, estado, proveedor, imagen, id_categoria)
             VALUES (:nombre, :pventa, :pcompra, :fecha, 'activo', :proveedor, :imagen, :cat)"
        )->execute([
            ':nombre'    => $datos['nombre'],
            ':pventa'    => $datos['precio_venta'],
            ':pcompra'   => $datos['precio_compra'],
            ':fecha'     => $datos['fecha_ingreso'],
            ':proveedor' => $datos['proveedor'],
            ':imagen'    => $datos['imagen'] ?? null,
            ':cat'       => $datos['id_categoria'],
        ]);
        return (int)$this->conn->lastInsertId() ?: false;
    }

    // Actualiza campos editables del producto
    // Si imagen viene en $datos se actualiza, si no se deja intacta
    public function actualizar(int $id, array $datos): void
    {
        // Construir SET dinamicamente para no pisar la imagen si no se subio una nueva
        $set    = "nombre=:nombre, precio_venta=:pventa, precio_compra=:pcompra,
                   fecha_ingreso=:fecha, proveedor=:proveedor, id_categoria=:cat";
        $params = [
            ':nombre'    => $datos['nombre'],
            ':pventa'    => $datos['precio_venta'],
            ':pcompra'   => $datos['precio_compra'],
            ':fecha'     => $datos['fecha_ingreso'],
            ':proveedor' => $datos['proveedor'],
            ':cat'       => $datos['id_categoria'],
            ':id'        => $id,
        ];

        // Solo actualizar imagen si viene informada en $datos
        if (array_key_exists('imagen', $datos)) {
            $set .= ", imagen=:imagen";
            $params[':imagen'] = $datos['imagen'];
        }

        $this->conn->prepare(
            "UPDATE producto SET $set WHERE id_producto=:id"
        )->execute($params);
    }

    // Alterna entre 'activo' e 'inactivo' con IF directo en SQL
    public function toggleEstado(int $id): void
    {
        $this->conn->prepare(
            "UPDATE producto SET estado = IF(estado='activo','inactivo','activo')
             WHERE id_producto = :id"
        )->execute([':id' => $id]);
    }

    // Verifica dependencias antes de eliminar
    // Si tiene ventas retorna el mensaje de error como string
    // Si no tiene ventas borra alertas, movimientos, inventario y el producto
    public function eliminar(int $id): bool|string
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM detalle_venta WHERE id_producto = :id"
        );
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            return 'Este producto tiene ventas registradas. Desactivalo en su lugar.';
        }

        // Borrar dependencias en orden para no violar foreign keys
        $this->conn->prepare("DELETE FROM alerta WHERE id_producto = :id")->execute([':id' => $id]);
        $this->conn->prepare("DELETE FROM movimiento_inventario WHERE id_producto = :id")->execute([':id' => $id]);
        $this->conn->prepare("DELETE FROM inventario WHERE id_producto = :id")->execute([':id' => $id]);
        $this->conn->prepare("DELETE FROM producto WHERE id_producto = :id")->execute([':id' => $id]);
        return true;
    }

    // Busca un producto por ID
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT p.*,
                    COALESCE(i.cantidad_stock, 0) AS stock_actual,
                    COALESCE(i.stock_minimo, 0)   AS stock_minimo
             FROM producto p
             LEFT JOIN inventario i ON i.id_producto = p.id_producto
             WHERE p.id_producto = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // Reporte: inventario valorizado a precio de costo y venta
    public function reporteInventario(): array
    {
        return $this->conn->query(
            "SELECT p.id_producto AS codigo, p.nombre,
                    c.nombre_categoria AS categoria,
                    COALESCE(i.cantidad_stock, 0) AS stock_actual,
                    COALESCE(i.stock_minimo, 0)   AS stock_minimo,
                    (COALESCE(i.cantidad_stock,0) * p.precio_compra) AS valor_costo,
                    (COALESCE(i.cantidad_stock,0) * p.precio_venta)  AS valor_venta,
                    p.fecha_ingreso AS ultima_actualizacion
             FROM producto p
             LEFT JOIN categoria c  ON p.id_categoria = c.id_categoria
             LEFT JOIN inventario i ON i.id_producto  = p.id_producto
             WHERE p.estado = 'activo'
             ORDER BY p.nombre ASC"
        )->fetchAll();
    }

    // Reporte: productos con stock bajo el minimo
    public function reporteStockBajo(): array
    {
        return $this->conn->query(
            "SELECT p.id_producto AS codigo, p.nombre,
                    c.nombre_categoria AS categoria,
                    i.cantidad_stock AS stock_actual,
                    i.stock_minimo,
                    (i.stock_minimo - i.cantidad_stock) AS faltante
             FROM producto p
             LEFT JOIN categoria c  ON p.id_categoria = c.id_categoria
             JOIN inventario i      ON i.id_producto  = p.id_producto
             WHERE i.cantidad_stock <= i.stock_minimo
               AND i.stock_minimo > 0
               AND p.estado = 'activo'
             ORDER BY faltante DESC"
        )->fetchAll();
    }
}
