<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->query("SELECT DISTINCT rol FROM usuarios");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Roles found in database:\n";
    foreach ($roles as $rol) {
        echo "- '$rol'\n";
    }
    
    // Also count users per role
    echo "\nUser counts:\n";
    $stmt = $conn->query("SELECT rol, COUNT(*) as count FROM usuarios GROUP BY rol");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "- {$row['rol']}: {$row['count']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
