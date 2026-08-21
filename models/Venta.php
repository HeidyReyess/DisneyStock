<?php
// ============================================================
//  DisneyStock — Modelo de Venta
//  Archivo: models/Venta.php
//  BD: disney_stock
//  Tablas: venta, detalle_venta, factura, movimiento_inventario, alerta, inventario
//
//  CAMBIOS respecto a BD anterior:
//  - venta ya no tiene id_administrador + id_empleado separados
//    ahora tiene un solo campo: id_usuario
//  - movimiento_inventario usa id_usuario en lugar de id_administrador
//  - el stock se actualiza en tabla inventario, no en producto
//  - fecha_venta es DATE en lugar de DATETIME
//  - nombres de tablas en minuscula
// ============================================================

class Venta
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Retorna cantidad y monto total de ventas del dia (excluye anuladas)
    // Usado en la tarjeta "Ventas Hoy" del dashboard
    public function metricasHoy(string $hoy): array
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS monto
             FROM venta
             WHERE fecha_venta = :hoy AND estado != 'anulada'"
        );
        $stmt->execute([':hoy' => $hoy]);
        return $stmt->fetch();
    }

    // Suma ingresos del mes dado (excluye anuladas)
    public function ingresosMes(string $mes): float
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(SUM(total), 0) AS monto
             FROM venta
             WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = :mes AND estado != 'anulada'"
        );
        $stmt->execute([':mes' => $mes]);
        return (float)$stmt->fetchColumn();
    }

    // Retorna las ultimas N ventas con numero de factura y nombre del vendedor
    // JOIN simplificado: ahora es directo a usuario via id_usuario
    public function ultimas(int $limite = 6): array
    {
        $stmt = $this->conn->prepare(
            "SELECT v.id_venta, f.numero AS numero_factura,
                    v.total, v.estado, v.fecha_venta,
                    COALESCE(u.nombre, 'Sin vendedor') AS vendedor
             FROM venta v
             LEFT JOIN factura f  ON f.id_venta  = v.id_venta
             LEFT JOIN usuario u  ON u.id_usuario = v.id_usuario
             ORDER BY v.fecha_venta DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Lista ventas del periodo con filtro opcional de estado
    public function listar(string $desde, string $hasta, string $estado = ''): array
    {
        $sql = "SELECT v.*,
                       f.numero AS numero_factura,
                       COALESCE(u.nombre, 'Sin vendedor') AS vendedor
                FROM venta v
                LEFT JOIN factura f  ON f.id_venta   = v.id_venta
                LEFT JOIN usuario u  ON u.id_usuario  = v.id_usuario
                WHERE v.fecha_venta BETWEEN :desde AND :hasta";
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($estado) {
            $sql .= " AND v.estado = :estado";
            $params[':estado'] = $estado;
        }
        $sql .= " ORDER BY v.fecha_venta DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Retorna una venta completa con factura y vendedor para el modal AJAX
    public function obtenerDetalle(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT v.*,
                    f.numero AS numero_factura,
                    COALESCE(u.nombre, 'Sin vendedor') AS vendedor
             FROM venta v
             LEFT JOIN factura f  ON f.id_venta   = v.id_venta
             LEFT JOIN usuario u  ON u.id_usuario  = v.id_usuario
             WHERE v.id_venta = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // Retorna los items de una venta con nombre y codigo del producto
    public function obtenerItems(int $id): array
    {
        $stmt = $this->conn->prepare(
            "SELECT d.*, p.nombre, p.id_producto AS codigo
             FROM detalle_venta d
             JOIN producto p ON d.id_producto = p.id_producto
             WHERE d.id_venta = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    // Crea una venta completa en una transaccion atomica
    // Ahora recibe id_usuario en lugar de id_adm + id_emp por separado
    public function crear(array $items, float $descuento, ?int $id_usuario): array
    {
        // Calcular subtotal sumando precio * cantidad de cada item
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
        }
        $total = max(0, $subtotal - $descuento); // nunca negativo

        $this->conn->beginTransaction();
        try {
            // Paso 1: insertar la cabecera de la venta con id_usuario unico
            // impuesto se guarda como 0 — la logica de impuesto no esta implementada aun
            $this->conn->prepare(
                "INSERT INTO venta (fecha_venta, subtotal, impuesto, descuento, total, estado, id_usuario)
                 VALUES (CURDATE(), :sub, 0, :desc, :total, 'completada', :uid)"
            )->execute([
                ':sub'   => $subtotal,
                ':desc'  => $descuento,
                ':total' => $total,
                ':uid'   => $id_usuario,
            ]);
            $id_venta = (int)$this->conn->lastInsertId();
            $numFac   = 'DS-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT);

            // Paso 2: procesar cada item del carrito
            foreach ($items as $it) {
                $pid    = (int)$it['id_producto'];
                $cant   = (int)$it['cantidad'];
                $precio = (float)$it['precio_unitario'];

                // Verificar stock en tabla inventario (no en producto)
                $stk = $this->conn->prepare(
                    "SELECT i.cantidad_stock, p.nombre
                     FROM inventario i
                     JOIN producto p ON p.id_producto = i.id_producto
                     WHERE i.id_producto = :pid"
                );
                $stk->execute([':pid' => $pid]);
                $inv = $stk->fetch();

                if (!$inv || $inv['cantidad_stock'] < $cant) {
                    throw new Exception("Stock insuficiente para: " . ($inv['nombre'] ?? "producto #$pid"));
                }

                // Insertar linea del detalle de venta
                $this->conn->prepare(
                    "INSERT INTO detalle_venta (cantidad, precio_unitario, subtotal, id_venta, id_producto)
                     VALUES (:cant, :precio, :sub, :vid, :pid)"
                )->execute([
                    ':cant'   => $cant,
                    ':precio' => $precio,
                    ':sub'    => $precio * $cant,
                    ':vid'    => $id_venta,
                    ':pid'    => $pid,
                ]);

                // Descontar stock en tabla inventario
                $this->conn->prepare(
                    "UPDATE inventario SET cantidad_stock = cantidad_stock - :cant, fecha_actualizacion = CURDATE()
                     WHERE id_producto = :pid"
                )->execute([':cant' => $cant, ':pid' => $pid]);

                // Registrar movimiento de salida con id_usuario
                $this->conn->prepare(
                    "INSERT INTO movimiento_inventario (tipo_movimiento, cantidad, fecha, descripcion, id_producto, id_usuario, id_venta)
                     VALUES ('salida', :cant, CURDATE(), :desc, :pid, :uid, :vid)"
                )->execute([
                    ':cant' => $cant,
                    ':desc' => "Venta $numFac",
                    ':pid'  => $pid,
                    ':uid'  => $id_usuario,
                    ':vid'  => $id_venta,
                ]);

                // Verificar si el stock quedo bajo el minimo tras la venta
                $nuevo = $this->conn->prepare(
                    "SELECT cantidad_stock, stock_minimo FROM inventario WHERE id_producto = :pid"
                );
                $nuevo->execute([':pid' => $pid]);
                $np = $nuevo->fetch();

                if ($np['stock_minimo'] > 0 && $np['cantidad_stock'] <= $np['stock_minimo']) {
                    // Crear alerta solo si no existe una activa para este producto
                    $ya = $this->conn->prepare(
                        "SELECT COUNT(*) FROM alerta WHERE id_producto = :pid AND estado = 'activa'"
                    );
                    $ya->execute([':pid' => $pid]);
                    if (!(int)$ya->fetchColumn()) {
                        $this->conn->prepare(
                            "INSERT INTO alerta (tipo_alerta, mensaje, fecha_alerta, estado, id_producto)
                             VALUES ('stock_bajo', :msg, CURDATE(), 'activa', :pid)"
                        )->execute([
                            ':msg' => "Stock bajo tras venta $numFac: {$inv['nombre']} tiene {$np['cantidad_stock']} unidades",
                            ':pid' => $pid,
                        ]);
                    }
                }
            }

            // Paso 3: crear la factura vinculada a la venta
            $this->conn->prepare(
                "INSERT INTO factura (numero, fecha_emision, total, id_venta)
                 VALUES (:num, CURDATE(), :total, :vid)"
            )->execute([':num' => $numFac, ':total' => $total, ':vid' => $id_venta]);

            $this->conn->commit();
            return ['ok' => true, 'factura' => $numFac, 'total' => $total];

        } catch (Exception $e) {
            $this->conn->rollBack(); // deshacer todo si algo fallo
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // Anula una venta: restaura el stock en inventario y marca como anulada
    public function anular(int $id, ?int $id_usuario): array
    {
        $this->conn->beginTransaction();
        try {
            // Leer items de la venta para restaurar el stock
            $detalles = $this->conn->prepare(
                "SELECT id_producto, cantidad FROM detalle_venta WHERE id_venta = :id"
            );
            $detalles->execute([':id' => $id]);

            foreach ($detalles->fetchAll() as $d) {
                // Devolver el stock a la tabla inventario
                $this->conn->prepare(
                    "UPDATE inventario SET cantidad_stock = cantidad_stock + :cant, fecha_actualizacion = CURDATE()
                     WHERE id_producto = :pid"
                )->execute([':cant' => $d['cantidad'], ':pid' => $d['id_producto']]);

                // Registrar la entrada en el historial de movimientos
                $this->conn->prepare(
                    "INSERT INTO movimiento_inventario (tipo_movimiento, cantidad, fecha, descripcion, id_producto, id_usuario, id_venta)
                     VALUES ('entrada', :cant, CURDATE(), 'Anulacion de venta', :pid, :uid, :vid)"
                )->execute([
                    ':cant' => $d['cantidad'],
                    ':pid'  => $d['id_producto'],
                    ':uid'  => $id_usuario,
                    ':vid'  => $id,
                ]);
            }

            // Marcar la venta como anulada
            $this->conn->prepare(
                "UPDATE venta SET estado = 'anulada' WHERE id_venta = :id"
            )->execute([':id' => $id]);

            $this->conn->commit();
            return ['ok' => true];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // Reporte de ventas del periodo con factura y vendedor
    public function reporteVentas(string $desde, string $hasta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT f.numero AS numero_factura,
                    COALESCE(u.nombre, 'Sin vendedor') AS vendedor,
                    v.subtotal, v.descuento, v.total, v.estado, v.fecha_venta
             FROM venta v
             LEFT JOIN factura f  ON f.id_venta   = v.id_venta
             LEFT JOIN usuario u  ON u.id_usuario  = v.id_usuario
             WHERE v.fecha_venta BETWEEN :desde AND :hasta
             ORDER BY v.fecha_venta DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
