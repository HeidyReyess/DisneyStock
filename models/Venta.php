<?php
// ============================================================
//  DisneyStock — Modelo de Venta
//  Archivo: models/Venta.php
//  Tablas: Venta, Detalle_Venta, Factura, Movimiento_Inventario, Alerta
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
            "SELECT COUNT(*) AS total, COALESCE(SUM(total),0) AS monto
             FROM Venta WHERE DATE(fecha_venta) = :hoy AND estado != 'anulada'"
        );
        $stmt->execute([':hoy' => $hoy]);
        return $stmt->fetch();
    }

    // Suma los ingresos del mes en formato 'YYYY-MM' (excluye anuladas)
    // Usado en la tarjeta "Ingresos del Mes" del dashboard
    public function ingresosMes(string $mes): float
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(SUM(total),0) AS monto
             FROM Venta WHERE DATE_FORMAT(fecha_venta,'%Y-%m') = :mes AND estado != 'anulada'"
        );
        $stmt->execute([':mes' => $mes]);
        return (float)$stmt->fetchColumn();
    }

    // Retorna las ultimas N ventas con numero de factura y nombre del vendedor
    // COALESCE(ua.nombre, ue.nombre) resuelve si fue admin o empleado quien vendio
    public function ultimas(int $limite = 6): array
    {
        $stmt = $this->conn->prepare(
            "SELECT v.id_venta, f.numero AS numero_factura, v.total, v.estado, v.fecha_venta,
                    COALESCE(ua.nombre, ue.nombre) AS vendedor
             FROM Venta v
             LEFT JOIN Factura f        ON f.id_venta          = v.id_venta
             LEFT JOIN Administrador a  ON a.id_administrador  = v.id_administrador
             LEFT JOIN Usuario ua       ON ua.id_usuario        = a.id_usuario
             LEFT JOIN Empleado e       ON e.id_empleado        = v.id_empleado
             LEFT JOIN Usuario ue       ON ue.id_usuario        = e.id_usuario
             ORDER BY v.fecha_venta DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Lista ventas del periodo con filtro opcional de estado
    // Si $estado esta vacio retorna todos los estados
    public function listar(string $desde, string $hasta, string $estado = ''): array
    {
        $sql = "SELECT v.*,
                       f.numero AS numero_factura,
                       COALESCE(ua.nombre, ue.nombre) AS vendedor
                FROM Venta v
                LEFT JOIN Factura f       ON f.id_venta         = v.id_venta
                LEFT JOIN Administrador a ON a.id_administrador = v.id_administrador
                LEFT JOIN Usuario ua      ON ua.id_usuario       = a.id_usuario
                LEFT JOIN Empleado e      ON e.id_empleado       = v.id_empleado
                LEFT JOIN Usuario ue      ON ue.id_usuario       = e.id_usuario
                WHERE DATE(v.fecha_venta) BETWEEN :desde AND :hasta";
        $params = [':desde' => $desde, ':hasta' => $hasta];

        // Agregar filtro de estado solo si se paso uno
        if ($estado) {
            $sql .= " AND v.estado = :estado";
            $params[':estado'] = $estado;
        }
        $sql .= " ORDER BY v.fecha_venta DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Retorna una venta completa con factura y vendedor
    // Usado en el modal de detalle (respuesta AJAX)
    public function obtenerDetalle(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT v.*,
                    f.numero AS numero_factura,
                    COALESCE(ua.nombre, ue.nombre) AS vendedor
             FROM Venta v
             LEFT JOIN Factura f        ON f.id_venta          = v.id_venta
             LEFT JOIN Administrador a  ON a.id_administrador  = v.id_administrador
             LEFT JOIN Usuario ua       ON ua.id_usuario        = a.id_usuario
             LEFT JOIN Empleado e       ON e.id_empleado        = v.id_empleado
             LEFT JOIN Usuario ue       ON ue.id_usuario        = e.id_usuario
             WHERE v.id_venta = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // Retorna los items (productos) de una venta con nombre y codigo del producto
    // Usado junto con obtenerDetalle() para armar el modal de detalle
    public function obtenerItems(int $id): array
    {
        $stmt = $this->conn->prepare(
            "SELECT d.*, p.nombre, p.id_producto AS codigo
             FROM Detalle_Venta d
             JOIN Producto p ON d.id_producto = p.id_producto
             WHERE d.id_venta = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    // Crea una venta completa en una sola transaccion atomica
    // Si cualquier paso falla hace rollBack y retorna ['ok'=>false]
    public function crear(array $items, float $descuento, ?int $id_adm, ?int $id_emp): array
    {
        // Calcular subtotal sumando precio * cantidad de cada item
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
        }
        // El total nunca puede ser negativo aunque el descuento sea mayor
        $total = max(0, $subtotal - $descuento);

        $this->conn->beginTransaction();
        try {
            // Paso 1: insertar la cabecera de la venta
            $this->conn->prepare(
                "INSERT INTO Venta (subtotal, descuento, total, estado, id_empleado, id_administrador)
                 VALUES (:sub, :desc, :total, 'completada', :emp, :adm)"
            )->execute([':sub'=>$subtotal, ':desc'=>$descuento, ':total'=>$total, ':emp'=>$id_emp, ':adm'=>$id_adm]);

            $id_venta = (int)$this->conn->lastInsertId();
            // Numero de factura con formato DS-000001
            $numFac = 'DS-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT);

            // Paso 2: procesar cada item del carrito
            foreach ($items as $it) {
                $pid    = (int)$it['id_producto'];
                $cant   = (int)$it['cantidad'];
                $precio = (float)$it['precio_unitario'];

                // Verificar stock disponible antes de continuar
                $stk = $this->conn->prepare("SELECT stock_actual, nombre FROM Producto WHERE id_producto = :pid");
                $stk->execute([':pid' => $pid]);
                $prod = $stk->fetch();
                if (!$prod || $prod['stock_actual'] < $cant) {
                    // Lanza excepcion para activar el rollBack
                    throw new Exception("Stock insuficiente para: " . ($prod['nombre'] ?? "producto #$pid"));
                }

                // Insertar linea del detalle de venta
                $this->conn->prepare(
                    "INSERT INTO Detalle_Venta (cantidad, precio_unitario, subtotal, id_venta, id_producto)
                     VALUES (:cant, :precio, :sub, :vid, :pid)"
                )->execute([':cant'=>$cant, ':precio'=>$precio, ':sub'=>$precio*$cant, ':vid'=>$id_venta, ':pid'=>$pid]);

                // Descontar el stock del producto
                $this->conn->prepare(
                    "UPDATE Producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid"
                )->execute([':cant'=>$cant, ':pid'=>$pid]);

                // Registrar el movimiento de salida en el inventario
                $this->conn->prepare(
                    "INSERT INTO Movimiento_Inventario (tipo_movimiento, cantidad, descripcion, id_producto, id_administrador, id_venta)
                     VALUES ('salida', :cant, :desc, :pid, :adm, :vid)"
                )->execute([':cant'=>$cant, ':desc'=>"Venta $numFac", ':pid'=>$pid, ':adm'=>$id_adm, ':vid'=>$id_venta]);

                // Verificar si el stock quedo bajo el minimo tras la venta
                $nuevo = $this->conn->prepare("SELECT stock_actual, stock_minimo FROM Producto WHERE id_producto = :pid");
                $nuevo->execute([':pid' => $pid]);
                $np = $nuevo->fetch();
                if ($np['stock_minimo'] > 0 && $np['stock_actual'] <= $np['stock_minimo']) {
                    // Solo crear alerta si no existe una activa para este producto
                    $ya = $this->conn->prepare("SELECT COUNT(*) FROM Alerta WHERE id_producto=:pid AND estado='activa'");
                    $ya->execute([':pid' => $pid]);
                    if (!(int)$ya->fetchColumn()) {
                        $this->conn->prepare(
                            "INSERT INTO Alerta (tipo_alerta, mensaje, id_producto) VALUES ('stock_bajo', :msg, :pid)"
                        )->execute([
                            ':msg' => "Stock bajo tras venta $numFac: {$prod['nombre']} tiene {$np['stock_actual']} unidades",
                            ':pid' => $pid
                        ]);
                    }
                }
            }

            // Paso 3: crear la factura vinculada a la venta
            $this->conn->prepare(
                "INSERT INTO Factura (numero, total, id_venta) VALUES (:num, :total, :vid)"
            )->execute([':num'=>$numFac, ':total'=>$total, ':vid'=>$id_venta]);

            $this->conn->commit();
            return ['ok' => true, 'factura' => $numFac, 'total' => $total];

        } catch (Exception $e) {
            // Cualquier error deshace todos los cambios
            $this->conn->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // Anula una venta: restaura el stock de cada item y marca como 'anulada'
    // Tambien registra movimientos de entrada por cada producto devuelto
    public function anular(int $id, ?int $id_adm): array
    {
        $this->conn->beginTransaction();
        try {
            // Leer todos los items de la venta para restaurar el stock
            $detalles = $this->conn->prepare("SELECT id_producto, cantidad FROM Detalle_Venta WHERE id_venta = :id");
            $detalles->execute([':id' => $id]);

            foreach ($detalles->fetchAll() as $d) {
                // Devolver el stock al producto
                $this->conn->prepare(
                    "UPDATE Producto SET stock_actual = stock_actual + :cant WHERE id_producto = :pid"
                )->execute([':cant'=>$d['cantidad'], ':pid'=>$d['id_producto']]);

                // Registrar la entrada en el historial de movimientos
                $this->conn->prepare(
                    "INSERT INTO Movimiento_Inventario (tipo_movimiento, cantidad, descripcion, id_producto, id_administrador, id_venta)
                     VALUES ('entrada', :cant, 'Anulacion de venta', :pid, :adm, :vid)"
                )->execute([':cant'=>$d['cantidad'], ':pid'=>$d['id_producto'], ':adm'=>$id_adm, ':vid'=>$id]);
            }

            // Marcar la venta como anulada
            $this->conn->prepare("UPDATE Venta SET estado='anulada' WHERE id_venta=:id")->execute([':id'=>$id]);
            $this->conn->commit();
            return ['ok' => true];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // Retorna ventas del periodo con todos los datos para el reporte
    public function reporteVentas(string $desde, string $hasta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT f.numero AS numero_factura,
                    COALESCE(ua.nombre, ue.nombre) AS vendedor,
                    v.subtotal, v.descuento, v.total, v.estado, v.fecha_venta
             FROM Venta v
             LEFT JOIN Factura f         ON f.id_venta          = v.id_venta
             LEFT JOIN Administrador a   ON a.id_administrador  = v.id_administrador
             LEFT JOIN Usuario ua        ON ua.id_usuario        = a.id_usuario
             LEFT JOIN Empleado e        ON e.id_empleado        = v.id_empleado
             LEFT JOIN Usuario ue        ON ue.id_usuario        = e.id_usuario
             WHERE DATE(v.fecha_venta) BETWEEN :desde AND :hasta
             ORDER BY v.fecha_venta DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
