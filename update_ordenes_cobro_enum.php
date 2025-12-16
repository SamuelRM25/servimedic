<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Modify enum column
    $sql = "ALTER TABLE `ordenes_cobro` MODIFY COLUMN `tipo_cobro` enum('Consulta','Procedimiento','Examen','Otro','EPS','IGS','MAWDY','PRIVADO') NOT NULL DEFAULT 'Consulta';";
    
    $conn->exec($sql);
    echo "Table ordenes_cobro updated successfully.";
    
} catch (PDOException $e) {
    echo "Error updating table: " . $e->getMessage();
}
?>
