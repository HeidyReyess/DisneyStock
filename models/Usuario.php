<?php
// ============================================================
//  DisneyStock — Modelo de Usuario
//  Archivo: models/Usuario.php
//  BD: disney_stock
//  Tablas: usuario, administrador, empleado
//
//  CAMBIOS respecto a BD anterior:
//  - contrasena  → contrasenia (con i)
//  - activo (tinyint) → estado ('activo' | 'inactivo')
//  - created_at  → fecha_registro
//  - sin updated_at
//  - nueva columna: requiere_cambio_contrasenia (tinyint)
// ============================================================

class Usuario
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Busca usuario activo por nombre de usuario para el login
    // Retorna array con id, nombre, password_hash, rol, id_administrador, id_empleado
    // o false si no existe o está inactivo
    public function obtenerPorUsuario(string $usuario): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id_usuario AS id,
                    u.nombre,
                    u.usuario,
                    u.contrasenia AS password_hash,
                    u.estado,
                    u.requiere_cambio_contrasenia,
                    CASE
                        WHEN a.id_administrador IS NOT NULL THEN 'admin'
                        WHEN e.id_empleado IS NOT NULL THEN 'empleado'
                        ELSE 'empleado'
                    END AS rol,
                    a.id_administrador,
                    e.id_empleado
             FROM usuario u
             LEFT JOIN administrador a ON a.id_usuario = u.id_usuario
             LEFT JOIN empleado e ON e.id_usuario = u.id_usuario
             WHERE u.usuario = :usuario AND u.estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->fetch();
    }

    // Verifica si el nombre de usuario ya existe — para evitar duplicados
    public function existeUsuario(string $usuario): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario FROM usuario WHERE usuario = :usuario LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->rowCount() > 0;
    }

    // Registra un nuevo usuario en dos pasos dentro de una transaccion:
    // 1. Inserta en usuario con contrasenia hasheada
    // 2. Inserta en administrador o empleado segun el rol
    public function registrar(array $datos): bool|string
    {
        try {
            $this->conn->beginTransaction();

            // Paso 1: insertar datos base del usuario
            $stmt = $this->conn->prepare(
                "INSERT INTO usuario (nombre, usuario, contrasenia, estado, fecha_registro, requiere_cambio_contrasenia)
                 VALUES (:nombre, :usuario, :contrasenia, 'activo', CURDATE(), 0)"
            );
            $stmt->execute([
                ':nombre'      => $datos['nombre'],
                ':usuario'     => $datos['usuario'],
                ':contrasenia' => $datos['password_hash'], // llega ya hasheada del controlador
            ]);
            $id_usuario = (int)$this->conn->lastInsertId();

            // Paso 2: insertar en la tabla de rol correspondiente
            if (($datos['rol'] ?? 'empleado') === 'admin') {
                $this->conn->prepare(
                    "INSERT INTO administrador (id_usuario) VALUES (:id)"
                )->execute([':id' => $id_usuario]);
            } else {
                $this->conn->prepare(
                    "INSERT INTO empleado (id_usuario) VALUES (:id)"
                )->execute([':id' => $id_usuario]);
            }

            $this->conn->commit();
            return true;

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Usuario::registrar — " . $e->getMessage());
            return "Error al registrar: " . $e->getMessage();
        }
    }

    // Lista todos los usuarios con su rol calculado por JOIN
    // Ordenados por fecha_registro descendente
    public function obtenerTodos(): array
    {
        $stmt = $this->conn->query(
            "SELECT u.id_usuario AS id,
                    u.nombre,
                    u.usuario,
                    u.estado,
                    u.fecha_registro AS created_at,
                    u.requiere_cambio_contrasenia,
                    CASE
                        WHEN a.id_administrador IS NOT NULL THEN 'admin'
                        ELSE 'empleado'
                    END AS rol,
                    a.id_administrador,
                    e.id_empleado
             FROM usuario u
             LEFT JOIN administrador a ON a.id_usuario = u.id_usuario
             LEFT JOIN empleado e ON e.id_usuario = u.id_usuario
             ORDER BY u.fecha_registro DESC"
        );
        return $stmt->fetchAll();
    }

    // Actualiza nombre y usuario
    // Solo actualiza contrasenia si viene en $datos (no cambiar si no se envio)
    public function actualizar(int $id_usuario, array $datos): bool|string
    {
        try {
            $sql = "UPDATE usuario SET nombre = :nombre, usuario = :usuario";

            // Agregar contrasenia al UPDATE solo si el admin envio una nueva
            if (!empty($datos['password_hash'])) {
                $sql .= ", contrasenia = :contrasenia";
            }
            $sql .= " WHERE id_usuario = :id";

            $params = [
                ':nombre'  => $datos['nombre'],
                ':usuario' => $datos['usuario'],
                ':id'      => $id_usuario,
            ];
            if (!empty($datos['password_hash'])) {
                $params[':contrasenia'] = $datos['password_hash'];
            }

            $this->conn->prepare($sql)->execute($params);
            return true;

        } catch (PDOException $e) {
            error_log("Usuario::actualizar — " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    // Activa ('activo') o desactiva ('inactivo') un usuario
    // El controlador evita que el admin se desactive a si mismo
    public function cambiarEstado(int $id_usuario, string $estado): void
    {
        $this->conn->prepare(
            "UPDATE usuario SET estado = :estado WHERE id_usuario = :id"
        )->execute([':estado' => $estado, ':id' => $id_usuario]);
    }

    // Busca usuario por ID — usado para leer el estado antes del toggle
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario AS id, nombre, usuario, estado FROM usuario WHERE id_usuario = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
