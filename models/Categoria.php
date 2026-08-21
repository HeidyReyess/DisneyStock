<?php
// ============================================================
//  DisneyStock — Modelo Categoria
//  Archivo: models/Categoria.php
//  BD: disney_stock — Tabla: categoria (minuscula)
// ============================================================

class Categoria
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // Retorna todas las categorias ordenadas A-Z
    public function obtenerTodas(): array
    {
        return $this->conn
            ->query("SELECT * FROM categoria ORDER BY nombre_categoria ASC")
            ->fetchAll();
    }

    // Verifica si ya existe una categoria con ese nombre exacto
    public function existePorNombre(string $nombre): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id_categoria FROM categoria WHERE nombre_categoria = :n LIMIT 1"
        );
        $stmt->execute([':n' => $nombre]);
        return $stmt->rowCount() > 0;
    }

    // Inserta una nueva categoria
    public function crear(string $nombre, ?string $descripcion = null): void
    {
        $this->conn->prepare(
            "INSERT INTO categoria (nombre_categoria, descripcion) VALUES (:n, :d)"
        )->execute([':n' => $nombre, ':d' => $descripcion]);
    }

    // Verifica si la categoria tiene productos — bloquea eliminacion si true
    public function tieneProductos(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM producto WHERE id_categoria = :id"
        );
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // Elimina la categoria por ID
    public function eliminar(int $id): void
    {
        $this->conn->prepare(
            "DELETE FROM categoria WHERE id_categoria = :id"
        )->execute([':id' => $id]);
    }
}
