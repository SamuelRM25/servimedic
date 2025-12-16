<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Aplicando actualizaciones de base de datos...</h2>";
    
    // 1. Add columns to compras table
    echo "<h3>Tabla: compras</h3>";
    
    // Check if codigo_referencia exists
    $stmt = $conn->query("SHOW COLUMNS FROM compras LIKE 'codigo_referencia'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE compras ADD COLUMN codigo_referencia VARCHAR(20) AFTER id_compra");
        echo "<p style='color: green'>✓ Columna 'codigo_referencia' agregada.</p>";
    } else {
        echo "<p style='color: orange'>• Columna 'codigo_referencia' ya existe.</p>";
    }
    
    // Check if numero_factura exists
    $stmt = $conn->query("SHOW COLUMNS FROM compras LIKE 'numero_factura'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE compras ADD COLUMN numero_factura VARCHAR(50) AFTER tipo_factura");
        echo "<p style='color: green'>✓ Columna 'numero_factura' agregada.</p>";
    } else {
        echo "<p style='color: orange'>• Columna 'numero_factura' ya existe.</p>";
    }

    // 2. Add columns to inventario table
    echo "<h3>Tabla: inventario</h3>";
    
    // Check if codigo_referencia exists
    $stmt = $conn->query("SHOW COLUMNS FROM inventario LIKE 'codigo_referencia'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE inventario ADD COLUMN codigo_referencia VARCHAR(20) AFTER id_inventario");
        echo "<p style='color: green'>✓ Columna 'codigo_referencia' agregada.</p>";
    } else {
        echo "<p style='color: orange'>• Columna 'codigo_referencia' ya existe.</p>";
    }
    
    echo "<br><hr>";
    echo "<h3>¡Actualización completada exitosamente!</h3>";
    echo "<p>Puede cerrar esta ventana y continuar con el sistema.</p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red'>Error: " . $e->getMessage() . "</h3>";
}
?>
