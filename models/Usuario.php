<?php
// ============================================================
//  DisneyStock — Modelo de Usuario
//  Archivo: models/Usuario.php
//  Tablas: Usuario, Administrador, Empleado
//
//  El sistema usa tres tablas relacionadas:
//  Usuario (datos base de autenticacion)
//  Administrador (rol admin, apunta a Usuario)
//  Empleado (rol empleado, apunta a Usuario)
// ============================================================

class Usuario
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Busca usuario activo por nombre de usuario para el proceso de login
    // Hace JOIN con Administrador y Empleado para saber el rol
    // COALESCE en el CASE para manejar usuarios que no esten en ninguna tabla de rol
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

    // Verifica si el nombre de usuario ya esta en uso
    // Llamado antes de registrar o crear para evitar duplicados
    public function existeUsuario(string $usuario): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario FROM Usuario WHERE usuario = :usuario LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->rowCount() > 0;
    }

    // Crea el usuario en dos pasos dentro de una transaccion:
    // 1. Inserta en Usuario con nombre, usuario y contrasena hasheada
    // 2. Inserta en Administrador o Empleado segun el rol
    // Si algo falla hace rollBack y retorna el mensaje de error
    public function registrar(array $datos): bool|string
    {
        try {
            $this->conn->beginTransaction();

            // Paso 1: insertar datos base en la tabla Usuario
            $stmt = $this->conn->prepare(
                "INSERT INTO Usuario (nombre, usuario, contrasena)
                 VALUES (:nombre, :usuario, :contrasena)"
            );
            $stmt->execute([
                ':nombre'     => $datos['nombre'],
                ':usuario'    => $datos['usuario'],
                ':contrasena' => $datos['password_hash'], // ya viene hasheada del controlador
            ]);
            $id_usuario = (int)$this->conn->lastInsertId();

            // Paso 2: insertar en la tabla de rol correspondiente
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

    // Lista todos los usuarios con su rol calculado por JOIN
    // Ordenados por fecha de creacion descendente (mas nuevos primero)
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

    // Actualiza nombre y usuario — la contrasena solo si viene en $datos
    // Esto permite editar sin tocar la clave si el admin no escribio una nueva
    public function actualizar(int $id_usuario, array $datos): bool|string
    {
        try {
            $sql = "UPDATE Usuario SET nombre = :nombre, usuario = :usuario, updated_at = NOW()";

            // Agregar campo de contrasena al UPDATE solo si se envio una nueva
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

    // Activa (1) o desactiva (0) un usuario por ID
    // El controlador evita que un admin se desactive a si mismo
    public function cambiarEstado(int $id_usuario, int $activo): void
    {
        $this->conn->prepare(
            "UPDATE Usuario SET activo = :activo WHERE id_usuario = :id"
        )->execute([':activo' => $activo, ':id' => $id_usuario]);
    }

    // Busca un usuario por ID — usado para leer el estado actual antes del toggle
    public function obtenerPorId(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario AS id, nombre, usuario, activo FROM Usuario WHERE id_usuario = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
