<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $tables = ['caja_chica', 'ordenes_cobro', 'ventas', 'detalle_ventas', 'compras'];
    
    foreach ($tables as $table) {
        echo "Table: $table\n";
        try {
            $stmt = $conn->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                echo " - " . $col['Field'] . " (" . $col['Type'] . ")\n";
            }
        } catch (PDOException $e) {
            echo " - Does not exist or error: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Connection error: " . $e->getMessage();
}
?>
