
<?php
// config/database.php

class Database {
    private $host = "bkzonlznatzzfkelstum-mysql.services.clever-cloud.com";
    private $db_name = "bkzonlznatzzfkelstum";
    private $username = "us1c5wbm2waphqnm";
    private $password = "vwFAkN5AuK4FAnyB3QQo";
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