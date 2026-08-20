<?php
// ============================================================
//  DisneyStock — Modelo de Producto
//  Archivo: models/Producto.php
//  Tablas: Producto, Categoria, Alerta, Detalle_Venta
// ============================================================

class Producto
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Retorna productos con JOIN a Categoria para mostrar el nombre
    // Si $buscar no es vacio, filtra por nombre con LIKE
    // Si $idCategoria no es vacio, filtra por categoria
    public function obtenerTodos(string $buscar = '', int|string $idCategoria = ''): array
    {
        $sql = "SELECT p.*, c.nombre_categoria AS categoria_nombre
                FROM Producto p
                LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
                WHERE 1=1";
        $params = [];

        // Agregar filtro de nombre solo si se busco algo
        if ($buscar) {
            $sql .= " AND p.nombre LIKE :q";
            $params[':q'] = "%$buscar%";
        }
        // Agregar filtro de categoria solo si se selecciono una
        if ($idCategoria !== '') {
            $sql .= " AND p.id_categoria = :cat";
            $params[':cat'] = $idCategoria;
        }
        $sql .= " ORDER BY p.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Solo productos activos con los campos necesarios para el select de ventas
    public function obtenerActivos(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_producto, nombre, precio_venta, stock_actual
             FROM Producto WHERE estado = 'activo' ORDER BY nombre ASC"
        );
        return $stmt->fetchAll();
    }

    // Cuenta productos activos para la tarjeta del dashboard
    public function contarActivos(): int
    {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM Producto WHERE estado = 'activo'"
        )->fetchColumn();
    }

    // Cuenta productos activos cuyo stock esta en o por debajo del minimo
    // Usado en la tarjeta de stock bajo del dashboard y en el banner de inventario
    public function contarStockBajo(): int
    {
        return (int)$this->conn->query(
            "SELECT COUNT(*) FROM Producto
             WHERE stock_actual <= stock_minimo AND stock_minimo > 0 AND estado = 'activo'"
        )->fetchColumn();
    }

    // Retorna los N productos mas vendidos por cantidad total en Detalle_Venta
    // Usado en el panel "Top Productos" del dashboard
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

    // Retorna inventario activo con categoria
    // Si $filtro = 'bajo' solo trae los que estan en o bajo el minimo
    public function obtenerInventario(string $filtro = 'todos'): array
    {
        $sql = "SELECT p.id_producto, p.nombre, p.stock_actual, p.stock_minimo,
                       p.proveedor, p.estado, p.fecha_ingreso,
                       c.nombre_categoria AS categoria
                FROM Producto p
                LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria
                WHERE p.estado = 'activo'";

        // Agregar condicion de stock bajo solo si se filtro por 'bajo'
        if ($filtro === 'bajo') {
            $sql .= " AND p.stock_actual <= p.stock_minimo AND p.stock_minimo > 0";
        }
        $sql .= " ORDER BY p.nombre ASC";
        return $this->conn->query($sql)->fetchAll();
    }

    // Inserta un nuevo producto y retorna el ID generado
    // Retorna false si el insert no genero ID (no deberia ocurrir)
    public function crear(array $datos): int|false
    {
        $this->conn->prepare(
            "INSERT INTO Producto
                (nombre, precio_venta, precio_compra, stock_actual, stock_minimo,
                 fecha_ingreso, proveedor, imagen, id_categoria)
             VALUES
                (:nombre, :pventa, :pcompra, :stock, :minimo, :fecha, :proveedor, :imagen, :cat)"
        )->execute([
            ':nombre'    => $datos['nombre'],
            ':pventa'    => $datos['precio_venta'],
            ':pcompra'   => $datos['precio_compra'],
            ':stock'     => $datos['stock_actual'],
            ':minimo'    => $datos['stock_minimo'],
            ':fecha'     => $datos['fecha_ingreso'],
            ':proveedor' => $datos['proveedor'],
            ':imagen'    => $datos['imagen'] ?? null,  // null si no se subio imagen
            ':cat'       => $datos['id_categoria'],
        ]);
        return (int)$this->conn->lastInsertId() ?: false;
    }

    // Actualiza los campos editables del producto
    // La imagen solo se actualiza si viene en el array (evita borrar la existente)
    public function actualizar(int $id, array $datos): void
    {
        $sql = "UPDATE Producto
                SET nombre=:nombre, precio_venta=:pventa, precio_compra=:pcompra,
                    stock_minimo=:minimo, fecha_ingreso=:fecha, proveedor=:proveedor,
                    id_categoria=:cat";
        $params = [
            ':nombre'    => $datos['nombre'],
            ':pventa'    => $datos['precio_venta'],
            ':pcompra'   => $datos['precio_compra'],
            ':minimo'    => $datos['stock_minimo'],
            ':fecha'     => $datos['fecha_ingreso'],
            ':proveedor' => $datos['proveedor'],
            ':cat'       => $datos['id_categoria'],
            ':id'        => $id,
        ];

        // Solo agregar imagen al UPDATE si el controlador subio una nueva
        if (array_key_exists('imagen', $datos) && $datos['imagen'] !== null) {
            $sql .= ", imagen=:imagen";
            $params[':imagen'] = $datos['imagen'];
        }
        $sql .= " WHERE id_producto=:id";
        $this->conn->prepare($sql)->execute($params);
    }

    // Alterna entre 'activo' e 'inactivo' usando IF directo en SQL
    // Mas eficiente que leer el estado y luego escribirlo
    public function toggleEstado(int $id): void
    {
        $this->conn->prepare(
            "UPDATE Producto SET estado = IF(estado='activo','inactivo','activo')
             WHERE id_producto = :id"
        )->execute([':id' => $id]);
    }

    // Verifica dependencias antes de eliminar
    // Si tiene ventas retorna el mensaje de error como string
    // Si no tiene ventas borra alertas, movimientos y el producto
    public function eliminar(int $id): bool|string
    {
        // Verificar si el producto tiene ventas registradas
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM Detalle_Venta WHERE id_producto = :id"
        );
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            return 'Este producto tiene ventas registradas. Desactivalo en su lugar.';
        }

        // Borrar dependencias en orden para no violar foreign keys
        $this->conn->prepare("DELETE FROM Alerta WHERE id_producto = :id")->execute([':id' => $id]);
        $this->conn->prepare("DELETE FROM Movimiento_Inventario WHERE id_producto = :id")->execute([':id' => $id]);
        $this->conn->prepare("DELETE FROM Producto WHERE id_producto = :id")->execute([':id' => $id]);
        return true;
    }

    // Busca un producto por ID — usado para obtener la imagen actual al editar
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM Producto WHERE id_producto = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // Reporte: inventario con valor total a precio de costo y de venta
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

    // Reporte: productos bajo minimo con campo 'faltante' calculado
    // faltante = stock_minimo - stock_actual
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
