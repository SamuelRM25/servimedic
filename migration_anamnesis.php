<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $sql = "ALTER TABLE pacientes 
            ADD COLUMN motivo_consulta TEXT DEFAULT NULL,
            ADD COLUMN sintomass TEXT DEFAULT NULL,
            ADD COLUMN historia_clinica TEXT DEFAULT NULL,
            ADD COLUMN medicacion_actual TEXT DEFAULT NULL,
            ADD COLUMN alergias TEXT DEFAULT NULL,
            ADD COLUMN historial_familiar TEXT DEFAULT NULL,
            ADD COLUMN estilo_vida TEXT DEFAULT NULL,
            ADD COLUMN contacto_emergencia_nombre VARCHAR(200) DEFAULT NULL,
            ADD COLUMN contacto_emergencia_telefono VARCHAR(20) DEFAULT NULL";
            
    // Fix typo in previous attempt if any, 'sintomas' is better than 'sintomass' but let's stick to standard
    // Actually, I'll use 'sintomas' correctly.
    $sql = "ALTER TABLE pacientes 
            ADD COLUMN motivo_consulta TEXT DEFAULT NULL,
            ADD COLUMN sintomas TEXT DEFAULT NULL,
            ADD COLUMN historia_clinica TEXT DEFAULT NULL,
            ADD COLUMN medicacion_actual TEXT DEFAULT NULL,
            ADD COLUMN alergias TEXT DEFAULT NULL,
            ADD COLUMN historial_familiar TEXT DEFAULT NULL,
            ADD COLUMN estilo_vida TEXT DEFAULT NULL,
            ADD COLUMN contacto_emergencia_nombre VARCHAR(200) DEFAULT NULL,
            ADD COLUMN contacto_emergencia_telefono VARCHAR(20) DEFAULT NULL";

    $conn->exec($sql);
    echo "Migration completed successfully.";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
