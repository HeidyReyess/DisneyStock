<?php
// ============================================================
//  DisneyStock — Configuración de base de datos
//  Copia este archivo como database.php y ajusta los valores
//  según tu entorno local.
// ============================================================

class Database
{
    private string $host     = "127.0.0.1";
    private string $port     = "3306";       // Laragon usa 3306 o 3320
    private string $db_name  = "disneystock";
    private string $username = "root";
    private string $password = "";

    public ?PDO $conn = null;

    public function conectar(): PDO
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            die(json_encode([
                'error'   => true,
                'mensaje' => 'No se pudo conectar a la base de datos DisneyStock.'
            ]));
        }

        return $this->conn;
    }
}
