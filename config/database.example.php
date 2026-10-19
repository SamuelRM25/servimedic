<?php
// config/database.php - Copia este archivo como database.php y llena tus datos reales.
// NOTA: database.php está en .gitignore y NO debe subirse al repositorio.

class Database {
    private $host = "HOST_DE_TU_BD";
    private $db_name = "NOMBRE_DE_TU_BD";
    private $username = "USUARIO_DE_TU_BD";
    private $password = "CONTRASENA_DE_TU_BD";
    private $port = "3306";
    private $conn = null;

    public function getConnection() {
        try {
            if ($this->conn === null) {
                $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8";

                $this->conn = new PDO(
                    $dsn,
                    $this->username,
                    $this->password,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    )
                );
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}
?>
