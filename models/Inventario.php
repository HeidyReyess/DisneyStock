<?php
// ============================================================
//  DisneyStock — Modelo de Inventario
//  Archivo: models/Inventario.php
//
//  RESPONSABILIDAD:
//  Operaciones sobre movimientos de inventario y alertas de stock.
//  Es el único lugar donde se debe gestionar la lógica de alertas
//  para movimientos manuales.
//
//  MÉTODOS DISPONIBLES:
//  - ultimosMovimientos($limite)
//      → Últimos N movimientos con datos de producto y usuario.
//        El usuario puede ser admin o empleado (via venta).
//        Usado en el panel lateral de la vista de inventario.
//  - registrar($id_producto, $tipo, $cantidad, $descripcion, $id_adm)
//      → Registra el movimiento y actualiza stock_actual:
//          · entrada → stock_actual + cantidad
//          · salida  → stock_actual - cantidad (valida disponibilidad)
//          · ajuste  → stock_actual = cantidad
//        Gestión de alertas automática:
//          · Si stock queda bajo mínimo → crea Alerta si no existe una.
//          · Si entrada/ajuste → resuelve alertas activas del producto.
//        Retorna ['ok'=>bool, 'error'=>string|null].
//  - alertasActivas($limite)
//      → Alertas con estado='activa' ordenadas por stock ascendente.
//        Usado en el panel de alertas del dashboard.
//  - reporteMovimientos($desde, $hasta)
//      → Movimientos del período para la vista de reportes.
// ============================================================

class Inventario
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // ── Últimos N movimientos ─────────────────────────────────
    public function ultimosMovimientos(int $limite = 10): array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id_movimiento, m.tipo_movimiento AS tipo, m.cantidad,
                    m.descripcion AS motivo, m.fecha,
                    p.nombre AS producto,
                    COALESCE(ua.nombre, ue.nombre, 'Sistema') AS usuario
             FROM Movimiento_Inventario m
             JOIN Producto p            ON m.id_producto      = p.id_producto
             LEFT JOIN Administrador a  ON a.id_administrador = m.id_administrador
             LEFT JOIN Usuario ua       ON ua.id_usuario       = a.id_usuario
             LEFT JOIN Venta v          ON v.id_venta          = m.id_venta
             LEFT JOIN Empleado emp     ON emp.id_empleado     = v.id_empleado
             LEFT JOIN Usuario ue       ON ue.id_usuario       = emp.id_usuario
             ORDER BY m.fecha DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Registrar movimiento y actualizar stock ───────────────
    public function registrar(int $id_producto, string $tipo, int $cantidad, ?string $descripcion, ?int $id_adm): array
    {
        $stk = $this->conn->prepare(
            "SELECT stock_actual, stock_minimo, nombre FROM Producto WHERE id_producto = :pid"
        );
        $stk->execute([':pid' => $id_producto]);
        $prod = $stk->fetch();

        if (!$prod) {
            return ['ok' => false, 'error' => 'Producto no encontrado.'];
        }
        if ($tipo === 'salida' && $prod['stock_actual'] < $cantidad) {
            return ['ok' => false, 'error' => "Solo hay {$prod['stock_actual']} unidades disponibles."];
        }

        $this->conn->prepare(
            "INSERT INTO Movimiento_Inventario (tipo_movimiento, cantidad, descripcion, id_producto, id_administrador)
             VALUES (:tipo, :cant, :desc, :pid, :adm)"
        )->execute([':tipo'=>$tipo,':cant'=>$cantidad,':desc'=>$descripcion,':pid'=>$id_producto,':adm'=>$id_adm]);

        if ($tipo === 'entrada') {
            $sql = "UPDATE Producto SET stock_actual = stock_actual + :cant WHERE id_producto = :pid";
        } elseif ($tipo === 'salida') {
            $sql = "UPDATE Producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid";
        } else {
            $sql = "UPDATE Producto SET stock_actual = :cant WHERE id_producto = :pid";
        }
        $this->conn->prepare($sql)->execute([':cant' => $cantidad, ':pid' => $id_producto]);

        // Gestionar alertas de stock
        $nuevo = $this->conn->prepare("SELECT stock_actual, stock_minimo FROM Producto WHERE id_producto = :pid");
        $nuevo->execute([':pid' => $id_producto]);
        $act = $nuevo->fetch();

        if ($act['stock_minimo'] > 0 && $act['stock_actual'] <= $act['stock_minimo']) {
            $ya = $this->conn->prepare("SELECT COUNT(*) FROM Alerta WHERE id_producto=:pid AND estado='activa'");
            $ya->execute([':pid' => $id_producto]);
            if (!(int)$ya->fetchColumn()) {
                $this->conn->prepare(
                    "INSERT INTO Alerta (tipo_alerta, mensaje, id_producto) VALUES ('stock_bajo', :msg, :pid)"
                )->execute([':msg'=>"Stock bajo: '{$prod['nombre']}' tiene {$act['stock_actual']} unidades (mínimo: {$act['stock_minimo']})",':pid'=>$id_producto]);
            }
        } elseif (in_array($tipo, ['entrada','ajuste'])) {
            $this->conn->prepare(
                "UPDATE Alerta SET estado='resuelta', fecha_resolucion=NOW()
                 WHERE id_producto=:pid AND estado='activa'"
            )->execute([':pid' => $id_producto]);
        }

        return ['ok' => true];
    }

    // ── Alertas activas ───────────────────────────────────────
    public function alertasActivas(int $limite = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT a.mensaje, a.fecha_alerta, p.nombre, p.stock_actual, p.stock_minimo
             FROM Alerta a
             JOIN Producto p ON a.id_producto = p.id_producto
             WHERE a.estado = 'activa'
             ORDER BY p.stock_actual ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Reporte movimientos por período ───────────────────────
    public function reporteMovimientos(string $desde, string $hasta): array
    {
        $stmt = $this->conn->prepare(
            "SELECT p.nombre AS producto, m.tipo_movimiento AS tipo, m.cantidad,
                    m.descripcion AS motivo,
                    COALESCE(ua.nombre, 'Sistema') AS usuario, m.fecha
             FROM Movimiento_Inventario m
             JOIN Producto p            ON m.id_producto      = p.id_producto
             LEFT JOIN Administrador a  ON a.id_administrador = m.id_administrador
             LEFT JOIN Usuario ua       ON ua.id_usuario       = a.id_usuario
             WHERE DATE(m.fecha) BETWEEN :desde AND :hasta
             ORDER BY m.fecha DESC"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }
}
