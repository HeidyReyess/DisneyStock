<?php
// ============================================================
//  DisneyStock — Modelo Categoria
//  Archivo: models/Categoria.php
//  Tabla: Categoria
// ============================================================

class Categoria
{
    private PDO $conn;

    // Recibe la conexion PDO desde el controlador
    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Retorna todas las categorias ordenadas A-Z
    // Usado en los selects de productos y en el modal de categorias
    public function obtenerTodas(): array
    {
        return $this->conn
            ->query("SELECT * FROM Categoria ORDER BY nombre_categoria ASC")
            ->fetchAll();
    }

    // Verifica si ya existe una categoria con ese nombre exacto
    // Evita duplicados antes de insertar
    public function existePorNombre(string $nombre): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id_categoria FROM Categoria WHERE nombre_categoria = :n LIMIT 1"
        );
        $stmt->execute([':n' => $nombre]);
        return $stmt->rowCount() > 0;
    }

    // Inserta una nueva categoria con nombre y descripcion opcional
    public function crear(string $nombre, ?string $descripcion = null): void
    {
        $this->conn->prepare(
            "INSERT INTO Categoria (nombre_categoria, descripcion) VALUES (:n, :d)"
        )->execute([':n' => $nombre, ':d' => $descripcion]);
    }

    // Verifica si la categoria tiene productos antes de eliminar
    // Si retorna true el controlador bloquea la eliminacion
    public function tieneProductos(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM Producto WHERE id_categoria = :id"
        );
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // Elimina la categoria por ID
    // Solo se llama si tieneProductos() retorna false
    public function eliminar(int $id): void
    {
        $this->conn->prepare(
            "DELETE FROM Categoria WHERE id_categoria = :id"
        )->execute([':id' => $id]);
    }
}
