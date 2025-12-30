<?php
// config/database.php

class Database {
    private $host = "buyvuolarphibfd4i5ie-mysql.services.clever-cloud.com";
    private $db_name = "buyvuolarphibfd4i5ie";
    private $username = "uebyutsweyo11mee";
    private $password = "7sVDIlXBSrSGUDS4R1J"; // MAMP suele usar 'root' como contraseña por defecto
    private $port = "20926";
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