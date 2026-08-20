<?php
// ============================================================
//  DisneyStock — Modelo de Venta
//  Archivo: models/Venta.php
//
//  RESPONSABILIDAD:
//  Todas las operaciones de BD sobre ventas, facturas,
//  detalles de venta y alertas de stock post-venta.
//
//  MÉTODOS DISPONIBLES:
//  - metricasHoy($hoy)
//      → Cuenta y suma ventas de hoy (excluye anuladas).
//        Usado en tarjetas del dashboard.
//  - ingresosMes($mes)
//      → Suma ingresos del mes dado (excluye anuladas).
//  - ultimas($limite)
//      → Últimas N ventas con datos de factura y vendedor.
//        Usado en la tabla del dashboard.
//  - listar($desde, $hasta, $estado)
//      → Ventas filtradas por rango de fechas y estado opcional.
//        Usado en la vista de ventas.
//  - obtenerDetalle($id)
//      → Una venta completa con factura y vendedor.
//        Usado en el modal de detalle (AJAX).
//  - obtenerItems($id)
//      → Ítems de una venta con nombre y código de producto.
//        Usado en el modal de detalle y en reportes.
//  - crear($items, $descuento, $id_adm, $id_emp)
//      → TRANSACCIÓN COMPLETA:
//        1. Valida stock de cada producto.
//        2. Inserta en Venta.
//        3. Inserta Detalle_Venta por cada ítem.
//        4. Descuenta stock_actual de cada producto.
//        5. Registra Movimiento_Inventario (salida).
//        6. Crea Alerta si el stock queda bajo mínimo.
//        7. Inserta Factura con número DS-XXXXXX.
//        Retorna array ['ok'=>bool, 'factura'=>string, 'total'=>float].
//  - anular($id, $id_adm)
//      → TRANSACCIÓN: restaura stock de cada ítem, registra
//        movimientos de entrada y marca la venta como 'anulada'.
//  - reporteVentas($desde, $hasta)
//      → Ventas completas del período para la vista de reportes.
// ============================================================

class Venta
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // ── Ventas de hoy (métricas dashboard) ───────────────────
    public function metricasHoy(string $hoy): array
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(total),0) AS monto
             FROM Venta WHERE DATE(fecha_venta) = :hoy AND estado != 'anulada'"
        );
        $stmt->execute([':hoy' => $hoy]);
        return $stmt->fetch();
    }

    // ── Ingresos del mes (métricas dashboard) ─────────────────
    public function ingresosMes(string $mes): float
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(SUM(total),0) AS monto
             FROM Venta WHERE DATE_FORMAT(fecha_venta,'%Y-%m') = :mes AND estado != 'anulada'"
        );
        $stmt->execute([':mes' => $mes]);
        return (float)$stmt->fetchColumn();
    }

    // ── Últimas N ventas (dashboard) ──────────────────────────
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

    // ── Listado filtrado (vista ventas) ───────────────────────
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
        if ($estado) {
            $sql .= " AND v.estado = :estado";
            $params[':estado'] = $estado;
        }
        $sql .= " ORDER BY v.fecha_venta DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Detalle de una venta ──────────────────────────────────
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

    // ── Items de una venta ────────────────────────────────────
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

    // ── Crear venta completa (transacción) ────────────────────
    public function crear(array $items, float $descuento, ?int $id_adm, ?int $id_emp): array
    {
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += (float)$it['precio_unitario'] * (int)$it['cantidad'];
        }
        $total = max(0, $subtotal - $descuento);

        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "INSERT INTO Venta (subtotal, descuento, total, estado, id_empleado, id_administrador)
                 VALUES (:sub, :desc, :total, 'completada', :emp, :adm)"
            )->execute([':sub'=>$subtotal,':desc'=>$descuento,':total'=>$total,':emp'=>$id_emp,':adm'=>$id_adm]);
            $id_venta = (int)$this->conn->lastInsertId();
            $numFac   = 'DS-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT);

            foreach ($items as $it) {
                $pid    = (int)$it['id_producto'];
                $cant   = (int)$it['cantidad'];
                $precio = (float)$it['precio_unitario'];

                $stk = $this->conn->prepare("SELECT stock_actual, nombre FROM Producto WHERE id_producto = :pid");
                $stk->execute([':pid' => $pid]);
                $prod = $stk->fetch();
                if (!$prod || $prod['stock_actual'] < $cant) {
                    throw new Exception("Stock insuficiente para: " . ($prod['nombre'] ?? "producto #$pid"));
                }

                $this->conn->prepare(
                    "INSERT INTO Detalle_Venta (cantidad, precio_unitario, subtotal, id_venta, id_producto)
                     VALUES (:cant, :precio, :sub, :vid, :pid)"
                )->execute([':cant'=>$cant,':precio'=>$precio,':sub'=>$precio*$cant,':vid'=>$id_venta,':pid'=>$pid]);

                $this->conn->prepare(
                    "UPDATE Producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid"
                )->execute([':cant'=>$cant,':pid'=>$pid]);

                $this->conn->prepare(
                    "INSERT INTO Movimiento_Inventario (tipo_movimiento, cantidad, descripcion, id_producto, id_administrador, id_venta)
                     VALUES ('salida', :cant, :desc, :pid, :adm, :vid)"
                )->execute([':cant'=>$cant,':desc'=>"Venta $numFac",':pid'=>$pid,':adm'=>$id_adm,':vid'=>$id_venta]);

                // Alerta de stock bajo
                $nuevo = $this->conn->prepare("SELECT stock_actual, stock_minimo FROM Producto WHERE id_producto = :pid");
                $nuevo->execute([':pid' => $pid]);
                $np = $nuevo->fetch();
                if ($np['stock_minimo'] > 0 && $np['stock_actual'] <= $np['stock_minimo']) {
                    $ya = $this->conn->prepare("SELECT COUNT(*) FROM Alerta WHERE id_producto=:pid AND estado='activa'");
                    $ya->execute([':pid' => $pid]);
                    if (!(int)$ya->fetchColumn()) {
                        $this->conn->prepare(
                            "INSERT INTO Alerta (tipo_alerta, mensaje, id_producto) VALUES ('stock_bajo', :msg, :pid)"
                        )->execute([':msg'=>"Stock bajo tras venta $numFac: {$prod['nombre']} tiene {$np['stock_actual']} unidades",':pid'=>$pid]);
                    }
                }
            }

            $this->conn->prepare(
                "INSERT INTO Factura (numero, total, id_venta) VALUES (:num, :total, :vid)"
            )->execute([':num'=>$numFac,':total'=>$total,':vid'=>$id_venta]);

            $this->conn->commit();
            return ['ok' => true, 'factura' => $numFac, 'total' => $total];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Anular venta ──────────────────────────────────────────
    public function anular(int $id, ?int $id_adm): array
    {
        $this->conn->beginTransaction();
        try {
            $detalles = $this->conn->prepare("SELECT id_producto, cantidad FROM Detalle_Venta WHERE id_venta = :id");
            $detalles->execute([':id' => $id]);
            foreach ($detalles->fetchAll() as $d) {
                $this->conn->prepare(
                    "UPDATE Producto SET stock_actual = stock_actual + :cant WHERE id_producto = :pid"
                )->execute([':cant'=>$d['cantidad'],':pid'=>$d['id_producto']]);
                $this->conn->prepare(
                    "INSERT INTO Movimiento_Inventario (tipo_movimiento, cantidad, descripcion, id_producto, id_administrador, id_venta)
                     VALUES ('entrada', :cant, 'Anulación de venta', :pid, :adm, :vid)"
                )->execute([':cant'=>$d['cantidad'],':pid'=>$d['id_producto'],':adm'=>$id_adm,':vid'=>$id]);
            }
            $this->conn->prepare("UPDATE Venta SET estado='anulada' WHERE id_venta=:id")->execute([':id'=>$id]);
            $this->conn->commit();
            return ['ok' => true];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Reporte ventas por período ────────────────────────────
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
