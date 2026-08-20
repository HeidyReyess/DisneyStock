<?php
// ============================================================
//  DisneyStock — Modelo de Inventario
//  Archivo: models/Inventario.php
//  Tablas: Movimiento_Inventario, Producto, Alerta
// ============================================================

class Inventario
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Retorna los ultimos N movimientos con producto y usuario
    // Hace JOIN doble para obtener el usuario: puede ser admin directo
    // o empleado via la venta asociada al movimiento
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
        // bindValue necesario para pasar entero a LIMIT — PDO no acepta string ahi
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Registra un movimiento manual y actualiza el stock del producto
    // Tipos: entrada (suma), salida (resta), ajuste (reemplaza)
    // Tambien gestiona alertas automaticamente segun el resultado
    public function registrar(int $id_producto, string $tipo, int $cantidad, ?string $descripcion, ?int $id_adm): array
    {
        // Leer el stock y nombre del producto antes de modificar
        $stk = $this->conn->prepare(
            "SELECT stock_actual, stock_minimo, nombre FROM Producto WHERE id_producto = :pid"
        );
        $stk->execute([':pid' => $id_producto]);
        $prod = $stk->fetch();

        // Verificar que el producto exista
        if (!$prod) {
            return ['ok' => false, 'error' => 'Producto no encontrado.'];
        }

        // Para salidas, verificar que haya suficiente stock disponible
        if ($tipo === 'salida' && $prod['stock_actual'] < $cantidad) {
            return ['ok' => false, 'error' => "Solo hay {$prod['stock_actual']} unidades disponibles."];
        }

        // Insertar el registro del movimiento en la tabla
        $this->conn->prepare(
            "INSERT INTO Movimiento_Inventario (tipo_movimiento, cantidad, descripcion, id_producto, id_administrador)
             VALUES (:tipo, :cant, :desc, :pid, :adm)"
        )->execute([':tipo'=>$tipo, ':cant'=>$cantidad, ':desc'=>$descripcion, ':pid'=>$id_producto, ':adm'=>$id_adm]);

        // Actualizar el stock segun el tipo de movimiento
        if ($tipo === 'entrada') {
            $sql = "UPDATE Producto SET stock_actual = stock_actual + :cant WHERE id_producto = :pid";
        } elseif ($tipo === 'salida') {
            $sql = "UPDATE Producto SET stock_actual = stock_actual - :cant WHERE id_producto = :pid";
        } else {
            // ajuste: establece el valor exacto indicado
            $sql = "UPDATE Producto SET stock_actual = :cant WHERE id_producto = :pid";
        }
        $this->conn->prepare($sql)->execute([':cant' => $cantidad, ':pid' => $id_producto]);

        // Leer el stock actualizado para decidir si crear o resolver alertas
        $nuevo = $this->conn->prepare("SELECT stock_actual, stock_minimo FROM Producto WHERE id_producto = :pid");
        $nuevo->execute([':pid' => $id_producto]);
        $act = $nuevo->fetch();

        if ($act['stock_minimo'] > 0 && $act['stock_actual'] <= $act['stock_minimo']) {
            // Stock quedo bajo el minimo: crear alerta solo si no existe una activa
            $ya = $this->conn->prepare("SELECT COUNT(*) FROM Alerta WHERE id_producto=:pid AND estado='activa'");
            $ya->execute([':pid' => $id_producto]);
            if (!(int)$ya->fetchColumn()) {
                $this->conn->prepare(
                    "INSERT INTO Alerta (tipo_alerta, mensaje, id_producto) VALUES ('stock_bajo', :msg, :pid)"
                )->execute([
                    ':msg' => "Stock bajo: '{$prod['nombre']}' tiene {$act['stock_actual']} unidades (minimo: {$act['stock_minimo']})",
                    ':pid' => $id_producto
                ]);
            }
        } elseif (in_array($tipo, ['entrada', 'ajuste'])) {
            // Entrada o ajuste con stock normalizado: resolver alertas activas
            $this->conn->prepare(
                "UPDATE Alerta SET estado='resuelta', fecha_resolucion=NOW()
                 WHERE id_producto=:pid AND estado='activa'"
            )->execute([':pid' => $id_producto]);
        }

        return ['ok' => true];
    }

    // Retorna las N alertas activas ordenadas por stock mas bajo primero
    // Usado en el panel de alertas del dashboard
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

    // Retorna todos los movimientos del periodo para el reporte
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
