<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM inventario LIKE 'codigo_barras'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Add column
        $sql = "ALTER TABLE inventario ADD COLUMN codigo_barras VARCHAR(50) AFTER id_inventario";
        $conn->exec($sql);
        echo "Column 'codigo_barras' added successfully.\n";
    } else {
        echo "Column 'codigo_barras' already exists.\n";
    }
    
    // Also add index for performance
    try {
        $conn->exec("CREATE INDEX idx_codigo_barras ON inventario(codigo_barras)");
        echo "Index added.\n";
    } catch (PDOException $e) {
        // Index might exist, ignore
        echo "Index check: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
