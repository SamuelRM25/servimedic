<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Tables in database:\n";
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
        
        $stmt2 = $conn->query("DESCRIBE " . $row[0]);
        echo "  Columns:\n";
        while ($col = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            echo "    " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
