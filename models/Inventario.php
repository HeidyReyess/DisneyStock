<?php
// ============================================================
//  DisneyStock — Modelo de Inventario
//  Archivo: models/Inventario.php
//  BD: disney_stock
//  Tablas: inventario, movimiento_inventario, producto, alerta
//
//  CAMBIOS respecto a BD anterior:
//  - Nueva tabla 'inventario' con cantidad_stock y stock_minimo
//    (antes el stock estaba en la tabla producto)
//  - movimiento_inventario usa id_usuario en lugar de id_administrador
//  - proveedor ahora es campo de movimiento_inventario
// ============================================================

class Inventario
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Crea el registro de inventario para un producto nuevo
    // Se llama desde el controlador despues de crear el producto
    public function crearRegistro(int $id_producto, int $stock_inicial = 0, int $stock_minimo = 0): void
    {
        $this->conn->prepare(
            "INSERT INTO inventario (cantidad_stock, stock_minimo, fecha_actualizacion, id_producto)
             VALUES (:stock, :minimo, CURDATE(), :pid)"
        )->execute([
            ':stock'  => $stock_inicial,
            ':minimo' => $stock_minimo,
            ':pid'    => $id_producto,
        ]);
    }

    // Actualiza solo el stock_minimo del inventario de un producto
    public function actualizarMinimo(int $id_producto, int $stock_minimo): void
    {
        $this->conn->prepare(
            "UPDATE inventario SET stock_minimo = :minimo WHERE id_producto = :pid"
        )->execute([':minimo' => $stock_minimo, ':pid' => $id_producto]);
    }

    // Retorna los ultimos N movimientos con nombre de producto y usuario
    // id_usuario unifica admin y empleado — JOIN directo a usuario
    public function ultimosMovimientos(int $limite = 10): array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id_movimiento, m.tipo_movimiento AS tipo, m.cantidad,
                    m.descripcion AS motivo, m.fecha, m.proveedor,
                    p.nombre AS producto,
                    COALESCE(u.nombre, 'Sistema') AS usuario
             FROM movimiento_inventario m
             JOIN producto p       ON m.id_producto = p.id_producto
             LEFT JOIN usuario u   ON m.id_usuario  = u.id_usuario
             ORDER BY m.fecha DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Registra un movimiento y actualiza la tabla inventario
    // Tipos: entrada (suma), salida (resta), ajuste (reemplaza)
    // Gestiona alertas automaticamente segun el resultado
    public function registrar(int $id_producto, string $tipo, int $cantidad, ?string $descripcion, ?int $id_usuario): array
    {
        // Leer el stock actual desde la tabla inventario
        $stk = $this->conn->prepare(
            "SELECT i.cantidad_stock, i.stock_minimo, p.nombre
             FROM inventario i
             JOIN producto p ON p.id_producto = i.id_producto
             WHERE i.id_producto = :pid"
        );
        $stk->execute([':pid' => $id_producto]);
        $inv = $stk->fetch();

        // Verificar que el producto tenga registro en inventario
        if (!$inv) {
            return ['ok' => false, 'error' => 'Producto no encontrado en inventario.'];
        }

        // Para salidas, verificar que haya suficiente stock
        if ($tipo === 'salida' && $inv['cantidad_stock'] < $cantidad) {
            return ['ok' => false, 'error' => "Solo hay {$inv['cantidad_stock']} unidades disponibles."];
        }

        // Insertar el movimiento en el historial
        $this->conn->prepare(
            "INSERT INTO movimiento_inventario (tipo_movimiento, cantidad, fecha, descripcion, id_producto, id_usuario)
             VALUES (:tipo, :cant, CURDATE(), :desc, :pid, :uid)"
        )->execute([
            ':tipo' => $tipo,
            ':cant' => $cantidad,
            ':desc' => $descripcion,
            ':pid'  => $id_producto,
            ':uid'  => $id_usuario,
        ]);

        // Actualizar cantidad_stock en la tabla inventario segun el tipo
        if ($tipo === 'entrada') {
            $sql = "UPDATE inventario SET cantidad_stock = cantidad_stock + :cant, fecha_actualizacion = CURDATE() WHERE id_producto = :pid";
        } elseif ($tipo === 'salida') {
            $sql = "UPDATE inventario SET cantidad_stock = cantidad_stock - :cant, fecha_actualizacion = CURDATE() WHERE id_producto = :pid";
        } else {
            // ajuste: establece el valor exacto indicado
            $sql = "UPDATE inventario SET cantidad_stock = :cant, fecha_actualizacion = CURDATE() WHERE id_producto = :pid";
        }
        $this->conn->prepare($sql)->execute([':cant' => $cantidad, ':pid' => $id_producto]);

        // Releer stock actualizado para decidir sobre alertas
        $nuevo = $this->conn->prepare(
            "SELECT cantidad_stock, stock_minimo FROM inventario WHERE id_producto = :pid"
        );
        $nuevo->execute([':pid' => $id_producto]);
        $act = $nuevo->fetch();

        if ($act['stock_minimo'] > 0 && $act['cantidad_stock'] <= $act['stock_minimo']) {
            // Stock quedo bajo el minimo — crear alerta si no existe una activa
            $ya = $this->conn->prepare(
                "SELECT COUNT(*) FROM alerta WHERE id_producto = :pid AND estado = 'activa'"
            );
            $ya->execute([':pid' => $id_producto]);
            if (!(int)$ya->fetchColumn()) {
                $this->conn->prepare(
                    "INSERT INTO alerta (tipo_alerta, mensaje, fecha_alerta, estado, id_producto)
                     VALUES ('stock_bajo', :msg, CURDATE(), 'activa', :pid)"
                )->execute([
                    ':msg' => "Stock bajo: '{$inv['nombre']}' tiene {$act['cantidad_stock']} unidades (minimo: {$act['stock_minimo']})",
                    ':pid' => $id_producto,
                ]);
            }
        } elseif (in_array($tipo, ['entrada', 'ajuste'])) {
            // Stock normalizado — resolver alertas activas del producto
            $this->conn->prepare(
                "UPDATE alerta SET estado = 'resuelta', fecha_resolucion = CURDATE()
                 WHERE id_producto = :pid AND estado = 'activa'"
            )->execute([':pid' => $id_producto]);
        }

        return ['ok' => true];
    }

    // Retorna las N alertas activas ordenadas por stock mas bajo primero
    public function alertasActivas(int $limite = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.mensaje, a.fecha_alerta, p.nombre,
                    i.cantidad_stock AS stock_actual,
                    i.stock_minimo
             FROM alerta a
             JOIN producto p   ON a.id_producto = p.id_producto
             JOIN inventario i ON i.id_producto = p.id_producto
             WHERE a.estado = 'activa'
             ORDER BY i.cantidad_stock ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Reporte de movimientos del periodo para la vista de reportes
    public function reporteMovimientos(string $desde, string $hasta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.nombre AS producto, m.tipo_movimiento AS tipo, m.cantidad,
                    m.descripcion AS motivo,
                    COALESCE(u.nombre, 'Sistema') AS usuario,
                    m.fecha
             FROM movimiento_inventario m
             JOIN producto p     ON m.id_producto = p.id_producto
             LEFT JOIN usuario u ON m.id_usuario  = u.id_usuario
             WHERE DATE(m.fecha) BETWEEN :desde AND :hasta
             ORDER BY m.fecha DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
