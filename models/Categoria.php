<?php
// ============================================================
//  DisneyStock — Modelo Categoria
//  Archivo: models/Categoria.php
//
//  RESPONSABILIDAD:
//  Operaciones sobre categorías de productos.
//
//  MÉTODOS DISPONIBLES:
//  - obtenerTodas()
//      → Lista todas las categorías ordenadas por nombre.
//        Usado en selects de productos y filtros.
//  - existePorNombre($nombre)
//      → Verifica si ya existe una categoría con ese nombre.
//        Usado para evitar duplicados al crear.
//  - crear($nombre, $descripcion)
//      → Inserta una nueva categoría.
//  - tieneProductos($id)
//      → Verifica si la categoría tiene productos asociados.
//        Usado para bloquear eliminación si aplica.
//  - eliminar($id)
//      → Borra la categoría por ID.
// ============================================================

class Categoria
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function obtenerTodas(): array
    {
        return $this->conn
            ->query("SELECT * FROM Categoria ORDER BY nombre_categoria ASC")
            ->fetchAll();
    }

    public function existePorNombre(string $nombre): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id_categoria FROM Categoria WHERE nombre_categoria = :n LIMIT 1"
        );
        $stmt->execute([':n' => $nombre]);
        return $stmt->rowCount() > 0;
    }

    public function crear(string $nombre, ?string $descripcion = null): void
    {
        $this->conn->prepare(
            "INSERT INTO Categoria (nombre_categoria, descripcion) VALUES (:n, :d)"
        )->execute([':n' => $nombre, ':d' => $descripcion]);
    }

    public function tieneProductos(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM Producto WHERE id_categoria = :id"
        );
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function eliminar(int $id): void
    {
        $this->conn->prepare(
            "DELETE FROM Categoria WHERE id_categoria = :id"
        )->execute([':id' => $id]);
    }
}
