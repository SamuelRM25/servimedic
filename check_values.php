<?php
require_once 'config/database.php';
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query("SELECT DISTINCT tipo_factura FROM inventario");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Distinct tipo_factura values:\n";
    foreach($rows as $row) {
        echo "- '" . $row['tipo_factura'] . "'\n";
    }
    
    // Also check branches
    $stmt2 = $conn->query("SELECT id_sucursal, nombre FROM sucursales");
    echo "\nBranches:\n";
    foreach($stmt2->fetchAll(PDO::FETCH_ASSOC) as $b) {
        echo $b['id_sucursal'] . ": " . $b['nombre'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
