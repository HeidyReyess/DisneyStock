<?php
// ============================================================
//  DisneyStock — Modelo de Usuario
//  Archivo: models/Usuario.php
//
//  RESPONSABILIDAD:
//  Operaciones sobre usuarios, administradores y empleados.
//  El sistema usa tres tablas relacionadas: Usuario (datos base),
//  Administrador (rol admin) y Empleado (rol empleado).
//
//  MÉTODOS DISPONIBLES:
//  - obtenerPorUsuario($usuario)
//      → Busca usuario activo por nombre de usuario para el login.
//        Hace JOIN con Administrador y Empleado para determinar el rol.
//        Retorna array con id, nombre, usuario, password_hash, rol,
//        id_administrador e id_empleado.
//  - existeUsuario($usuario)
//      → Verifica si el nombre de usuario ya está en uso.
//        Usado en registro y creación de usuarios.
//  - registrar($datos)
//      → TRANSACCIÓN: inserta en Usuario y luego en Administrador
//        o Empleado según el rol. Retorna true o string de error.
//  - obtenerTodos()
//      → Lista todos los usuarios con su rol determinado por JOIN.
//        Usado en la vista de gestión de usuarios.
//  - actualizar($id_usuario, $datos)
//      → Actualiza nombre y usuario. Si viene 'password_hash'
//        en $datos, también actualiza la contraseña.
//  - cambiarEstado($id_usuario, $activo)
//      → Activa (1) o desactiva (0) un usuario.
//  - obtenerPorId($id)
//      → Busca usuario por ID. Usado en toggle de estado.
// ============================================================

class Usuario
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // ── Obtener usuario activo por nombre de usuario (login) ─
    public function obtenerPorUsuario(string $usuario): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id_usuario AS id,
                    u.nombre,
                    u.usuario,
                    u.contrasena AS password_hash,
                    u.activo,
                    CASE
                        WHEN a.id_administrador IS NOT NULL THEN 'admin'
                        WHEN e.id_empleado IS NOT NULL THEN 'empleado'
                        ELSE 'empleado'
                    END AS rol,
                    a.id_administrador,
                    e.id_empleado
             FROM Usuario u
             LEFT JOIN Administrador a ON a.id_usuario = u.id_usuario
             LEFT JOIN Empleado e ON e.id_usuario = u.id_usuario
             WHERE u.usuario = :usuario AND u.activo = 1
             LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->fetch();
    }

    // ── Verificar si un nombre de usuario ya existe ───────────
    public function existeUsuario(string $usuario): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario FROM Usuario WHERE usuario = :usuario LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->rowCount() > 0;
    }

    // ── Registrar nuevo usuario + rol ─────────────────────────
    public function registrar(array $datos): bool|string
    {
        try {
            $this->conn->beginTransaction();

            // Insertar en Usuario
            $stmt = $this->conn->prepare(
                "INSERT INTO Usuario (nombre, usuario, contrasena)
                 VALUES (:nombre, :usuario, :contrasena)"
            );
            $stmt->execute([
                ':nombre'    => $datos['nombre'],
                ':usuario'   => $datos['usuario'],
                ':contrasena' => $datos['password_hash'],
            ]);
            $id_usuario = (int)$this->conn->lastInsertId();

            // Insertar en tabla de rol
            if (($datos['rol'] ?? 'empleado') === 'admin') {
                $this->conn->prepare(
                    "INSERT INTO Administrador (id_usuario) VALUES (:id)"
                )->execute([':id' => $id_usuario]);
            } else {
                $this->conn->prepare(
                    "INSERT INTO Empleado (id_usuario) VALUES (:id)"
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

    // ── Obtener todos los usuarios con su rol ─────────────────
    public function obtenerTodos(): array
    {
        $stmt = $this->conn->query(
            "SELECT u.id_usuario AS id,
                    u.nombre,
                    u.usuario,
                    u.activo,
                    u.created_at,
                    CASE
                        WHEN a.id_administrador IS NOT NULL THEN 'admin'
                        ELSE 'empleado'
                    END AS rol,
                    a.id_administrador,
                    e.id_empleado
             FROM Usuario u
             LEFT JOIN Administrador a ON a.id_usuario = u.id_usuario
             LEFT JOIN Empleado e ON e.id_usuario = u.id_usuario
             ORDER BY u.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    // ── Actualizar datos de usuario ───────────────────────────
    public function actualizar(int $id_usuario, array $datos): bool|string
    {
        try {
            $sql = "UPDATE Usuario SET nombre = :nombre, usuario = :usuario, updated_at = NOW()";
            if (!empty($datos['password_hash'])) {
                $sql .= ", contrasena = :contrasena";
            }
            $sql .= " WHERE id_usuario = :id";

            $params = [
                ':nombre'  => $datos['nombre'],
                ':usuario' => $datos['usuario'],
                ':id'      => $id_usuario,
            ];
            if (!empty($datos['password_hash'])) {
                $params[':contrasena'] = $datos['password_hash'];
            }

            $this->conn->prepare($sql)->execute($params);
            return true;

        } catch (PDOException $e) {
            error_log("Usuario::actualizar — " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }

    // ── Activar / desactivar ──────────────────────────────────
    public function cambiarEstado(int $id_usuario, int $activo): void
    {
        $this->conn->prepare(
            "UPDATE Usuario SET activo = :activo WHERE id_usuario = :id"
        )->execute([':activo' => $activo, ':id' => $id_usuario]);
    }

    // ── Obtener usuario por ID ────────────────────────────────
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario AS id, nombre, usuario, activo FROM Usuario WHERE id_usuario = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
